<?php

use App\Services\CompanyProvisioningService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\TrustProxies::class
        );
        $middleware->web(append: [
            \App\Http\Middleware\SetApplicationLocale::class,
        ]);
        $middleware->alias([
            'tenant' => \App\Http\Middleware\EnsureActiveCompany::class,
            'feature' => \App\Http\Middleware\EnsurePlanFeature::class,
            'perm' => \App\Http\Middleware\AuthorizeModulePermission::class,
            'platform.admin' => \App\Http\Middleware\PlatformAdminMiddleware::class,
        ]);
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsureActiveCompany::class
        );
        $middleware->prependToPriorityList(
            \App\Http\Middleware\EnsureActiveCompany::class,
            \App\Http\Middleware\SetApplicationLocale::class
        );
        $middleware->appendToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\EnsurePlanFeature::class
        );
        $middleware->appendToPriorityList(
            \App\Http\Middleware\EnsurePlanFeature::class,
            \App\Http\Middleware\AuthorizeModulePermission::class
        );
        $middleware->redirectUsersTo(function (Request $request): string {
            $user = $request->user();
            if ($user instanceof \App\Models\PlatformAdmin) {
                return route('platform.dashboard');
            }

            if ($user instanceof \App\Models\User) {
                $provisioning = app(CompanyProvisioningService::class);
                if (!$provisioning->hasAnyWorkspace($user)) {
                    return route('onboarding.company.create');
                }
                if (!$provisioning->hasActiveCompany($user)) {
                    return route('company.access-status');
                }
            }

            if ($user instanceof \App\Models\User && !$request->session()->has('active_company_id')) {
                $activeCount = $user->companies()
                    ->wherePivot('is_active', 1)
                    ->where('companies.is_active', 1)
                    ->where('companies.account_status', \App\Models\Company::STATUS_ACTIVE)
                    ->where(function ($q): void {
                        $q->whereNull('companies.access_expires_at')
                            ->orWhere('companies.access_expires_at', '>=', now());
                    })
                    ->count();

                if ($activeCount > 1) {
                    return route('company.select');
                }
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
