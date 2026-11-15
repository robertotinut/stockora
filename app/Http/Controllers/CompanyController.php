<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Services\CompanyLifecycleService;
use App\Services\CompanyProvisioningService;
use App\Services\RbacService;
use App\Services\RenewalReminderService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class CompanyController extends Controller
{
    public function select(Request $request, CompanyLifecycleService $lifecycle)
    {
        $allCompanies = $request->user()
            ->companies()
            ->wherePivot('is_active', 1)
            ->orderByDesc('company_user.is_default')
            ->orderBy('companies.name')
            ->get();

        foreach ($allCompanies as $item) {
            $lifecycle->determineBlockReason($item);
            $item->refresh();
        }

        $companies = $allCompanies->filter(fn (Company $c) => $c->isOperationallyActive())->values();
        $blockedCompanies = $allCompanies->reject(fn (Company $c) => $c->isOperationallyActive())->values();
        $activeCompanyId = (int) $request->session()->get('active_company_id', 0);

        return view('auth.select-company', compact('companies', 'blockedCompanies', 'activeCompanyId'));
    }

    public function switchCompany(Request $request, CompanyLifecycleService $lifecycle)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'min:1'],
        ]);

        $company = $request->user()
            ->companies()
            ->where('companies.id', (int) $validated['company_id'])
            ->wherePivot('is_active', 1)
            ->first();

        if (!$company) {
            return back()->with('error', __('operations.flash.company_no_access'));
        }

        $blockReason = $lifecycle->determineBlockReason($company);
        if ($blockReason !== null) {
            return back()->with('error', __('operations.flash.company_blocked', ['name' => $company->name, 'status' => $company->effectiveStatus()]));
        }

        $request->session()->put('active_company_id', (int) $company->id);
        $request->session()->forget('status_company_id');

        return redirect()->route('dashboard')->with('success', __('operations.flash.company_switched', ['name' => $company->name]));
    }

    public function accessStatus(
        Request $request,
        CompanyLifecycleService $lifecycle,
        TenantContext $tenantContext,
        RbacService $rbac,
        SubscriptionPlanService $planService,
        RenewalReminderService $reminderService
    )
    {
        $user = $request->user();
        $candidateId = (int) ($request->session()->get('status_company_id')
            ?: $request->session()->get('active_company_id', 0));

        $userCompanies = $user->companies()
            ->wherePivot('is_active', 1)
            ->orderByDesc('company_user.is_default')
            ->orderBy('companies.id')
            ->get();

        if ($userCompanies->isEmpty()) {
            $hasInactiveMembership = $user->companies()->exists();

            return redirect()->route($hasInactiveMembership ? 'company.select' : 'onboarding.company.create');
        }

        $company = $userCompanies->firstWhere('id', $candidateId) ?? $userCompanies->first();

        $blockReason = $lifecycle->determineBlockReason($company);
        $company->refresh();

        if ($blockReason === null) {
            $request->session()->put('active_company_id', (int) $company->id);
            $request->session()->forget(['status_company_id', 'onboarding_ready_company_id']);

            return redirect()
                ->route('dashboard')
                ->with('success', __('operations.flash.company_now_active', ['name' => $company->name]));
        }

        $activeWorkspaces = $userCompanies->filter(function (Company $c) use ($lifecycle) {
            return $lifecycle->determineBlockReason($c) === null;
        })->values();

        $request->session()->put('status_company_id', (int) $company->id);

        $resolvedPlan = $planService->getCompanyPlan($company);
        $billingReminder = $reminderService->evaluateCompany($company);
        $canManageBilling = $rbac->canManageCompanyBilling($user, (int) $company->id, true);
        $latestPayment = $tenantContext->withoutScope(fn () => SubscriptionPayment::where('company_id', $company->id)
            ->orderByDesc('id')
            ->first());

        return view('company.access-status', [
            'company' => $company,
            'effectiveStatus' => $company->effectiveStatus(),
            'blockReason' => $blockReason,
            'activeWorkspaces' => $activeWorkspaces,
            'resolvedPlan' => $resolvedPlan,
            'billingReminder' => $billingReminder,
            'canManageBilling' => $canManageBilling,
            'latestPayment' => $latestPayment,
            'support' => config('stockora.support', []),
        ]);
    }

    public function index(Request $request, TenantContext $tenantContext)
    {
        $search = trim((string) $request->query('search', ''));
        $activeCompanyId = $tenantContext->requireCompanyId();

        $companies = $request->user()
            ->companies()
            ->wherePivot('is_active', 1)
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('companies.code', 'like', "%{$search}%")
                        ->orWhere('companies.name', 'like', "%{$search}%");
                });
            })
            ->withCount('warehouses')
            ->orderByDesc('company_user.is_default')
            ->orderBy('companies.name')
            ->paginate(10)
            ->withQueryString();

        return view('companies.index', compact('companies', 'search', 'activeCompanyId'));
    }

    public function create()
    {
        $company = new Company(['is_active' => true]);
        $industries = Company::INDUSTRIES;

        return view('companies.form', compact('company', 'industries'));
    }

    public function store(Request $request, CompanyProvisioningService $provisioning)
    {
        if ($request->has('code')) {
            $request->merge([
                'code' => strtoupper(trim((string) $request->input('code', ''))),
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('companies', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', Rule::in(Company::INDUSTRIES)],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $company = $provisioning->createWorkspaceForUser(
            $request->user(),
            $validated,
            false,
            $request->file('logo'),
            Company::STATUS_ACTIVE,
            Company::ACCESS_PROFESSIONAL
        );

        $request->session()->put('active_company_id', (int) $company->id);

        return redirect()->route('companies.index')->with('success', __('operations.flash.company_created', ['name' => $company->name]));
    }

    public function edit(Request $request, Company $company)
    {
        $this->authorizeOwnerOrAdmin($request, $company);
        $industries = Company::INDUSTRIES;

        return view('companies.form', compact('company', 'industries'));
    }

    public function update(Request $request, Company $company)
    {
        $this->authorizeOwnerOrAdmin($request, $company);

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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldLogoPath = $company->logo_path;
        $newLogoPath = null;
        $removeLogo = $request->boolean('remove_logo');

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->store('company-logos', 'public');
        }

        try {
            $company->update([
                'code' => strtoupper($validated['code']),
                'name' => trim($validated['name']),
                'industry' => $validated['industry'] ?? null,
                'phone' => isset($validated['phone']) && trim((string) $validated['phone']) !== ''
                    ? trim((string) $validated['phone'])
                    : null,
                'address' => isset($validated['address']) && trim((string) $validated['address']) !== ''
                    ? trim((string) $validated['address'])
                    : null,
                'logo_path' => $newLogoPath ?? ($removeLogo ? null : $oldLogoPath),
                // Tenant admins cannot reactivate platform-suspended/pending/expired companies via profile edit.
                'is_active' => $company->account_status === Company::STATUS_ACTIVE
                    ? $request->boolean('is_active')
                    : false,
            ]);
        } catch (Throwable $e) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $e;
        }

        if ($oldLogoPath && ($newLogoPath !== null || $removeLogo)) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return redirect()->route('companies.index')->with('success', __('operations.flash.company_updated', ['name' => $company->name]));
    }

    private function authorizeOwnerOrAdmin(Request $request, Company $company): void
    {
        if (!app(RbacService::class)->canAccessModule($request->user(), (int) $company->id, 'company_settings', 'edit')) {
            abort(403, 'You do not have permission to edit company settings for this workspace.');
        }
    }
}
