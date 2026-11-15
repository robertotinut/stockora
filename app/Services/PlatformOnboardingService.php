<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Notifications\DirectCustomerWelcomeNotification;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PlatformOnboardingService
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyProvisioningService $provisioning,
        private PlatformAuditService $audit,
        private SubscriptionPlanService $plans
    ) {}

    public function hasRealMailTransport(): bool
    {
        return !in_array(config('mail.default'), ['log'], true);
    }

    /**
     * Atomically create a customer company, default warehouse, owner user (or confirmed attach),
     * and single-use password setup flow for newly provisioned users.
     *
     * @return array{company: Company, owner: User, owner_mode: string, setup_url: ?string, mail_dispatched: bool}
     */
    public function createCustomerDirect(
        PlatformAdmin $admin,
        array $companyAttributes,
        array $ownerAttributes,
        string $accessType = Company::ACCESS_PROFESSIONAL,
        string $initialStatus = Company::STATUS_ACTIVE,
        ?Carbon $startsAt = null,
        ?Carbon $expiresAt = null,
        ?string $adminNotes = null,
        bool $confirmExistingOwner = false,
        ?string $ipAddress = null
    ): array {
        $accessType = strtoupper(trim($accessType));
        if (!array_key_exists($accessType, Company::ACCESS_TYPES)) {
            throw new DomainException('Selected access type is invalid.');
        }

        $initialStatus = strtoupper(trim($initialStatus));
        if (!in_array($initialStatus, [Company::STATUS_ACTIVE, Company::STATUS_PENDING_ACTIVATION], true)) {
            throw new DomainException('Initial status must be ACTIVE or PENDING_ACTIVATION.');
        }

        $effectiveStartsAt = $startsAt ?? now();
        if ($expiresAt !== null && $expiresAt->lte($effectiveStartsAt)) {
            throw new DomainException('Expiration date must be later than the access start date.');
        }
        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new DomainException('Expiration date cannot be in the past.');
        }

        $ownerEmail = strtolower(trim((string) ($ownerAttributes['email'] ?? '')));
        $ownerName = trim((string) ($ownerAttributes['name'] ?? ''));

        $result = DB::transaction(function () use (
            $admin,
            $companyAttributes,
            $ownerEmail,
            $ownerName,
            $accessType,
            $initialStatus,
            $effectiveStartsAt,
            $expiresAt,
            $adminNotes,
            $confirmExistingOwner,
            $ipAddress
        ): array {
            return $this->tenantContext->withoutScope(function () use (
                $admin,
                $companyAttributes,
                $ownerEmail,
                $ownerName,
                $accessType,
                $initialStatus,
                $effectiveStartsAt,
                $expiresAt,
                $adminNotes,
                $confirmExistingOwner,
                $ipAddress
            ): array {
                $existingOwner = User::whereRaw('LOWER(email) = ?', [$ownerEmail])->lockForUpdate()->first();

                if ($existingOwner && !$confirmExistingOwner) {
                    throw new DomainException(
                        "A Stockora user already exists for {$ownerEmail}. Confirm attaching this existing user as workspace owner, or enter a new unique owner email."
                    );
                }

                $ownerMode = $existingOwner ? 'existing_user' : 'new_user';

                $owner = $existingOwner ?? User::create([
                    'name' => $ownerName,
                    'email' => $ownerEmail,
                    // Unguessable random placeholder until owner finishes single-use password setup link.
                    'password' => Hash::make(Str::random(48)),
                ]);

                $requestedCode = strtoupper(trim((string) ($companyAttributes['code'] ?? '')));
                $code = $requestedCode !== ''
                    ? $requestedCode
                    : $this->provisioning->generateUniqueCode((string) $companyAttributes['name']);

                $isActive = $initialStatus === Company::STATUS_ACTIVE;
                $resolvedPlan = $this->plans->resolveDefaultPlanByTier($accessType);

                $company = Company::create([
                    'code' => $code,
                    'name' => trim((string) $companyAttributes['name']),
                    'industry' => $companyAttributes['industry'] ?? null,
                    'phone' => isset($companyAttributes['phone']) && trim((string) $companyAttributes['phone']) !== ''
                        ? trim((string) $companyAttributes['phone'])
                        : null,
                    'address' => isset($companyAttributes['address']) && trim((string) $companyAttributes['address']) !== ''
                        ? trim((string) $companyAttributes['address'])
                        : null,
                    'is_active' => $isActive,
                    'account_status' => $initialStatus,
                    'access_type' => $accessType,
                    'subscription_plan_id' => $resolvedPlan?->id,
                    'access_starts_at' => $effectiveStartsAt,
                    'access_expires_at' => $expiresAt,
                    'activated_at' => $isActive ? now() : null,
                    'activated_by_platform_admin_id' => $isActive ? $admin->id : null,
                    'admin_notes' => $adminNotes !== null && trim($adminNotes) !== '' ? trim($adminNotes) : null,
                ]);

                $hasDefaultMembership = DB::table('company_user')
                    ->where('user_id', $owner->id)
                    ->where('is_default', 1)
                    ->where('is_active', 1)
                    ->exists();

                DB::table('company_user')->insert([
                    'company_id' => $company->id,
                    'user_id' => $owner->id,
                    'role' => 'owner',
                    'is_default' => $hasDefaultMembership ? 0 : 1,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('warehouses')->insert([
                    'company_id' => $company->id,
                    'code' => 'WH-MAIN',
                    'name' => 'Gudang Utama',
                    'is_default' => 1,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->audit->record(
                    action: 'CUSTOMER_CREATED_DIRECTLY',
                    companyId: (int) $company->id,
                    platformAdminId: (int) $admin->id,
                    actorUserId: (int) $owner->id,
                    previousValues: null,
                    newValues: [
                        'company_code' => $company->code,
                        'company_name' => $company->name,
                        'account_status' => $company->account_status,
                        'access_type' => $company->access_type,
                        'access_expires_at' => $company->access_expires_at?->toIso8601String(),
                        'owner_email' => $owner->email,
                        'owner_mode' => $ownerMode,
                    ],
                    note: $adminNotes ?: 'Customer workspace onboarded directly by Platform Super Admin.',
                    ipAddress: $ipAddress
                );

                return [
                    'company' => $company,
                    'owner' => $owner,
                    'owner_mode' => $ownerMode,
                ];
            });
        });

        $setupUrl = null;
        $mailDispatched = false;

        if ($result['owner_mode'] === 'new_user') {
            $token = Password::broker('users')->createToken($result['owner']);
            $setupUrl = route('password.setup', [
                'token' => $token,
                'email' => $result['owner']->email,
            ]);

            if (app()->environment('testing') || $this->hasRealMailTransport()) {
                Notification::send(
                    $result['owner'],
                    new DirectCustomerWelcomeNotification(
                        companyName: $result['company']->name,
                        companyCode: $result['company']->code,
                        setupUrl: $setupUrl
                    )
                );
                $mailDispatched = true;
            }
        }

        return [
            'company' => $result['company'],
            'owner' => $result['owner'],
            'owner_mode' => $result['owner_mode'],
            'setup_url' => $setupUrl,
            'mail_dispatched' => $mailDispatched,
        ];
    }
}
