<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalRequest;
use App\Services\CompanyLifecycleService;
use App\Services\PaymentInstructionService;
use App\Services\RbacService;
use App\Services\RenewalReminderService;
use App\Services\SubscriptionPaymentService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerSubscriptionController extends Controller
{
    public function index(
        Request $request,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac,
        SubscriptionPlanService $planService,
        PaymentInstructionService $instructionService,
        RenewalReminderService $reminderService
    ) {
        $company = $this->resolveAuthorizedBillingCompany($request, $tenantContext, $lifecycle, $rbac, false);
        $canManageBilling = $rbac->canManageCompanyBilling($request->user(), (int) $company->id, true);

        $lifecycle->determineBlockReason($company);
        $company->refresh()->load(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)]);

        $effectiveStatus = $company->effectiveStatus();
        $resolvedPlan = $planService->getCompanyPlan($company);
        $entitlements = $planService->getCompanyEntitlements($company);
        $usageSummary = $company->isOperationallyActive()
            ? $tenantContext->withoutScope(fn () => $planService->getUsageSummary($company))
            : [];

        $reminder = $reminderService->evaluateCompany($company);
        $billingSettings = $instructionService->getBillingSettings();
        $instructions = $instructionService->getActiveInstructions();

        $availablePlans = SubscriptionPlan::where('is_active', 1)
            ->where('is_internal', 0)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $payments = $tenantContext->withoutScope(fn () => SubscriptionPayment::where('company_id', $company->id)
            ->with(['subscriptionPlan', 'paymentInstruction', 'renewalRequest'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString());

        $pendingPayment = $tenantContext->withoutScope(fn () => SubscriptionPayment::where('company_id', $company->id)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->orderByDesc('id')
            ->first());

        $latestRenewalRequest = $tenantContext->withoutScope(fn () => SubscriptionRenewalRequest::where('company_id', $company->id)
            ->with(['requestedPlan', 'payment'])
            ->orderByDesc('id')
            ->first());

        $useAdminLayout = $company->isOperationallyActive();

        return view('subscription.index', compact(
            'company',
            'effectiveStatus',
            'resolvedPlan',
            'entitlements',
            'usageSummary',
            'reminder',
            'billingSettings',
            'instructions',
            'availablePlans',
            'payments',
            'pendingPayment',
            'latestRenewalRequest',
            'canManageBilling',
            'useAdminLayout'
        ));
    }

    public function createPayment(
        Request $request,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac,
        SubscriptionPlanService $planService,
        PaymentInstructionService $instructionService,
        RenewalReminderService $reminderService
    ) {
        $company = $this->resolveAuthorizedBillingCompany($request, $tenantContext, $lifecycle, $rbac, true);
        $lifecycle->determineBlockReason($company);
        $company->refresh()->load('subscriptionPlan');

        $effectiveStatus = $company->effectiveStatus();
        if ($effectiveStatus === Company::STATUS_CANCELLED) {
            return redirect()
                ->route('subscription.index')
                ->with('error', __('payments.errors.cancelled_workspace'));
        }

        $billingSettings = $instructionService->getBillingSettings();
        if (!($billingSettings['allow_customer_payment_submissions'] ?? true)) {
            return redirect()
                ->route('subscription.index')
                ->with('error', __('payments.errors.submissions_disabled'));
        }

        $instructions = $instructionService->getActiveInstructions();
        $paidPlans = SubscriptionPlan::where('is_active', 1)
            ->where('is_internal', 0)
            ->where('price', '>', 0)
            ->where('tier', '!=', Company::ACCESS_FREE)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $currentPlan = $planService->getCompanyPlan($company);
        $selectedPlanId = (int) $request->query('plan', 0);
        if ($selectedPlanId <= 0) {
            $selectedPlanId = ($currentPlan && !$currentPlan->isFreePlan() && !$currentPlan->is_internal)
                ? (int) $currentPlan->id
                : (int) ($paidPlans->first()?->id ?? 0);
        }

        $pendingPayment = $tenantContext->withoutScope(fn () => SubscriptionPayment::where('company_id', $company->id)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->orderByDesc('id')
            ->first());

        $reminder = $reminderService->evaluateCompany($company);
        $useAdminLayout = $company->isOperationallyActive();

        return view('subscription.payments.create', compact(
            'company',
            'effectiveStatus',
            'currentPlan',
            'paidPlans',
            'selectedPlanId',
            'instructions',
            'billingSettings',
            'pendingPayment',
            'reminder',
            'useAdminLayout'
        ));
    }

    public function storePayment(
        Request $request,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac,
        PaymentInstructionService $instructionService,
        SubscriptionPaymentService $paymentService
    ) {
        $company = $this->resolveAuthorizedBillingCompany($request, $tenantContext, $lifecycle, $rbac, true);
        $maxKb = (int) ($instructionService->getBillingSettings()['payment_proof_max_kb'] ?? PaymentInstructionService::DEFAULT_MAX_PROOF_KB);

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')],
            'billing_interval' => ['required', 'string', Rule::in(array_keys(SubscriptionPlan::BILLING_INTERVALS))],
            'payment_instruction_id' => ['required', 'integer', Rule::exists('payment_instructions', 'id')],
            'payment_date' => ['required', 'date', 'before_or_equal:' . now()->addDay()->toDateString()],
            'customer_transfer_reference' => ['nullable', 'string', 'max:120'],
            'payer_name' => ['nullable', 'string', 'max:150'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'replace_existing_pending' => ['nullable', 'boolean'],
            'payment_proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:' . $maxKb],
        ]);

        try {
            $payment = $paymentService->submitCustomerPayment(
                company: $company,
                user: $request->user(),
                input: array_merge($validated, [
                    'replace_existing_pending' => $request->boolean('replace_existing_pending'),
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
            ->route('subscription.payments.show', $payment)
            ->with('success', __('payments.flash.customer_submitted', ['ref' => $payment->payment_reference]));
    }

    public function showPayment(
        Request $request,
        int $paymentId,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac
    ) {
        $company = $this->resolveAuthorizedBillingCompany($request, $tenantContext, $lifecycle, $rbac, false);
        $canManageBilling = $rbac->canManageCompanyBilling($request->user(), (int) $company->id, true);

        $payment = $tenantContext->withoutScope(fn () => SubscriptionPayment::with([
            'subscriptionPlan',
            'previousPlan',
            'paymentInstruction',
            'requestedByUser',
            'renewalRequest',
        ])->find($paymentId));

        if (!$payment || (int) $payment->company_id !== (int) $company->id) {
            abort(403, __('payments.errors.unauthorized_payment'));
        }

        $useAdminLayout = $company->isOperationallyActive();

        return view('subscription.payments.show', compact(
            'company',
            'payment',
            'canManageBilling',
            'useAdminLayout'
        ));
    }

    public function downloadProof(
        Request $request,
        int $paymentId,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac
    ) {
        $company = $this->resolveAuthorizedBillingCompany($request, $tenantContext, $lifecycle, $rbac, false);

        $payment = $tenantContext->withoutScope(fn () => SubscriptionPayment::find($paymentId));
        if (!$payment || (int) $payment->company_id !== (int) $company->id) {
            abort(403, __('payments.errors.unauthorized_payment'));
        }

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

    public function cancelPayment(
        Request $request,
        int $paymentId,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac,
        SubscriptionPaymentService $paymentService
    ) {
        $company = $this->resolveAuthorizedBillingCompany($request, $tenantContext, $lifecycle, $rbac, true);

        $payment = $tenantContext->withoutScope(fn () => SubscriptionPayment::find($paymentId));
        if (!$payment || (int) $payment->company_id !== (int) $company->id) {
            abort(403, __('payments.errors.unauthorized_payment'));
        }

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $paymentService->cancelByCustomer(
                payment: $payment,
                company: $company,
                user: $request->user(),
                reason: $validated['cancellation_reason'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('subscription.payments.show', $payment)
            ->with('success', __('payments.flash.customer_cancelled', ['ref' => $payment->payment_reference]));
    }

    private function resolveAuthorizedBillingCompany(
        Request $request,
        TenantContext $tenantContext,
        CompanyLifecycleService $lifecycle,
        RbacService $rbac,
        bool $requireManage
    ): Company {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        return $tenantContext->withoutScope(function () use ($request, $user, $tenantContext, $rbac, $requireManage): Company {
            $userCompanies = $user->companies()
                ->wherePivot('is_active', 1)
                ->orderByDesc('company_user.is_default')
                ->orderBy('companies.id')
                ->get();

            if ($userCompanies->isEmpty()) {
                abort(403, __('payments.errors.no_company_membership'));
            }

            $statusCompanyId = (int) $request->session()->get('status_company_id', 0);
            $activeCompanyId = (int) $request->session()->get('active_company_id', 0);

            $company = null;
            if ($statusCompanyId > 0) {
                $company = $userCompanies->firstWhere('id', $statusCompanyId);
            }
            if (!$company && $activeCompanyId > 0) {
                $company = $userCompanies->firstWhere('id', $activeCompanyId);
            }
            if (!$company) {
                $company = $userCompanies->first();
            }

            if (!$rbac->canManageCompanyBilling($user, (int) $company->id, $requireManage)) {
                abort(403, __('payments.errors.unauthorized_billing_role'));
            }

            if ($company->isOperationallyActive()) {
                $tenantContext->setCompany((int) $company->id);
            }

            return $company;
        });
    }
}
