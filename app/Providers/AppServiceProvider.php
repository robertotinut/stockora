<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\LegacyReconciliationAudit;
use App\Models\Product;
use App\Services\RbacService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext());
        $this->app->scoped(RbacService::class, fn ($app) => new RbacService($app->make(TenantContext::class)));
    }

    public function boot(): void
    {
        \App\Support\DatabaseSafetyGuard::enforce();
        \Illuminate\Support\Facades\DB::prohibitDestructiveCommands($this->app->isProduction());
        \App\Support\StockoraRateLimiters::register();

        Paginator::useBootstrapFour();

        View::composer('layouts.admin', function ($view): void {
            $ctx = app(TenantContext::class);
            $user = Auth::user();

            $activeCompany = $ctx->id()
                ? Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])->find($ctx->id())
                : null;
            $planEntitlements = $activeCompany
                ? app(SubscriptionPlanService::class)->getCompanyEntitlements($activeCompany)
                : ['has_plan' => false, 'plan' => null, 'features' => [], 'limits' => []];
            $planFeatures = $planEntitlements['features'] ?? [];
            $userCompanies = $user
                ? $user->companies()
                    ->wherePivot('is_active', 1)
                    ->where('companies.is_active', 1)
                    ->where('companies.account_status', Company::STATUS_ACTIVE)
                    ->where(function ($q): void {
                        $q->whereNull('companies.access_expires_at')
                            ->orWhere('companies.access_expires_at', '>=', now());
                    })
                    ->orderBy('companies.name')
                    ->get()
                : collect();

            $activeCompanyRole = $activeCompany
                ? ($userCompanies->firstWhere('id', $activeCompany->id)?->pivot?->role ?? null)
                : null;

            $navLowStock = $ctx->id()
                ? Product::whereColumn('stock', '<=', 'min_stock')
                    ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
                    ->count()
                : 0;
            $navQuarantinedCount = $ctx->id()
                ? LegacyReconciliationAudit::where('action_taken', 'BLOCKED_NEEDS_REVIEW')->count()
                : 0;

            $view->with(compact(
                'activeCompany',
                'activeCompanyRole',
                'userCompanies',
                'navLowStock',
                'navQuarantinedCount',
                'planEntitlements',
                'planFeatures'
            ));
        });
    }
}
