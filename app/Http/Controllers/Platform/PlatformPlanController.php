<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionCatalog;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlatformPlanController extends Controller
{
    public function index(TenantContext $tenantContext)
    {
        $plans = $tenantContext->withoutScope(function () {
            return SubscriptionPlan::withCount(['companies', 'features'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        });

        $stats = [
            'totalPlans' => $plans->count(),
            'activePlans' => $plans->where('is_active', true)->count(),
            'internalPlans' => $plans->where('is_internal', true)->count(),
            'recommendedPlan' => $plans->firstWhere('is_recommended', true),
            'assignedCompanies' => (int) $plans->sum('companies_count'),
            'activeFeatures' => SubscriptionFeature::where('is_active', 1)->count(),
        ];

        return view('platform.plans.index', compact('plans', 'stats'));
    }

    public function create()
    {
        $plan = new SubscriptionPlan([
            'tier' => Company::ACCESS_STARTER,
            'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'price' => 0,
            'is_active' => true,
            'is_recommended' => false,
            'is_internal' => false,
            'sort_order' => (int) SubscriptionPlan::max('sort_order') + 1,
        ]);

        $groupedFeatures = $this->groupedFeatures();
        $featureValues = [];
        foreach ($groupedFeatures as $items) {
            foreach ($items as $feature) {
                $featureValues[$feature->id] = $feature->default_value;
            }
        }

        return view('platform.plans.form', [
            'plan' => $plan,
            'isEdit' => false,
            'groupedFeatures' => $groupedFeatures,
            'featureValues' => $featureValues,
            'tiers' => Company::ACCESS_TYPES,
            'intervals' => SubscriptionPlan::BILLING_INTERVALS,
        ]);
    }

    public function store(Request $request, SubscriptionPlanService $planService)
    {
        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code', '')))]);
        }

        $validated = $this->validatePlanRequest($request, null);

        try {
            $plan = $planService->createPlan(
                admin: $this->platformAdmin(),
                attributes: $validated,
                featureInput: (array) $request->input('features', []),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.plans.show', $plan)
            ->with('success', __('platform.flash.plan_created', ['name' => "{$plan->name} ({$plan->code})"]));
    }

    public function show(SubscriptionPlan $plan, TenantContext $tenantContext)
    {
        $plan->load(['features' => fn ($q) => $q->orderBy('category')->orderBy('sort_order')->orderBy('id')]);

        $featuresByCategory = $plan->features->groupBy('category');

        $assignedCompanies = $tenantContext->withoutScope(function () use ($plan) {
            return Company::where('subscription_plan_id', $plan->id)
                ->with(['users' => fn ($q) => $q->wherePivot('role', 'owner')->wherePivot('is_active', 1)])
                ->orderBy('name')
                ->paginate(15);
        });

        return view('platform.plans.show', [
            'plan' => $plan,
            'featuresByCategory' => $featuresByCategory,
            'assignedCompanies' => $assignedCompanies,
            'limitSpecs' => SubscriptionCatalog::MEASURABLE_LIMIT_SPECS,
        ]);
    }

    public function edit(SubscriptionPlan $plan)
    {
        $plan->load('features');
        $groupedFeatures = $this->groupedFeatures();

        $featureValues = [];
        foreach ($plan->features as $feature) {
            $featureValues[$feature->id] = $feature->pivot?->value;
        }
        foreach ($groupedFeatures as $items) {
            foreach ($items as $feature) {
                if (!array_key_exists($feature->id, $featureValues)) {
                    $featureValues[$feature->id] = $feature->default_value;
                }
            }
        }

        return view('platform.plans.form', [
            'plan' => $plan,
            'isEdit' => true,
            'groupedFeatures' => $groupedFeatures,
            'featureValues' => $featureValues,
            'tiers' => Company::ACCESS_TYPES,
            'intervals' => SubscriptionPlan::BILLING_INTERVALS,
        ]);
    }

    public function update(Request $request, SubscriptionPlan $plan, SubscriptionPlanService $planService)
    {
        if ($plan->isCoreSystemPlan()) {
            $merge = ['code' => $plan->code];
            if (array_key_exists(strtoupper((string) $plan->code), Company::ACCESS_TYPES)) {
                $merge['tier'] = $plan->code;
            }
            if ((bool) $plan->is_internal) {
                $merge['is_internal'] = true;
                $merge['is_recommended'] = false;
            }
            $request->merge($merge);
        } elseif ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code', '')))]);
        }

        $validated = $this->validatePlanRequest($request, $plan);

        try {
            $updated = $planService->updatePlan(
                plan: $plan,
                admin: $this->platformAdmin(),
                attributes: $validated,
                featureInput: (array) $request->input('features', []),
                ipAddress: $request->ip()
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.plans.show', $updated)
            ->with('success', __('platform.flash.plan_updated', ['name' => "{$updated->name} ({$updated->code})"]));
    }

    public function activate(Request $request, SubscriptionPlan $plan, SubscriptionPlanService $planService)
    {
        try {
            $planService->setPlanActive($plan, $this->platformAdmin(), true, null, $request->ip());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('platform.flash.plan_activated', ['name' => $plan->name]));
    }

    public function deactivate(Request $request, SubscriptionPlan $plan, SubscriptionPlanService $planService)
    {
        try {
            $planService->setPlanActive($plan, $this->platformAdmin(), false, null, $request->ip());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('platform.flash.plan_deactivated', ['name' => $plan->name]));
    }

    public function destroy(SubscriptionPlan $plan, SubscriptionPlanService $planService)
    {
        try {
            $planService->deletePlan($plan);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.plans.index')
            ->with('success', __('platform.flash.plan_deleted', ['name' => $plan->code]));
    }

    private function validatePlanRequest(Request $request, ?SubscriptionPlan $existing): array
    {
        $codeRule = Rule::unique('subscription_plans', 'code');
        if ($existing !== null) {
            $codeRule->ignore($existing->id);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', $codeRule],
            'tier' => ['required', 'string', Rule::in(array_keys(Company::ACCESS_TYPES))],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'billing_interval' => ['required', 'string', Rule::in(array_keys(SubscriptionPlan::BILLING_INTERVALS))],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'is_recommended' => ['nullable', 'boolean'],
            'is_internal' => ['nullable', 'boolean'],
            'features' => ['nullable', 'array'],
        ], [
            'code.regex' => __('platform.flash.plan_code_regex'),
            'price.min' => __('platform.flash.plan_price_min'),
        ]);

        // Validate individual submitted feature limits and text lengths
        $limitFeatureIds = SubscriptionFeature::where('feature_type', SubscriptionFeature::TYPE_LIMIT)->pluck('id')->all();
        $submitted = (array) $request->input('features', []);
        foreach ($limitFeatureIds as $fId) {
            if (!array_key_exists($fId, $submitted)) {
                continue;
            }
            $raw = $submitted[$fId];
            if ($raw === null || trim((string) $raw) === '') {
                continue;
            }
            if (!is_numeric($raw) || (int) $raw < 0 || (string) (int) $raw !== trim((string) $raw)) {
                $request->validate([
                    "features.{$fId}" => ['nullable', 'integer', 'min:0'],
                ]);
            }
        }

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_internal'] = $request->boolean('is_internal');
        $validated['is_recommended'] = $validated['is_internal'] ? false : $request->boolean('is_recommended');

        return $validated;
    }

    private function groupedFeatures()
    {
        return SubscriptionFeature::orderByRaw("FIELD(category, 'Resource Limits', 'Inventory Management', 'Purchasing', 'Reporting', 'Team & Access Management', 'Advanced Capabilities') ASC")
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('category');
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
