<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\SubscriptionCatalog;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function __construct(
        private TenantContext $tenantContext,
        private SubscriptionPlanService $planService
    ) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $companyId = $this->tenantContext->id();
        $company = $companyId !== null
            ? Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])->find($companyId)
            : null;

        if ($company === null) {
            abort(403, __('subscription.errors.no_active_workspace'));
        }

        if ($this->planService->hasFeature($company, $featureKey)) {
            return $next($request);
        }

        $plan = $this->planService->getCompanyPlan($company);
        $planName = $plan?->name ?? ($company->access_type ?: 'Free');
        $featureLabel = __("subscription.features.{$featureKey}");
        if ($featureLabel === "subscription.features.{$featureKey}") {
            $meta = collect(SubscriptionCatalog::FEATURES)->firstWhere('feature_key', $featureKey);
            $featureLabel = is_array($meta) && isset($meta['name']) ? $meta['name'] : $featureKey;
        }

        $message = __('subscription.feature_locked_message', [
            'feature' => $featureLabel,
            'plan' => $planName,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'code' => 'FEATURE_LOCKED',
                'feature' => $featureKey,
                'plan' => $planName,
                'message' => $message,
                'error' => $message,
            ], 403);
        }

        return response()->view('errors.feature-locked', [
            'company' => $company,
            'plan' => $plan,
            'planName' => $planName,
            'featureKey' => $featureKey,
            'featureLabel' => $featureLabel,
            'message' => $message,
            'support' => config('stockora.support', []),
        ], 403);
    }
}
