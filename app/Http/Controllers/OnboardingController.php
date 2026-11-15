<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyOnboardedException;
use App\Models\Company;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function createCompany(Request $request, CompanyProvisioningService $provisioning)
    {
        $user = $request->user();

        if ($provisioning->hasActiveCompany($user)) {
            if ($request->session()->has('onboarding_ready_company_id')) {
                return redirect()->route('onboarding.ready');
            }

            return redirect()->route('dashboard');
        }

        if ($provisioning->hasAnyWorkspace($user)) {
            return redirect()->route(
                $request->session()->has('onboarding_ready_company_id') ? 'onboarding.ready' : 'company.access-status'
            );
        }

        $industries = Company::INDUSTRIES;

        return view('onboarding.company', compact('industries'));
    }

    public function storeCompany(Request $request, CompanyProvisioningService $provisioning)
    {
        $user = $request->user();

        if ($provisioning->hasActiveCompany($user)) {
            if ($request->session()->has('onboarding_ready_company_id')) {
                return redirect()->route('onboarding.ready');
            }

            return redirect()
                ->route('dashboard')
                ->with('info', __('auth.onboarding_already_active'));
        }

        if ($provisioning->hasAnyWorkspace($user)) {
            return redirect()
                ->route($request->session()->has('onboarding_ready_company_id') ? 'onboarding.ready' : 'company.access-status')
                ->with('info', __('auth.onboarding_already_submitted'));
        }

        if ($request->has('code')) {
            $cleanCode = strtoupper(trim((string) $request->input('code', '')));
            $request->merge([
                'code' => $cleanCode !== '' ? $cleanCode : null,
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('companies', 'code'),
            ],
            'industry' => ['required', 'string', Rule::in(Company::INDUSTRIES)],
            'phone' => ['required', 'string', 'max:50', 'regex:/^[0-9+\-\s().]{6,30}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'code.regex' => __('auth.onboarding_code_regex'),
            'phone.regex' => __('auth.onboarding_phone_regex'),
        ]);

        try {
            $company = $provisioning->createWorkspaceForUser(
                $user,
                $validated,
                true,
                $request->file('logo'),
                Company::STATUS_PENDING_ACTIVATION,
                Company::ACCESS_FREE
            );
        } catch (AlreadyOnboardedException) {
            return redirect()
                ->route($request->session()->has('onboarding_ready_company_id') ? 'onboarding.ready' : 'company.access-status')
                ->with('info', __('auth.onboarding_already_created'));
        }

        $request->session()->put('active_company_id', (int) $company->id);
        $request->session()->put('onboarding_ready_company_id', (int) $company->id);
        $request->session()->put('status_company_id', (int) $company->id);

        return redirect()
            ->route('onboarding.ready')
            ->with('success', __('auth.onboarding_submitted_success', ['company' => $company->name]));
    }

    public function ready(Request $request)
    {
        $user = $request->user();
        $companyId = (int) ($request->session()->get('onboarding_ready_company_id')
            ?: $request->session()->get('active_company_id', 0));

        $company = $companyId > 0
            ? $user->companies()
                ->where('companies.id', $companyId)
                ->wherePivot('is_active', 1)
                ->withCount('warehouses')
                ->first()
            : null;

        if (!$company) {
            return redirect()->route('onboarding.company.create');
        }

        $request->session()->put('active_company_id', (int) $company->id);
        $request->session()->put('status_company_id', (int) $company->id);

        return view('onboarding.ready', [
            'company' => $company,
            'support' => config('stockora.support', []),
        ]);
    }
}
