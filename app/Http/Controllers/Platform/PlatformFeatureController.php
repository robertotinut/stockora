<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionFeature;
use App\Services\SubscriptionCatalog;
use App\Services\SubscriptionPlanService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PlatformFeatureController extends Controller
{
    public function index(Request $request)
    {
        $category = trim((string) $request->query('category', ''));
        $type = strtoupper(trim((string) $request->query('type', '')));
        $status = trim((string) $request->query('status', ''));

        $features = SubscriptionFeature::with(['plans' => fn ($q) => $q->orderBy('sort_order')])
            ->when($category !== '', fn ($q) => $q->where('category', $category))
            ->when($type !== '' && array_key_exists($type, SubscriptionFeature::TYPES), fn ($q) => $q->where('feature_type', $type))
            ->when($status === 'active', fn ($q) => $q->where('is_active', 1))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', 0))
            ->orderByRaw("FIELD(category, 'Inventory Management', 'Purchasing', 'Reporting', 'Team & Access Management', 'Resource Limits', 'Advanced Capabilities') ASC")
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groupedFeatures = $features->groupBy('category');
        $allCategories = SubscriptionFeature::select('category')
            ->distinct()
            ->pluck('category')
            ->merge(SubscriptionFeature::CATEGORIES)
            ->unique()
            ->values();

        return view('platform.features.index', [
            'groupedFeatures' => $groupedFeatures,
            'featuresCount' => $features->count(),
            'activeCount' => SubscriptionFeature::where('is_active', 1)->count(),
            'limitCount' => SubscriptionFeature::where('feature_type', SubscriptionFeature::TYPE_LIMIT)->count(),
            'category' => $category,
            'type' => $type,
            'status' => $status,
            'categories' => $allCategories,
            'types' => SubscriptionFeature::TYPES,
            'limitSpecs' => SubscriptionCatalog::MEASURABLE_LIMIT_SPECS,
            'unimplementedNotes' => SubscriptionCatalog::UNIMPLEMENTED_LIMIT_NOTES,
        ]);
    }

    public function store(Request $request, SubscriptionPlanService $planService)
    {
        if ($request->has('feature_key')) {
            $request->merge(['feature_key' => strtolower(trim((string) $request->input('feature_key', '')))]);
        }

        $validated = $request->validate([
            'feature_key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/', Rule::unique('subscription_features', 'feature_key')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'string', 'max:64'],
            'feature_type' => ['required', 'string', Rule::in(array_keys(SubscriptionFeature::TYPES))],
            'default_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'feature_key.regex' => __('platform.flash.feature_key_regex'),
        ]);

        if ($validated['feature_type'] === SubscriptionFeature::TYPE_LIMIT
            && isset($validated['default_value'])
            && trim((string) $validated['default_value']) !== ''
            && (!is_numeric($validated['default_value']) || (int) $validated['default_value'] < 0)
        ) {
            return back()->withInput()->with('error', __('platform.flash.feature_limit_nonneg'));
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        try {
            $feature = $planService->createFeature($this->platformAdmin(), $validated, $request->ip());
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.features.index')
            ->with('success', __('platform.flash.feature_created', ['name' => $feature->feature_key]));
    }

    public function update(Request $request, SubscriptionFeature $feature, SubscriptionPlanService $planService)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'string', 'max:64'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        if ($feature->feature_type === SubscriptionFeature::TYPE_LIMIT
            && isset($validated['default_value'])
            && trim((string) $validated['default_value']) !== ''
            && (!is_numeric($validated['default_value']) || (int) $validated['default_value'] < 0)
        ) {
            return back()->with('error', __('platform.flash.feature_limit_nonneg'));
        }

        try {
            $planService->updateFeature($feature, $this->platformAdmin(), $validated, $request->ip());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.features.index')
            ->with('success', __('platform.flash.feature_updated', ['name' => $feature->feature_key]));
    }

    public function activate(Request $request, SubscriptionFeature $feature, SubscriptionPlanService $planService)
    {
        try {
            $planService->setFeatureActive($feature, $this->platformAdmin(), true, $request->ip());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('platform.flash.feature_activated', ['name' => $feature->feature_key]));
    }

    public function deactivate(Request $request, SubscriptionFeature $feature, SubscriptionPlanService $planService)
    {
        try {
            $planService->setFeatureActive($feature, $this->platformAdmin(), false, $request->ip());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('platform.flash.feature_deactivated', ['name' => $feature->feature_key]));
    }

    public function destroy(SubscriptionFeature $feature, SubscriptionPlanService $planService)
    {
        try {
            $planService->deleteFeature($feature);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('platform.features.index')
            ->with('success', __('platform.flash.feature_deleted', ['name' => $feature->feature_key]));
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
