<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\CompanyLifecycleService;
use App\Services\PaymentInstructionService;
use App\Services\SubscriptionPaymentService;
use App\Services\TenantContext;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PlatformPaymentController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, CompanyLifecycleService $lifecycle)
    {
        $lifecycle->syncExpiredCompanies();

        $status = strtoupper(trim((string) $request->query('status', '')));
        $requestType = strtoupper(trim((string) $request->query('request_type', '')));
        $source = strtoupper(trim((string) $request->query('source', '')));
        $companyId = (int) $request->query('company_id', 0);
        $planId = (int) $request->query('subscription_plan_id', 0);
        $appliedState = strtolower(trim((string) $request->query('applied_state', '')));
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));
        $search = trim((string) $request->query('search', ''));

        $payments = $tenantContext->withoutScope(function () use (
            $status,
            $requestType,
            $source,
            $companyId,
            $planId,
            $appliedState,
            $dateFrom,
            $dateTo,
            $search
        ) {
            return SubscriptionPayment::with(['company', 'subscriptionPlan', 'paymentInstruction', 'requestedByUser', 'verifiedByAdmin'])
                ->when($status !== '' && array_key_exists($status, SubscriptionPayment::STATUSES), fn ($q) => $q->where('status', $status))
                ->when($requestType !== '' && array_key_exists($requestType, SubscriptionPayment::REQUEST_TYPES), fn ($q) => $q->where('request_type', $requestType))
                ->when($source !== '' && array_key_exists($source, SubscriptionPayment::SOURCES), fn ($q) => $q->where('source', $source))
                ->when($companyId > 0, fn ($q) => $q->where('company_id', $companyId))
                ->when($planId > 0, fn ($q) => $q->where('subscription_plan_id', $planId))
                ->when($appliedState === 'applied', fn ($q) => $q->whereNotNull('applied_to_subscription_at'))
                ->when($appliedState === 'unapplied', fn ($q) => $q->whereNull('applied_to_subscription_at')->where('status', SubscriptionPayment::STATUS_VERIFIED))
                ->when($dateFrom !== '', fn ($q) => $q->whereDate('payment_date', '>=', $dateFrom))
                ->when($dateTo !== '', fn ($q) => $q->whereDate('payment_date', '<=', $dateTo))
                ->when($search !== '', function ($q) use ($search): void {
                    $q->where(function ($sub) use ($search): void {
                        $sub->where('payment_reference', 'like', "%{$search}%")
                            ->orWhere('customer_transfer_reference', 'like', "%{$search}%")
                            ->orWhere('payer_name', 'like', "%{$search}%")
                            ->orWhereHas('company', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                    });
                })
                ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 WHEN status = 'VERIFIED' AND applied_to_subscription_at IS NULL THEN 1 ELSE 2 END ASC")
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();
        });

        $stats = [
            'pending' => SubscriptionPayment::where('status', SubscriptionPayment::STATUS_PENDING)->count(),
            'verified_unapplied' => SubscriptionPayment::where('status', SubscriptionPayment::STATUS_VERIFIED)->whereNull('applied_to_subscription_at')->count(),
            'verified_count' => SubscriptionPayment::where('status', SubscriptionPayment::STATUS_VERIFIED)->count(),
            'verified_amount' => (string) SubscriptionPayment::where('status', SubscriptionPayment::STATUS_VERIFIED)->sum('amount'),
            'rejected' => SubscriptionPayment::where('status', SubscriptionPayment::STATUS_REJECTED)->count(),
        ];

        $companies = $tenantContext->withoutScope(fn () => Company::orderBy('name')->get(['id', 'code', 'name', 'account_status']));
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('id')->get(['id', 'code', 'name', 'tier']);

        return view('platform.payments.index', compact(
            'payments',
            'stats',
            'companies',
            'plans',
            'status',
            'requestType',
            'source',
            'companyId',
            'planId',
            'appliedState',
            'dateFrom',
            'dateTo',
            'search'
        ));
    }

    public function create(Request $request, TenantContext $tenantContext, PaymentInstructionService $instructionService)
    {
        $companies = $tenantContext->withoutScope(fn () => Company::with('subscriptionPlan')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'account_status', 'access_type', 'subscription_plan_id', 'access_expires_at']));

        $plans = SubscriptionPlan::where('is_active', 1)
            ->where('is_internal', 0)
            ->where('price', '>', 0)
            ->where('tier', '!=', Company::ACCESS_FREE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $instructions = $instructionService->getActiveInstructions();
        $selectedCompanyId = (int) $request->query('company_id', 0);
        $billingSettings = $instructionService->getBillingSettings();

        return view('platform.payments.create', compact(
            'companies',
            'plans',
            'instructions',
            'selectedCompanyId',
            'billingSettings'
        ));
    }

    public function store(
        Request $request,
        TenantContext $tenantContext,
        PaymentInstructionService $instructionService,
        SubscriptionPaymentService $paymentService
    ) {
        $maxKb = (int) ($instructionService->getBillingSettings()['payment_proof_max_kb'] ?? PaymentInstructionService::DEFAULT_MAX_PROOF_KB);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')],
            'request_type' => ['required', 'string', Rule::in(array_keys(SubscriptionPayment::REQUEST_TYPES))],
            'billing_interval' => ['required', 'string', Rule::in(array_keys(SubscriptionPlan::BILLING_INTERVALS))],
            'duration_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999'],
            'payment_instruction_id' => ['nullable', 'integer', Rule::exists('payment_instructions', 'id')],
            'payment_method_name' => ['nullable', 'string', 'max:120'],
            'customer_transfer_reference' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'payment_date' => ['required', 'date'],
            'recorded_status' => ['required', 'string', Rule::in([SubscriptionPayment::STATUS_PENDING, SubscriptionPayment::STATUS_VERIFIED])],
            'payment_received_confirmed' => ['nullable', 'boolean'],
            'verification_note' => ['nullable', 'string', 'max:1000'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:' . $maxKb],
        ]);

        $company = $tenantContext->withoutScope(fn () => Company::findOrFail((int) $validated['company_id']));

        try {
            $payment = $paymentService->recordManualOfflinePayment(
                company: $company,
                admin: $this->platformAdmin(),
                input: array_merge($validated, [
                    'payment_received_confirmed' => $request->boolean('payment_received_confirmed'),
                ]),
                proofFile: $request->file('payment_proof'),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.payments.show', $payment)
            ->with('success', __('payments.flash.admin_recorded', ['ref' => $payment->payment_reference]));
    }

    public function show(
        SubscriptionPayment $payment,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        SubscriptionPaymentService $paymentService
    ) {
        $payment = $tenantContext->withoutScope(function () use ($payment, $lifecycle) {
            $loaded = SubscriptionPayment::with([
                'company.subscriptionPlan',
                'subscriptionPlan',
                'previousPlan',
                'paymentInstruction',
                'requestedByUser',
                'recordedByAdmin',
                'verifiedByAdmin',
                'rejectedByAdmin',
                'cancelledByUser',
                'cancelledByAdmin',
                'appliedByAdmin',
                'replacedByPayment',
                'renewalRequest',
            ])->findOrFail($payment->id);

            $lifecycle->determineBlockReason($loaded->company);
            $loaded->company->refresh()->load('subscriptionPlan');

            return $loaded;
        });

        $applicationPreview = $paymentService->previewApplicationResult(
            payment: $payment,
            company: $payment->company
        );

        $auditLogs = PlatformAuditLog::where('subscription_payment_id', $payment->id)
            ->with(['platformAdmin', 'actorUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('platform.payments.show', compact(
            'payment',
            'applicationPreview',
            'auditLogs'
        ));
    }

    public function downloadProof(Request $request, SubscriptionPayment $payment)
    {
        $disk = $payment->payment_proof_disk ?: SubscriptionPaymentService::PROOF_DISK;
        if (!$payment->payment_proof_path || !Storage::disk($disk)->exists($payment->payment_proof_path)) {
            abort(404, __('payments.errors.proof_not_found'));
        }

        $filename = $payment->payment_proof_original_name ?: ($payment->payment_reference . '.bin');
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download') || !$payment->isImageProof()) {
            return Storage::disk($disk)->download($payment->payment_proof_path, $filename, $headers);
        }

        return Storage::disk($disk)->response($payment->payment_proof_path, $filename, $headers);
    }

    public function verify(Request $request, SubscriptionPayment $payment, SubscriptionPaymentService $paymentService)
    {
        $validated = $request->validate([
            'confirm_verify' => ['accepted'],
            'verification_note' => ['nullable', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'confirm_verify.accepted' => __('payments.validation.confirm_verify_required'),
        ]);

        try {
            $paymentService->verifyPayment(
                payment: $payment,
                admin: $this->platformAdmin(),
                verificationNote: $validated['verification_note'] ?? null,
                adminNote: $request->has('admin_note') ? ($validated['admin_note'] ?? '') : null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.payments.show', $payment)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.payments.show', $payment)
            ->with('success', __('payments.flash.verified', ['ref' => $payment->payment_reference]));
    }

    public function reject(Request $request, SubscriptionPayment $payment, SubscriptionPaymentService $paymentService)
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $paymentService->rejectPayment(
                payment: $payment,
                admin: $this->platformAdmin(),
                rejectionReason: $validated['rejection_reason'],
                adminNote: $request->has('admin_note') ? ($validated['admin_note'] ?? '') : null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.payments.show', $payment)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.payments.show', $payment)
            ->with('success', __('payments.flash.rejected', ['ref' => $payment->payment_reference]));
    }

    public function cancel(Request $request, SubscriptionPayment $payment, SubscriptionPaymentService $paymentService)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $paymentService->cancelByAdmin(
                payment: $payment,
                admin: $this->platformAdmin(),
                reason: $validated['cancellation_reason'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.payments.show', $payment)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.payments.show', $payment)
            ->with('success', __('payments.flash.admin_cancelled', ['ref' => $payment->payment_reference]));
    }

    public function applyToSubscription(Request $request, SubscriptionPayment $payment, SubscriptionPaymentService $paymentService)
    {
        $validated = $request->validate([
            'confirm_apply' => ['accepted'],
            'expiration_action' => [
                'required',
                'string',
                Rule::in([
                    SubscriptionPayment::EXPIRY_ACTION_AUTO,
                    SubscriptionPayment::EXPIRY_ACTION_KEEP,
                    SubscriptionPayment::EXPIRY_ACTION_EXTEND,
                    SubscriptionPayment::EXPIRY_ACTION_FROM_START,
                    SubscriptionPayment::EXPIRY_ACTION_CUSTOM,
                ]),
            ],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'custom_expires_at' => ['nullable', 'date', 'required_if:expiration_action,' . SubscriptionPayment::EXPIRY_ACTION_CUSTOM],
            'note' => ['nullable', 'string', 'max:1000'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'confirm_apply.accepted' => __('payments.validation.confirm_apply_required'),
        ]);

        try {
            $updated = $paymentService->applyVerifiedPayment(
                payment: $payment,
                admin: $this->platformAdmin(),
                options: [
                    'expiration_action' => $validated['expiration_action'],
                    'duration_days' => $validated['duration_days'] ?? $payment->duration_days,
                    'custom_expires_at' => !empty($validated['custom_expires_at'])
                        ? Carbon::parse($validated['custom_expires_at'])->endOfDay()->toIso8601String()
                        : null,
                    'note' => $validated['note'] ?? null,
                    'admin_note' => $request->has('admin_note') ? ($validated['admin_note'] ?? '') : null,
                ],
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()
                ->route('platform.payments.show', $payment)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.payments.show', $updated)
            ->with('success', __('payments.flash.applied', ['ref' => $updated->payment_reference, 'company' => $updated->company->name]));
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();
        if (!$admin instanceof PlatformAdmin) {
            abort(403, 'Platform administrator identity required.');
        }

        return $admin;
    }
}
