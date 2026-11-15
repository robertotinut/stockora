<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\PermissionRegistry;
use App\Services\RbacService;
use App\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class TeamPermissionController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, RbacService $rbac)
    {
        $companyId = $tenantContext->requireCompanyId();
        $activeCompany = Company::findOrFail($companyId);

        $selectedRole = strtolower((string) $request->query('role', PermissionRegistry::ROLE_MANAGER));
        if (!PermissionRegistry::isValidRole($selectedRole)) {
            $selectedRole = PermissionRegistry::ROLE_MANAGER;
        }

        $rolesMeta = PermissionRegistry::ROLES;
        $modules = PermissionRegistry::MODULES;
        $actions = PermissionRegistry::ACTIONS;
        $matrix = $rbac->matrixForCompany($companyId);
        $canEditSelected = $rbac->canEditRolePermissions($request->user(), $companyId, $selectedRole);

        return view('team.permissions', compact(
            'activeCompany',
            'selectedRole',
            'rolesMeta',
            'modules',
            'actions',
            'matrix',
            'canEditSelected'
        ));
    }

    public function update(string $role, Request $request, TenantContext $tenantContext, RbacService $rbac)
    {
        $companyId = $tenantContext->requireCompanyId();
        $role = strtolower($role);

        if (!PermissionRegistry::isValidRole($role)) {
            abort(404, 'Unknown role.');
        }

        try {
            $rbac->saveRolePermissions(
                $companyId,
                $role,
                (array) $request->input('permissions', []),
                $request->user()
            );
        } catch (AuthorizationException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 403)
                : abort(403, $e->getMessage());
        }

        $label = PermissionRegistry::ROLES[$role]['label'] ?? strtoupper($role);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('team.flash.permissions_updated', ['role' => $label])]);
        }

        return redirect()
            ->route('team.permissions.index', ['role' => $role])
            ->with('success', __('team.flash.permissions_updated', ['role' => $label]));
    }

    public function reset(string $role, Request $request, TenantContext $tenantContext, RbacService $rbac)
    {
        $companyId = $tenantContext->requireCompanyId();
        $role = strtolower($role);

        if (!PermissionRegistry::isValidRole($role)) {
            abort(404, 'Unknown role.');
        }

        try {
            $rbac->resetRolePermissions($companyId, $role, $request->user());
        } catch (AuthorizationException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 403)
                : abort(403, $e->getMessage());
        }

        $label = PermissionRegistry::ROLES[$role]['label'] ?? strtoupper($role);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('team.flash.permissions_reset', ['role' => $label])]);
        }

        return redirect()
            ->route('team.permissions.index', ['role' => $role])
            ->with('success', __('team.flash.permissions_reset', ['role' => $label]));
    }
}
