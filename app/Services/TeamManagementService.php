<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class TeamManagementService
{
    public function __construct(
        private RbacService $rbac,
        private SubscriptionPlanService $plans
    ) {}

    public function updateMemberRole(int $companyId, int $memberId, string $newRole, User $actor): object
    {
        $newRole = strtolower(trim($newRole));
        if (!in_array($newRole, PermissionRegistry::assignableRoles(), true)) {
            throw new DomainException(__('team.errors.invalid_role'));
        }

        if (!$this->rbac->canAccessModule($actor, $companyId, 'team_management', 'edit')) {
            throw new AuthorizationException(__('team.errors.no_edit_role_perm'));
        }

        $updated = DB::transaction(function () use ($companyId, $memberId, $newRole, $actor): object {
            $member = DB::table('company_user')
                ->where('id', $memberId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$member) {
                abort(404, __('team.errors.member_not_found'));
            }

            $actorIsOwner = $this->rbac->isOwner($actor, $companyId);
            $targetRole = strtolower((string) $member->role);

            if ($targetRole === PermissionRegistry::ROLE_OWNER && !$actorIsOwner) {
                throw new AuthorizationException(__('team.errors.owner_only_modify'));
            }

            if ((int) $member->user_id === (int) $actor->id && $targetRole !== $newRole) {
                if ($targetRole === PermissionRegistry::ROLE_OWNER) {
                    throw new DomainException(__('team.errors.use_ownership_transfer'));
                }
                if (!$actorIsOwner) {
                    throw new AuthorizationException(__('team.errors.cannot_alter_own_role'));
                }
            }

            if ($targetRole === PermissionRegistry::ROLE_OWNER && (int) $member->is_active === 1) {
                $activeOwners = $this->countActiveOwnersLocked($companyId);
                if ($activeOwners <= 1) {
                    throw new DomainException(__('team.errors.cannot_demote_last_owner'));
                }
            }

            DB::table('company_user')
                ->where('id', $member->id)
                ->where('company_id', $companyId)
                ->update([
                    'role' => $newRole,
                    'updated_at' => now(),
                ]);

            return DB::table('company_user')->where('id', $member->id)->first();
        });

        $this->rbac->clearCache();

        return $updated;
    }

    public function updateMemberStatus(int $companyId, int $memberId, bool $active, User $actor): object
    {
        if (!$this->rbac->canAccessModule($actor, $companyId, 'team_management', 'edit')) {
            throw new AuthorizationException(__('team.errors.no_status_perm'));
        }

        $updated = DB::transaction(function () use ($companyId, $memberId, $active, $actor): object {
            $member = DB::table('company_user')
                ->where('id', $memberId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$member) {
                abort(404, __('team.errors.member_not_found'));
            }

            $actorIsOwner = $this->rbac->isOwner($actor, $companyId);
            $targetRole = strtolower((string) $member->role);

            if ($targetRole === PermissionRegistry::ROLE_OWNER && !$actorIsOwner) {
                throw new AuthorizationException(__('team.errors.owner_only_status'));
            }

            if ((int) $member->user_id === (int) $actor->id && !$active) {
                throw new DomainException(__('team.errors.cannot_deactivate_self'));
            }

            if (!$active && $targetRole === PermissionRegistry::ROLE_OWNER && (int) $member->is_active === 1) {
                $activeOwners = $this->countActiveOwnersLocked($companyId);
                if ($activeOwners <= 1) {
                    throw new DomainException(__('team.errors.cannot_deactivate_last_owner'));
                }
            }

            if ($active && (int) $member->is_active === 0) {
                $this->plans->assertCanCreateQuota($companyId, 'max_users', 1);
            }

            DB::table('company_user')
                ->where('id', $member->id)
                ->where('company_id', $companyId)
                ->update([
                    'is_active' => $active ? 1 : 0,
                    'updated_at' => now(),
                ]);

            return DB::table('company_user')->where('id', $member->id)->first();
        });

        $this->rbac->clearCache();

        return $updated;
    }

    public function removeMember(int $companyId, int $memberId, User $actor): void
    {
        if (!$this->rbac->canAccessModule($actor, $companyId, 'team_management', 'delete')) {
            throw new AuthorizationException(__('team.errors.no_remove_perm'));
        }

        DB::transaction(function () use ($companyId, $memberId, $actor): void {
            $member = DB::table('company_user')
                ->where('id', $memberId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$member) {
                abort(404, __('team.errors.member_not_found'));
            }

            $actorIsOwner = $this->rbac->isOwner($actor, $companyId);
            $targetRole = strtolower((string) $member->role);

            if ($targetRole === PermissionRegistry::ROLE_OWNER && !$actorIsOwner) {
                throw new AuthorizationException(__('team.errors.owner_only_remove'));
            }

            if ((int) $member->user_id === (int) $actor->id) {
                throw new DomainException(__('team.errors.cannot_remove_self'));
            }

            if ($targetRole === PermissionRegistry::ROLE_OWNER && (int) $member->is_active === 1) {
                $activeOwners = $this->countActiveOwnersLocked($companyId);
                if ($activeOwners <= 1) {
                    throw new DomainException(__('team.errors.cannot_remove_last_owner'));
                }
            }

            DB::table('company_user')
                ->where('id', $member->id)
                ->where('company_id', $companyId)
                ->delete();
        });

        $this->rbac->clearCache();
    }

    public function transferOwnership(int $companyId, int $targetMemberId, User $actor): object
    {
        if (!$this->rbac->isOwner($actor, $companyId)) {
            throw new AuthorizationException(__('team.errors.owner_only_transfer'));
        }

        $target = DB::transaction(function () use ($companyId, $targetMemberId, $actor): object {
            $members = DB::table('company_user')
                ->where('company_id', $companyId)
                ->where(function ($q) use ($actor, $targetMemberId): void {
                    $q->where('user_id', $actor->id)->orWhere('id', $targetMemberId);
                })
                ->lockForUpdate()
                ->get();

            $actorMember = $members->firstWhere('user_id', $actor->id);
            $targetMember = $members->firstWhere('id', $targetMemberId);

            if (!$actorMember || strtolower((string) $actorMember->role) !== PermissionRegistry::ROLE_OWNER || (int) $actorMember->is_active !== 1) {
                throw new AuthorizationException(__('team.errors.active_owner_only_transfer'));
            }

            if (!$targetMember || (int) $targetMember->company_id !== $companyId) {
                abort(404, __('team.errors.target_not_found'));
            }

            if ((int) $targetMember->user_id === (int) $actor->id) {
                throw new DomainException(__('team.errors.target_already_owner'));
            }

            if ((int) $targetMember->is_active !== 1) {
                throw new DomainException(__('team.errors.target_must_be_active'));
            }

            DB::table('company_user')
                ->where('id', $targetMember->id)
                ->where('company_id', $companyId)
                ->update([
                    'role' => PermissionRegistry::ROLE_OWNER,
                    'updated_at' => now(),
                ]);

            DB::table('company_user')
                ->where('id', $actorMember->id)
                ->where('company_id', $companyId)
                ->update([
                    'role' => PermissionRegistry::ROLE_ADMIN,
                    'updated_at' => now(),
                ]);

            return DB::table('company_user')->where('id', $targetMember->id)->first();
        });

        $this->rbac->clearCache();

        return $target;
    }

    private function countActiveOwnersLocked(int $companyId): int
    {
        return (int) DB::table('company_user')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(role) = ?', [PermissionRegistry::ROLE_OWNER])
            ->where('is_active', 1)
            ->lockForUpdate()
            ->count();
    }
}
