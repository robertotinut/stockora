<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionRenewalRequest;
use App\Models\Warehouse;
use App\Services\CompanyLifecycleService;
use App\Services\PlatformOnboardingService;
use App\Services\RenewalReminderService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlatformCustomerController extends Controller
{
    public function index(Request $request, CompanyLifecycleService $lifecycle, TenantContext $tenantContext)
    {
        return $this->renderCustomerListing($request, $lifecycle, $tenantContext, 'directory');
    }

    public function pending(Request $request, CompanyLifecycleService $lifecycle, TenantContext $tenantContext)
    {
        $request->merge(['status' => Company::STATUS_PENDING_ACTIVATION]);

        return $this->renderCustomerListing($request, $lifecycle, $tenantContext, 'pending');
    }

    public function accessIndex(Request $request, CompanyLifecycleService $lifecycle, TenantContext $tenantContext)
    {
        return $this->renderCustomerListing($request, $lifecycle, $tenantContext, 'access');
    }

    private function renderCustomerListing(
        Request $request,
        CompanyLifecycleService $lifecycle,
        TenantContext $tenantContext,
        string $mode
    ) {
        $lifecycle->syncExpiredCompanies();

        $search = trim((string) $request->query('search', ''));
        $status = strtoupper(trim((string) $request->query('status', '')));
        $accessType = strtoupper(trim((string) $request->query('access_type', '')));
        $expiringSoon = $request->boolean('expiring_soon');

        $companies = $tenantContext->withoutScope(function () use ($search, $status, $accessType, $expiringSoon, $mode) {
            $query = Company::query()
                ->with([
                    'subscriptionPlan',
                    'users' => fn ($q) => $q->wherePivot('role', 'owner')->wherePivot('is_active', 1),
                ])
                ->withCount([
                    'users as active_members_count' => fn ($q) => $q->where('company_user.is_active', 1),
                    'warehouses',
                ]);

            if ($status !== '' && array_key_exists($status, Company::ACCOUNT_STATUSES)) {
                $query->where('account_status', $status);
            }

            if ($accessType !== '' && array_key_exists($accessType, Company::ACCESS_TYPES)) {
                $query->where('access_type', $accessType);
            }

            if ($expiringSoon) {
                $query->where('account_status', Company::STATUS_ACTIVE)
                    ->whereNotNull('access_expires_at')
                    ->where('access_expires_at', '<=', now()->addDays(14));
            }

            if ($search !== '') {
                $query->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('users', function ($uq) use ($search): void {
                            $uq->where('users.name', 'like', "%{$search}%")
                                ->orWhere('users.email', 'like', "%{$search}%");
                        });
                });
            }

            if ($mode === 'pending') {
                $query->orderBy('created_at');
            } elseif ($mode === 'access') {
                $query->orderByRaw('CASE WHEN access_expires_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('access_expires_at')
                    ->orderByDesc('created_at');
            } else {
                $query->orderByDesc('created_at')->orderByDesc('id');
            }

            return $query->paginate(15)->withQueryString();
        });

        $counts = Company::selectRaw('account_status, COUNT(*) AS total')
            ->groupBy('account_status')
            ->pluck('total', 'account_status');

        return view('platform.customers.index', [
            'mode' => $mode,
            'companies' => $companies,
            'search' => $search,
            'status' => $status,
            'accessType' => $accessType,
            'expiringSoon' => $expiringSoon,
            'counts' => $counts,
            'statuses' => Company::ACCOUNT_STATUSES,
            'accessTypes' => Company::ACCESS_TYPES,
        ]);
    }

    public function create()
    {
        return view('platform.customers.create', [
            'industries' => Company::INDUSTRIES,
            'accessTypes' => Company::ACCESS_TYPES,
            'support' => config('stockora.support', []),
        ]);
    }

    public function store(Request $request, PlatformOnboardingService $onboarding)
    {
        if ($request->has('company_code')) {
            $cleanCode = strtoupper(trim((string) $request->input('company_code', '')));
            $request->merge(['company_code' => $cleanCode !== '' ? $cleanCode : null]);
        }
        $request->merge([
            'owner_email' => strtolower(trim((string) $request->input('owner_email', ''))),
        ]);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_code' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('companies', 'code'),
            ],
            'industry' => ['required', 'string', Rule::in(Company::INDUSTRIES)],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'confirm_existing_owner' => ['nullable', 'boolean'],
            'access_type' => ['required', 'string', Rule::in(array_keys(Company::ACCESS_TYPES))],
            'initial_status' => ['required', 'string', Rule::in([Company::STATUS_ACTIVE, Company::STATUS_PENDING_ACTIVATION])],
            'access_starts_at' => ['nullable', 'date'],
            'access_expires_at' => ['nullable', 'date'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'company_code.regex' => __('auth.onboarding_code_regex'),
            'phone.regex' => __('auth.onboarding_phone_regex'),
        ]);

        $startsAt = !empty($validated['access_starts_at'])
            ? Carbon::parse($validated['access_starts_at'])->startOfDay()
            : now();
        $expiresAt = !empty($validated['access_expires_at'])
            ? Carbon::parse($validated['access_expires_at'])->endOfDay()
            : null;

        try {
            $result = $onboarding->createCustomerDirect(
                admin: $this->platformAdmin(),
                companyAttributes: [
                    'name' => $validated['company_name'],
                    'code' => $validated['company_code'] ?? null,
                    'industry' => $validated['industry'],
                    'phone' => $validated['phone'],
                    'address' => $validated['address'],
                ],
                ownerAttributes: [
                    'name' => $validated['owner_name'],
                    'email' => $validated['owner_email'],
                ],
                accessType: $validated['access_type'],
                initialStatus: $validated['initial_status'],
                startsAt: $startsAt,
                expiresAt: $expiresAt,
                adminNotes: $validated['admin_notes'] ?? null,
                confirmExistingOwner: $request->boolean('confirm_existing_owner'),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return back()
                ->withInput()
                ->withErrors(['owner_email' => $e->getMessage()]);
        }

        $redirect = redirect()
            ->route('platform.customers.show', $result['company'])
            ->with('success', __('platform.flash.customer_created', [
                'name' => "{$result['company']->name} ({$result['company']->code})",
                'status' => $result['company']->account_status,
            ]));

        if (!app()->isProduction() && !$result['mail_dispatched'] && $result['setup_url']) {
            $redirect->with('sandbox_setup_preview', [
                'email' => $result['owner']->email,
                'setup_url' => $result['setup_url'],
            ]);
        }

        return $redirect;
    }

    public function show(
        Company $company,
        CompanyLifecycleService $lifecycle,
        TenantContext $tenantContext,
        SubscriptionPlanService $planService
    ) {
        $lifecycle->determineBlockReason($company);
        $company->refresh();
        $company->load(['activatedByAdmin', 'suspendedByAdmin', 'subscriptionPlan']);

        $effectiveStatus = $company->effectiveStatus();
        $entitlements = $planService->getCompanyEntitlements($company);
        $usageSummary = $planService->getUsageSummary($company);
        $resolvedPlan = $planService->getCompanyPlan($company);
        $activePlans = SubscriptionPlan::where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $summary = $tenantContext->withoutScope(function () use ($company): array {
            $members = $company->users()
                ->wherePivot('is_active', 1)
                ->orderByRaw("FIELD(company_user.role, 'owner', 'admin', 'manager', 'staff', 'viewer')")
                ->orderBy('users.name')
                ->get();

            return [
                'members' => $members,
                'inactiveMembersCount' => $company->users()->wherePivot('is_active', 0)->count(),
                'warehousesCount' => Warehouse::where('company_id', $company->id)->count(),
                'productsCount' => Product::where('company_id', $company->id)->count(),
                'purchasesCount' => Purchase::where('company_id', $company->id)->count(),
                'movementsCount' => StockMovement::where('company_id', $company->id)->count(),
                'pendingInvitesCount' => CompanyInvitation::where('company_id', $company->id)
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->count(),
            ];
        });

        $auditLogs = PlatformAuditLog::where('company_id', $company->id)
            ->with(['platformAdmin', 'actorUser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $payments = SubscriptionPayment::where('company_id', $company->id)
            ->with(['subscriptionPlan', 'verifiedByAdmin', 'appliedByAdmin', 'renewalRequest'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $renewalRequests = SubscriptionRenewalRequest::where('company_id', $company->id)
            ->with(['requestedPlan', 'payment'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $billingReminder = app(RenewalReminderService::class)->evaluateCompany($company);

        return view('platform.customers.show', [
            'company' => $company,
            'effectiveStatus' => $effectiveStatus,
            'entitlements' => $entitlements,
            'usageSummary' => $usageSummary,
            'resolvedPlan' => $resolvedPlan,
            'activePlans' => $activePlans,
            'summary' => $summary,
            'auditLogs' => $auditLogs,
            'payments' => $payments,
            'renewalRequests' => $renewalRequests,
            'billingReminder' => $billingReminder,
            'statuses' => Company::ACCOUNT_STATUSES,
            'accessTypes' => Company::ACCESS_TYPES,
            'industries' => Company::INDUSTRIES,
            'allowedTransitions' => CompanyLifecycleService::ALLOWED_TRANSITIONS[$effectiveStatus] ?? [],
        ]);
    }

    public function updateProfile(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        if ($request->has('code')) {
            $request->merge([
                'code' => strtoupper(trim((string) $request->input('code', ''))),
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('companies', 'code')->ignore($company->id)],
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', Rule::in(Company::INDUSTRIES)],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $lifecycle->updateProfileAndNotes(
            $company,
            $this->platformAdmin(),
            $validated,
            $request->ip()
        );

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.profile_updated'));
    }

    public function activate(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'access_type' => ['required', 'string', Rule::in(array_keys(Company::ACCESS_TYPES))],
            'access_starts_at' => ['nullable', 'date'],
            'access_expires_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $startsAt = !empty($validated['access_starts_at'])
            ? Carbon::parse($validated['access_starts_at'])->startOfDay()
            : now();
        $expiresAt = !empty($validated['access_expires_at'])
            ? Carbon::parse($validated['access_expires_at'])->endOfDay()
            : null;

        try {
            $lifecycle->activate(
                company: $company,
                admin: $this->platformAdmin(),
                accessType: $validated['access_type'],
                startsAt: $startsAt,
                expiresAt: $expiresAt,
                note: $validated['note'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.activated', ['name' => $company->name]));
    }

    public function suspend(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $lifecycle->suspend(
                company: $company,
                admin: $this->platformAdmin(),
                reason: $validated['reason'],
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.suspended', ['name' => $company->name]));
    }

    public function reactivate(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'access_type' => ['nullable', 'string', Rule::in(array_keys(Company::ACCESS_TYPES))],
            'access_expires_at' => ['nullable', 'date'],
            'clear_expiration' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $expiresAt = !empty($validated['access_expires_at'])
            ? Carbon::parse($validated['access_expires_at'])->endOfDay()
            : null;

        try {
            $lifecycle->reactivate(
                company: $company,
                admin: $this->platformAdmin(),
                accessType: $validated['access_type'] ?? null,
                expiresAt: $expiresAt,
                clearExpiration: $request->boolean('clear_expiration'),
                reason: $validated['reason'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.reactivated', ['name' => $company->name]));
    }

    public function expire(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $lifecycle->expireNow(
                company: $company,
                admin: $this->platformAdmin(),
                reason: $validated['reason'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.expired', ['name' => $company->name]));
    }

    public function cancel(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $lifecycle->cancel(
                company: $company,
                admin: $this->platformAdmin(),
                reason: $validated['reason'],
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.cancelled', ['name' => $company->name]));
    }

    public function extendExpiration(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'access_expires_at' => ['nullable', 'date', 'required_without:clear_expiration'],
            'clear_expiration' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $newExpiresAt = !empty($validated['access_expires_at'])
            ? Carbon::parse($validated['access_expires_at'])->endOfDay()
            : null;

        try {
            $lifecycle->extendExpiration(
                company: $company,
                admin: $this->platformAdmin(),
                newExpiresAt: $newExpiresAt,
                clearExpiration: $request->boolean('clear_expiration'),
                reactivateIfExpired: true,
                note: $validated['note'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.extended', ['name' => $company->name]));
    }

    public function changeAccessType(Request $request, Company $company, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'access_type' => ['required', 'string', Rule::in(array_keys(Company::ACCESS_TYPES))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $lifecycle->updateAccessType(
                company: $company,
                admin: $this->platformAdmin(),
                accessType: $validated['access_type'],
                note: $validated['note'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.access_type_changed', ['tier' => $validated['access_type']]));
    }

    public function assignPlan(Request $request, Company $company, SubscriptionPlanService $planService)
    {
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'integer', Rule::exists('subscription_plans', 'id')],
            'access_expires_at' => ['nullable', 'date'],
            'clear_expiration' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $plan = SubscriptionPlan::findOrFail((int) $validated['subscription_plan_id']);

        $expiresAt = !empty($validated['access_expires_at'])
            ? Carbon::parse($validated['access_expires_at'])->endOfDay()
            : null;

        $adminNotesArg = $request->has('admin_notes') ? ($validated['admin_notes'] ?? '') : null;

        try {
            $planService->assignPlanToCompany(
                company: $company,
                plan: $plan,
                admin: $this->platformAdmin(),
                expiresAt: $expiresAt,
                clearExpiration: $request->boolean('clear_expiration'),
                adminNotes: $adminNotesArg,
                note: $validated['note'] ?? null,
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return redirect()->route('platform.customers.show', $company)->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.customers.show', $company)
            ->with('success', __('platform.flash.plan_assigned', ['plan' => $plan->name, 'code' => $plan->code, 'company' => $company->name]));
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
