<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformAdmin;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class CompanyLifecycleService
{
    public const ALLOWED_TRANSITIONS = [
        Company::STATUS_PENDING_ACTIVATION => [
            Company::STATUS_ACTIVE,
            Company::STATUS_SUSPENDED,
            Company::STATUS_CANCELLED,
        ],
        Company::STATUS_ACTIVE => [
            Company::STATUS_SUSPENDED,
            Company::STATUS_EXPIRED,
            Company::STATUS_CANCELLED,
        ],
        Company::STATUS_SUSPENDED => [
            Company::STATUS_ACTIVE,
            Company::STATUS_EXPIRED,
            Company::STATUS_CANCELLED,
        ],
        Company::STATUS_EXPIRED => [
            Company::STATUS_ACTIVE,
            Company::STATUS_SUSPENDED,
            Company::STATUS_CANCELLED,
        ],
        Company::STATUS_CANCELLED => [
            Company::STATUS_ACTIVE,
            Company::STATUS_SUSPENDED,
        ],
    ];

    public function __construct(
        private TenantContext $tenantContext,
        private PlatformAuditService $audit,
        private SubscriptionPlanService $plans
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        $fromNorm = strtoupper(trim($from));
        $toNorm = strtoupper(trim($to));

        return in_array($toNorm, self::ALLOWED_TRANSITIONS[$fromNorm] ?? [], true);
    }

    /**
     * Automatically mark active companies whose access_expires_at is past as EXPIRED.
     */
    public function syncExpiredCompanies(?int $onlyCompanyId = null): int
    {
        return DB::transaction(function () use ($onlyCompanyId): int {
            return $this->tenantContext->withoutScope(function () use ($onlyCompanyId): int {
                $query = Company::where('account_status', Company::STATUS_ACTIVE)
                    ->whereNotNull('access_expires_at')
                    ->where('access_expires_at', '<', now());

                if ($onlyCompanyId !== null) {
                    $query->where('id', $onlyCompanyId);
                }

                $expiredCompanies = $query->lockForUpdate()->get();
                $count = 0;

                foreach ($expiredCompanies as $company) {
                    $previous = [
                        'account_status' => $company->account_status,
                        'is_active' => (bool) $company->is_active,
                        'access_expires_at' => $company->access_expires_at?->toIso8601String(),
                    ];

                    $company->update([
                        'account_status' => Company::STATUS_EXPIRED,
                        'is_active' => false,
                        'status_reason' => 'Access period expired automatically.',
                    ]);

                    $this->audit->record(
                        action: 'ACCOUNT_EXPIRED',
                        companyId: (int) $company->id,
                        previousValues: $previous,
                        newValues: [
                            'account_status' => Company::STATUS_EXPIRED,
                            'is_active' => false,
                            'access_expires_at' => $company->access_expires_at?->toIso8601String(),
                        ],
                        note: 'Access window ended and operational access was locked automatically.'
                    );

                    $count++;
                }

                return $count;
            });
        });
    }

    public function determineBlockReason(Company $company): ?string
    {
        if ($company->account_status === Company::STATUS_ACTIVE
            && $company->access_expires_at !== null
            && $company->access_expires_at->isPast()
        ) {
            $this->syncExpiredCompanies((int) $company->id);
            $company->refresh();
        }

        $status = $company->effectiveStatus();

        if ($status === Company::STATUS_ACTIVE && $company->is_active) {
            return null;
        }

        return strtolower($status === Company::STATUS_ACTIVE ? 'inactive' : $status);
    }

    public function activate(
        Company $company,
        PlatformAdmin $admin,
        string $accessType,
        ?Carbon $startsAt = null,
        ?Carbon $expiresAt = null,
        ?string $note = null,
        ?string $ipAddress = null
    ): Company {
        $accessType = strtoupper(trim($accessType));
        if (!array_key_exists($accessType, Company::ACCESS_TYPES)) {
            throw new DomainException('Selected access type is not valid.');
        }

        $effectiveStartsAt = $startsAt ?? now();
        if ($expiresAt !== null && $expiresAt->lte($effectiveStartsAt)) {
            throw new DomainException('Expiration date must be later than the activation start date.');
        }
        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new DomainException('Expiration date cannot be in the past when activating a customer.');
        }

        return DB::transaction(function () use ($company, $admin, $accessType, $effectiveStartsAt, $expiresAt, $note, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $accessType, $effectiveStartsAt, $expiresAt, $note, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $fromStatus = $locked->effectiveStatus();

                if ($fromStatus === Company::STATUS_ACTIVE && $locked->is_active) {
                    throw new DomainException('Customer workspace is already active.');
                }

                if (!$this->canTransition($fromStatus, Company::STATUS_ACTIVE)) {
                    throw new DomainException("Cannot activate workspace from {$fromStatus} status.");
                }

                $previous = $this->snapshot($locked);
                $targetPlan = $this->resolveSyncedPlanId($locked, $accessType);

                $locked->update([
                    'account_status' => Company::STATUS_ACTIVE,
                    'is_active' => true,
                    'access_type' => $accessType,
                    'subscription_plan_id' => $targetPlan,
                    'access_starts_at' => $effectiveStartsAt,
                    'access_expires_at' => $expiresAt,
                    'activated_at' => now(),
                    'activated_by_platform_admin_id' => $admin->id,
                    'suspended_at' => null,
                    'suspended_by_platform_admin_id' => null,
                    'status_reason' => $note !== null && trim($note) !== '' ? trim($note) : null,
                ]);

                $action = $fromStatus === Company::STATUS_PENDING_ACTIVATION
                    ? 'ACCOUNT_ACTIVATED'
                    : 'ACCOUNT_REACTIVATED';

                $this->audit->record(
                    action: $action,
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $note,
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function suspend(
        Company $company,
        PlatformAdmin $admin,
        string $reason,
        ?string $ipAddress = null
    ): Company {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A suspension reason is required.');
        }

        $this->syncExpiredCompanies((int) $company->id);

        return DB::transaction(function () use ($company, $admin, $reason, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $reason, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $fromStatus = $locked->effectiveStatus();

                if (!$this->canTransition($fromStatus, Company::STATUS_SUSPENDED)) {
                    throw new DomainException("Cannot suspend workspace from {$fromStatus} status.");
                }

                $previous = $this->snapshot($locked);

                $locked->update([
                    'account_status' => Company::STATUS_SUSPENDED,
                    'is_active' => false,
                    'suspended_at' => now(),
                    'suspended_by_platform_admin_id' => $admin->id,
                    'status_reason' => $reason,
                ]);

                $this->audit->record(
                    action: 'ACCOUNT_SUSPENDED',
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $reason,
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function reactivate(
        Company $company,
        PlatformAdmin $admin,
        ?string $accessType = null,
        ?Carbon $expiresAt = null,
        bool $clearExpiration = false,
        ?string $reason = null,
        ?string $ipAddress = null
    ): Company {
        $this->syncExpiredCompanies((int) $company->id);

        return DB::transaction(function () use ($company, $admin, $accessType, $expiresAt, $clearExpiration, $reason, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $accessType, $expiresAt, $clearExpiration, $reason, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $fromStatus = $locked->effectiveStatus();

                if ($fromStatus === Company::STATUS_PENDING_ACTIVATION) {
                    throw new DomainException('Pending workspaces must be activated using the activation workflow.');
                }

                if ($fromStatus === Company::STATUS_ACTIVE && $locked->is_active) {
                    throw new DomainException('Customer workspace is already active.');
                }

                if (!$this->canTransition($fromStatus, Company::STATUS_ACTIVE)) {
                    throw new DomainException("Cannot reactivate workspace from {$fromStatus} status.");
                }

                $targetAccessType = $accessType !== null && trim($accessType) !== ''
                    ? strtoupper(trim($accessType))
                    : ($locked->access_type ?: Company::ACCESS_FREE);

                if (!array_key_exists($targetAccessType, Company::ACCESS_TYPES)) {
                    throw new DomainException('Selected access type is not valid.');
                }

                $targetExpiresAt = $clearExpiration
                    ? null
                    : ($expiresAt ?? $locked->access_expires_at);

                if ($targetExpiresAt !== null && $targetExpiresAt->isPast()) {
                    throw new DomainException('Please provide a future expiration date or clear expiration before reactivating an expired workspace.');
                }

                $previous = $this->snapshot($locked);
                $targetPlan = $this->resolveSyncedPlanId($locked, $targetAccessType);

                $locked->update([
                    'account_status' => Company::STATUS_ACTIVE,
                    'is_active' => true,
                    'access_type' => $targetAccessType,
                    'subscription_plan_id' => $targetPlan,
                    'access_starts_at' => $locked->access_starts_at ?? now(),
                    'access_expires_at' => $targetExpiresAt,
                    'activated_at' => $locked->activated_at ?? now(),
                    'activated_by_platform_admin_id' => $locked->activated_by_platform_admin_id ?? $admin->id,
                    'suspended_at' => null,
                    'suspended_by_platform_admin_id' => null,
                    'status_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                ]);

                $this->audit->record(
                    action: 'ACCOUNT_REACTIVATED',
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $reason ?: 'Workspace reactivated by Platform Super Admin.',
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function expireNow(
        Company $company,
        PlatformAdmin $admin,
        ?string $reason = null,
        ?string $ipAddress = null
    ): Company {
        $this->syncExpiredCompanies((int) $company->id);

        return DB::transaction(function () use ($company, $admin, $reason, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $reason, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $fromStatus = $locked->effectiveStatus();

                if (!$this->canTransition($fromStatus, Company::STATUS_EXPIRED)) {
                    throw new DomainException("Cannot expire workspace from {$fromStatus} status.");
                }

                $previous = $this->snapshot($locked);
                $noteText = trim((string) ($reason ?: 'Marked expired manually by Platform Super Admin.'));

                $locked->update([
                    'account_status' => Company::STATUS_EXPIRED,
                    'is_active' => false,
                    'access_expires_at' => ($locked->access_expires_at !== null && $locked->access_expires_at->isPast())
                        ? $locked->access_expires_at
                        : now(),
                    'status_reason' => $noteText,
                ]);

                $this->audit->record(
                    action: 'ACCOUNT_EXPIRED',
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $noteText,
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function cancel(
        Company $company,
        PlatformAdmin $admin,
        string $reason,
        ?string $ipAddress = null
    ): Company {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('A cancellation reason is required.');
        }

        return DB::transaction(function () use ($company, $admin, $reason, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $reason, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $fromStatus = $locked->effectiveStatus();

                if (!$this->canTransition($fromStatus, Company::STATUS_CANCELLED)) {
                    throw new DomainException("Cannot cancel workspace from {$fromStatus} status.");
                }

                $previous = $this->snapshot($locked);

                $locked->update([
                    'account_status' => Company::STATUS_CANCELLED,
                    'is_active' => false,
                    'status_reason' => $reason,
                ]);

                $this->audit->record(
                    action: 'ACCOUNT_CANCELLED',
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $reason,
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function extendExpiration(
        Company $company,
        PlatformAdmin $admin,
        ?Carbon $newExpiresAt,
        bool $clearExpiration = false,
        bool $reactivateIfExpired = true,
        ?string $note = null,
        ?string $ipAddress = null
    ): Company {
        if (!$clearExpiration && $newExpiresAt === null) {
            throw new DomainException('Specify a new expiration date or choose unlimited access.');
        }

        if ($newExpiresAt !== null && $newExpiresAt->isPast()) {
            throw new DomainException('Extended expiration date must be in the future.');
        }

        $this->syncExpiredCompanies((int) $company->id);

        return DB::transaction(function () use ($company, $admin, $newExpiresAt, $clearExpiration, $reactivateIfExpired, $note, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $newExpiresAt, $clearExpiration, $reactivateIfExpired, $note, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $previous = $this->snapshot($locked);

                $updates = [
                    'access_expires_at' => $clearExpiration ? null : $newExpiresAt,
                ];

                if ($reactivateIfExpired && $locked->account_status === Company::STATUS_EXPIRED) {
                    $updates['account_status'] = Company::STATUS_ACTIVE;
                    $updates['is_active'] = true;
                    $updates['activated_at'] = $locked->activated_at ?? now();
                    $updates['activated_by_platform_admin_id'] = $locked->activated_by_platform_admin_id ?? $admin->id;
                    $updates['status_reason'] = null;
                }

                $locked->update($updates);

                $this->audit->record(
                    action: 'EXPIRATION_EXTENDED',
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $note ?: ($clearExpiration
                        ? 'Access expiration removed (unlimited term).'
                        : 'Access expiration extended to ' . $newExpiresAt->format('Y-m-d H:i') . '.'),
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function updateAccessType(
        Company $company,
        PlatformAdmin $admin,
        string $accessType,
        ?string $note = null,
        ?string $ipAddress = null
    ): Company {
        $accessType = strtoupper(trim($accessType));
        if (!array_key_exists($accessType, Company::ACCESS_TYPES)) {
            throw new DomainException('Selected access type is not valid.');
        }

        return DB::transaction(function () use ($company, $admin, $accessType, $note, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $accessType, $note, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $previous = $this->snapshot($locked);
                $targetPlan = $this->resolveSyncedPlanId($locked, $accessType);

                $locked->update([
                    'access_type' => $accessType,
                    'subscription_plan_id' => $targetPlan,
                ]);

                $this->audit->record(
                    action: 'ACCESS_TYPE_CHANGED',
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: $this->snapshot($locked),
                    note: $note ?: "Access tier updated to {$accessType}.",
                    ipAddress: $ipAddress
                );

                return $locked->fresh();
            });
        });
    }

    public function updateProfileAndNotes(
        Company $company,
        PlatformAdmin $admin,
        array $attributes,
        ?string $ipAddress = null
    ): Company {
        return DB::transaction(function () use ($company, $admin, $attributes, $ipAddress): Company {
            return $this->tenantContext->withoutScope(function () use ($company, $admin, $attributes, $ipAddress): Company {
                $locked = Company::where('id', $company->id)->lockForUpdate()->firstOrFail();
                $previousProfile = [
                    'code' => $locked->code,
                    'name' => $locked->name,
                    'industry' => $locked->industry,
                    'phone' => $locked->phone,
                    'address' => $locked->address,
                ];
                $previousNotes = $locked->admin_notes;

                $nextCode = array_key_exists('code', $attributes)
                    ? strtoupper(trim((string) $attributes['code']))
                    : $locked->code;

                $locked->update([
                    'code' => $nextCode,
                    'name' => trim((string) ($attributes['name'] ?? $locked->name)),
                    'industry' => $attributes['industry'] ?? $locked->industry,
                    'phone' => isset($attributes['phone']) && trim((string) $attributes['phone']) !== ''
                        ? trim((string) $attributes['phone'])
                        : null,
                    'address' => isset($attributes['address']) && trim((string) $attributes['address']) !== ''
                        ? trim((string) $attributes['address'])
                        : null,
                    'admin_notes' => array_key_exists('admin_notes', $attributes)
                        ? (trim((string) ($attributes['admin_notes'] ?? '')) !== '' ? trim((string) $attributes['admin_notes']) : null)
                        : $locked->admin_notes,
                ]);

                $newProfile = [
                    'code' => $locked->code,
                    'name' => $locked->name,
                    'industry' => $locked->industry,
                    'phone' => $locked->phone,
                    'address' => $locked->address,
                ];

                if ($previousProfile !== $newProfile) {
                    $this->audit->record(
                        action: 'PROFILE_UPDATED',
                        companyId: (int) $locked->id,
                        platformAdminId: (int) $admin->id,
                        previousValues: $previousProfile,
                        newValues: $newProfile,
                        note: 'Customer profile updated from Platform Control Plane.',
                        ipAddress: $ipAddress
                    );
                }

                if ($previousNotes !== $locked->admin_notes) {
                    $this->audit->record(
                        action: 'ADMIN_NOTE_UPDATED',
                        companyId: (int) $locked->id,
                        platformAdminId: (int) $admin->id,
                        previousValues: ['admin_notes' => $previousNotes],
                        newValues: ['admin_notes' => $locked->admin_notes],
                        note: 'Internal admin notes updated.',
                        ipAddress: $ipAddress
                    );
                }

                return $locked->fresh();
            });
        });
    }

    private function resolveSyncedPlanId(Company $locked, string $accessType): ?int
    {
        if ($locked->subscription_plan_id !== null) {
            $currentPlan = $locked->subscriptionPlan;
            if ($currentPlan !== null && strtoupper((string) $currentPlan->tier) === $accessType) {
                return (int) $currentPlan->id;
            }
        }

        return $this->plans->resolveDefaultPlanByTier($accessType)?->id;
    }

    private function snapshot(Company $company): array
    {
        return [
            'account_status' => $company->account_status,
            'is_active' => (bool) $company->is_active,
            'access_type' => $company->access_type,
            'subscription_plan_id' => $company->subscription_plan_id,
            'access_starts_at' => $company->access_starts_at?->toIso8601String(),
            'access_expires_at' => $company->access_expires_at?->toIso8601String(),
            'status_reason' => $company->status_reason,
        ];
    }
}
