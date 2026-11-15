<?php

namespace App\Services;

use App\Exceptions\AlreadyOnboardedException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CompanyProvisioningService
{
    public function __construct(
        private TenantContext $tenantContext,
        private PlatformAuditService $audit,
        private SubscriptionPlanService $plans
    ) {}

    public function hasActiveCompany(User $user): bool
    {
        return $user->companies()
            ->wherePivot('is_active', 1)
            ->where('companies.is_active', 1)
            ->where('companies.account_status', Company::STATUS_ACTIVE)
            ->where(function ($q): void {
                $q->whereNull('companies.access_expires_at')
                    ->orWhere('companies.access_expires_at', '>=', now());
            })
            ->exists();
    }

    public function hasAnyWorkspace(User $user): bool
    {
        return $user->companies()
            ->wherePivot('is_active', 1)
            ->exists();
    }

    public function generateUniqueCode(string $companyName): string
    {
        $clean = strtoupper(Str::ascii(trim($companyName)));
        $slug = trim((string) preg_replace('/[^A-Z0-9]+/', '-', $clean), '-');
        $base = substr($slug !== '' ? $slug : 'STK', 0, 18);
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'STK';
        }

        if (!Company::where('code', $base)->exists()) {
            return $base;
        }

        for ($i = 2; $i <= 9999; $i++) {
            $candidate = substr($base, 0, 42) . '-' . $i;
            if (!Company::where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return substr($base, 0, 12) . '-' . strtoupper(Str::random(6));
    }

    /**
     * Atomically provision a company, creator OWNER membership, and default WH-MAIN warehouse.
     *
     * @throws AlreadyOnboardedException
     * @throws Throwable
     */
    public function createWorkspaceForUser(
        User $user,
        array $attributes,
        bool $requireFirstWorkspace = false,
        ?UploadedFile $logo = null,
        string $accountStatus = Company::STATUS_PENDING_ACTIVATION,
        string $accessType = Company::ACCESS_FREE
    ): Company {
        $storedLogoPath = null;
        $statusNorm = strtoupper(trim($accountStatus));
        $accessNorm = strtoupper(trim($accessType));

        try {
            if ($logo !== null) {
                $storedLogoPath = $logo->store('company-logos', 'public');
            }

            return DB::transaction(function () use ($user, $attributes, $requireFirstWorkspace, $storedLogoPath, $statusNorm, $accessNorm): Company {
                return $this->tenantContext->withoutScope(function () use ($user, $attributes, $requireFirstWorkspace, $storedLogoPath, $statusNorm, $accessNorm): Company {
                    // Serialize concurrent submissions for the same user during onboarding.
                    $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

                    $hasWorkspaceMembership = DB::table('company_user')
                        ->where('company_user.user_id', $lockedUser->id)
                        ->where('company_user.is_active', 1)
                        ->exists();

                    if ($requireFirstWorkspace && $hasWorkspaceMembership) {
                        throw new AlreadyOnboardedException('Your account already belongs to a workspace.');
                    }

                    $hasDefaultMembership = DB::table('company_user')
                        ->where('user_id', $lockedUser->id)
                        ->where('is_default', 1)
                        ->where('is_active', 1)
                        ->exists();

                    $requestedCode = strtoupper(trim((string) ($attributes['code'] ?? '')));
                    $code = $requestedCode !== ''
                        ? $requestedCode
                        : $this->generateUniqueCode((string) $attributes['name']);

                    $isActive = $statusNorm === Company::STATUS_ACTIVE;
                    $resolvedAccess = array_key_exists($accessNorm, Company::ACCESS_TYPES) ? $accessNorm : Company::ACCESS_FREE;
                    $resolvedPlan = $this->plans->resolveDefaultPlanByTier($resolvedAccess);

                    $company = Company::create([
                        'code' => $code,
                        'name' => trim((string) $attributes['name']),
                        'industry' => $attributes['industry'] ?? null,
                        'phone' => isset($attributes['phone']) && trim((string) $attributes['phone']) !== ''
                            ? trim((string) $attributes['phone'])
                            : null,
                        'address' => isset($attributes['address']) && trim((string) $attributes['address']) !== ''
                            ? trim((string) $attributes['address'])
                            : null,
                        'logo_path' => $storedLogoPath,
                        'is_active' => $isActive,
                        'account_status' => $statusNorm,
                        'access_type' => $resolvedAccess,
                        'subscription_plan_id' => $resolvedPlan?->id,
                        'access_starts_at' => $isActive ? now() : null,
                        'activated_at' => $isActive ? now() : null,
                    ]);

                    // Security rule: role is never accepted from client payload; creator always receives owner.
                    DB::table('company_user')->insert([
                        'company_id' => $company->id,
                        'user_id' => $lockedUser->id,
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

                    if ($statusNorm === Company::STATUS_PENDING_ACTIVATION) {
                        $this->audit->record(
                            action: 'CUSTOMER_REGISTERED',
                            companyId: (int) $company->id,
                            actorUserId: (int) $lockedUser->id,
                            previousValues: null,
                            newValues: [
                                'company_code' => $company->code,
                                'company_name' => $company->name,
                                'account_status' => $company->account_status,
                                'access_type' => $company->access_type,
                                'owner_email' => $lockedUser->email,
                            ],
                            note: 'Customer self-registered workspace and awaits manual platform activation.'
                        );
                    }

                    return $company;
                });
            });
        } catch (Throwable $e) {
            if ($storedLogoPath !== null) {
                Storage::disk('public')->delete($storedLogoPath);
            }

            throw $e;
        }
    }
}
