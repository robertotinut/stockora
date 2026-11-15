<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RbacService
{
    /** @var array<string, ?object> */
    private array $membershipCache = [];

    /** @var array<string, array<string, bool>> */
    private array $overrideCache = [];

    public function __construct(private TenantContext $tenantContext) {}

    public function clearCache(): void
    {
        $this->membershipCache = [];
        $this->overrideCache = [];
    }

    public function membershipFor(User $user, int $companyId): ?object
    {
        if ($companyId <= 0) {
            return null;
        }

        $key = "{$user->id}:{$companyId}";
        if (array_key_exists($key, $this->membershipCache)) {
            return $this->membershipCache[$key];
        }

        $row = DB::selectOne(
            'SELECT cu.id, cu.company_id, cu.user_id, LOWER(cu.role) AS role, cu.is_default, cu.is_active
             FROM company_user cu
             JOIN companies c ON c.id = cu.company_id
             WHERE cu.company_id = ? AND cu.user_id = ? AND cu.is_active = 1 AND c.is_active = 1
             LIMIT 1',
            [$companyId, (int) $user->id]
        );

        return $this->membershipCache[$key] = $row ?: null;
    }

    /**
     * Resolve active user membership regardless of company activation status
     * (used for billing / payment / access-status flows on PENDING_ACTIVATION or EXPIRED companies).
     */
    public function billingMembershipFor(User $user, int $companyId): ?object
    {
        if ($companyId <= 0) {
            return null;
        }

        $row = DB::selectOne(
            'SELECT cu.id, cu.company_id, cu.user_id, LOWER(cu.role) AS role, cu.is_default, cu.is_active
             FROM company_user cu
             JOIN companies c ON c.id = cu.company_id
             WHERE cu.company_id = ? AND cu.user_id = ? AND cu.is_active = 1
             LIMIT 1',
            [$companyId, (int) $user->id]
        );

        return $row ?: null;
    }

    public function canManageCompanyBilling(?User $user, ?int $companyId, bool $requireManage = false): bool
    {
        if (!$user || !$companyId || $companyId <= 0) {
            return false;
        }

        $membership = $this->billingMembershipFor($user, $companyId);
        if (!$membership) {
            return false;
        }

        $role = strtolower((string) $membership->role);
        if ($role === PermissionRegistry::ROLE_OWNER) {
            return true;
        }

        $action = $requireManage ? 'manage' : 'view';
        if (!PermissionRegistry::isValidRole($role)) {
            return false;
        }

        $overrides = $this->overridesFor($companyId, $role);
        $permKey = "company_settings:{$action}";

        if (array_key_exists($permKey, $overrides)) {
            return $overrides[$permKey];
        }

        return PermissionRegistry::defaultAllows($role, 'company_settings', $action);
    }

    public function roleFor(User $user, int $companyId): ?string
    {
        return $this->membershipFor($user, $companyId)?->role ?? null;
    }

    public function isOwner(User $user, int $companyId): bool
    {
        return $this->roleFor($user, $companyId) === PermissionRegistry::ROLE_OWNER;
    }

    public function canAccessModule(?User $user, ?int $companyId, string $module, string $action = 'view'): bool
    {
        if (!$user || !$companyId || $companyId <= 0) {
            return false;
        }

        if (!PermissionRegistry::isValidPermission($module, $action)) {
            return false;
        }

        $role = $this->roleFor($user, $companyId);
        if (!$role || !PermissionRegistry::isValidRole($role)) {
            return false;
        }

        if ($role === PermissionRegistry::ROLE_OWNER) {
            return true;
        }

        $overrides = $this->overridesFor($companyId, $role);
        $permKey = "{$module}:{$action}";

        if (array_key_exists($permKey, $overrides)) {
            return $overrides[$permKey];
        }

        return PermissionRegistry::defaultAllows($role, $module, $action);
    }

    public function canEditRolePermissions(User $actor, int $companyId, string $targetRole): bool
    {
        $targetRole = strtolower($targetRole);
        if (!in_array($targetRole, PermissionRegistry::editablePermissionRoles(), true)) {
            return false;
        }

        if (!$this->canAccessModule($actor, $companyId, 'team_management', 'manage')) {
            return false;
        }

        $actorRole = $this->roleFor($actor, $companyId);
        if ($actorRole === PermissionRegistry::ROLE_OWNER) {
            return true;
        }

        // Non-owners (e.g. ADMIN) can customize operational roles only; never ADMIN itself or OWNER.
        return in_array($targetRole, [
            PermissionRegistry::ROLE_MANAGER,
            PermissionRegistry::ROLE_STAFF,
            PermissionRegistry::ROLE_VIEWER,
        ], true);
    }

    public function effectivePermissionsForRole(int $companyId, string $role): array
    {
        $role = strtolower($role);
        $overrides = $role === PermissionRegistry::ROLE_OWNER ? [] : $this->overridesFor($companyId, $role);
        $result = [];

        foreach (PermissionRegistry::MODULES as $module => $meta) {
            foreach ($meta['actions'] as $action) {
                $default = PermissionRegistry::defaultAllows($role, $module, $action);
                $permKey = "{$module}:{$action}";
                $hasOverride = array_key_exists($permKey, $overrides);
                $allowed = $role === PermissionRegistry::ROLE_OWNER
                    ? true
                    : ($hasOverride ? $overrides[$permKey] : $default);

                $result[$module][$action] = [
                    'allowed' => $allowed,
                    'default' => $default,
                    'overridden' => $hasOverride && ($overrides[$permKey] !== $default),
                ];
            }
        }

        return $result;
    }

    public function matrixForCompany(int $companyId): array
    {
        $matrix = [];
        foreach (PermissionRegistry::roles() as $role) {
            $matrix[$role] = $this->effectivePermissionsForRole($companyId, $role);
        }

        return $matrix;
    }

    public function saveRolePermissions(int $companyId, string $role, array $submittedPermissions, User $actor): void
    {
        $role = strtolower($role);
        if (!$this->canEditRolePermissions($actor, $companyId, $role)) {
            throw new AuthorizationException('You are not authorized to modify permissions for this role.');
        }

        DB::transaction(function () use ($companyId, $role, $submittedPermissions, $actor): void {
            $now = now();
            foreach (PermissionRegistry::MODULES as $module => $meta) {
                $submittedActions = $submittedPermissions[$module] ?? [];
                foreach ($meta['actions'] as $action) {
                    $isAllowed = !empty($submittedActions[$action]);
                    $defaultAllowed = PermissionRegistry::defaultAllows($role, $module, $action);

                    if ($isAllowed === $defaultAllowed) {
                        DB::table('company_role_permissions')
                            ->where('company_id', $companyId)
                            ->where('role', $role)
                            ->where('module', $module)
                            ->where('action', $action)
                            ->delete();
                    } else {
                        DB::table('company_role_permissions')->updateOrInsert(
                            [
                                'company_id' => $companyId,
                                'role' => $role,
                                'module' => $module,
                                'action' => $action,
                            ],
                            [
                                'is_allowed' => $isAllowed ? 1 : 0,
                                'updated_by_user_id' => $actor->id,
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
                    }
                }
            }
        });

        $this->clearCache();
    }

    public function resetRolePermissions(int $companyId, string $role, User $actor): void
    {
        $role = strtolower($role);
        if (!$this->canEditRolePermissions($actor, $companyId, $role)) {
            throw new AuthorizationException('You are not authorized to reset permissions for this role.');
        }

        DB::table('company_role_permissions')
            ->where('company_id', $companyId)
            ->where('role', $role)
            ->delete();

        $this->clearCache();
    }

    /** @return array<string, bool> */
    private function overridesFor(int $companyId, string $role): array
    {
        $key = "{$companyId}:{$role}";
        if (array_key_exists($key, $this->overrideCache)) {
            return $this->overrideCache[$key];
        }

        $rows = DB::select(
            'SELECT module, action, is_allowed FROM company_role_permissions WHERE company_id = ? AND role = ?',
            [$companyId, $role]
        );

        $map = [];
        foreach ($rows as $row) {
            $map["{$row->module}:{$row->action}"] = (bool) $row->is_allowed;
        }

        return $this->overrideCache[$key] = $map;
    }
}
