<?php

namespace App\Http\Middleware;

use App\Services\PermissionRegistry;
use App\Services\RbacService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeModulePermission
{
    public function __construct(
        private TenantContext $tenantContext,
        private RbacService $rbac
    ) {}

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        $user = $request->user();
        $companyId = $this->tenantContext->id();

        if (!$this->rbac->canAccessModule($user, $companyId, $module, $action)) {
            $moduleLabel = PermissionRegistry::MODULES[$module]['label'] ?? ucfirst(str_replace('_', ' ', $module));
            $message = "You do not have permission to {$action} {$moduleLabel} in this company workspace.";

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'module' => $module,
                    'action' => $action,
                ], 403);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
