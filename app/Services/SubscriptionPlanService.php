<?php

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for Stockora subscription plan configuration,
 * customer entitlement resolution, and platform plan/feature administration.
 *
 * NOTE: Round 21 establishes configuration, comparison, assignment, and
 * entitlement lookup only. Operational feature blocking and quota rejection
 * are deliberately deferred to Round 22.
 */
class SubscriptionPlanService
{
    public function __construct(
        private TenantContext $tenantContext,
        private PlatformAuditService $audit
    ) {}

    /**
     * Resolve the default active plan matching a Round 20 access_type tier.
     */
    public function resolveDefaultPlanByTier(string $tier): ?SubscriptionPlan
    {
        $tierNorm = strtoupper(trim($tier));
        if (!array_key_exists($tierNorm, Company::ACCESS_TYPES)) {
            return null;
        }

        return SubscriptionPlan::with(['features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
            ->where(function ($q) use ($tierNorm): void {
                $q->where('code', $tierNorm)
                    ->orWhere('tier', $tierNorm);
            })
            ->where('is_active', 1)
            ->where('is_internal', 0)
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END ASC', [$tierNorm])
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Resolve the current subscription plan for an explicit Company model.
     * Prefers explicit `subscription_plan_id`, then falls back to `access_type`
     * for full Round 20 compatibility. Never resolves via untrusted request input.
     */
    public function getCompanyPlan(Company $company): ?SubscriptionPlan
    {
        if ($company->subscription_plan_id !== null) {
            if ($company->relationLoaded('subscriptionPlan') && $company->subscriptionPlan !== null) {
                $plan = $company->subscriptionPlan;
                if (!$plan->relationLoaded('features')) {
                    $plan->load(['features' => fn ($q) => $q->where('subscription_features.is_active', 1)]);
                }

                return $plan;
            }

            $plan = SubscriptionPlan::with(['features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
                ->find($company->subscription_plan_id);
            if ($plan !== null) {
                return $plan;
            }
        }

        $tier = strtoupper((string) ($company->access_type ?: Company::ACCESS_FREE));

        return SubscriptionPlan::with(['features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
            ->where('code', $tier)
            ->first()
            ?? $this->resolveDefaultPlanByTier($tier);
    }

    /**
     * Resolve entitlement plan strictly for the current authenticated TenantContext.
     */
    public function getActiveTenantPlan(): ?SubscriptionPlan
    {
        $companyId = $this->tenantContext->id();
        if ($companyId === null) {
            return null;
        }

        $company = Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
            ->find($companyId);

        if (!$company instanceof Company) {
            return null;
        }

        return $this->getCompanyPlan($company);
    }

    /**
     * Retrieve typed value for a given feature_key.
     *
     * - BOOLEAN: returns bool (defaults false when unassigned or inactive)
     * - LIMIT:   returns ?int where `null` means Unlimited (when plan configures unlimited)
     *            and `0` means fail-safe no quota when company has no valid plan assignment
     * - TEXT:    returns ?string
     */
    public function getFeatureValue(Company $company, string $featureKey): mixed
    {
        $entitlements = $this->getCompanyEntitlements($company);
        if (isset($entitlements['features'][$featureKey])) {
            return $entitlements['features'][$featureKey]['value'];
        }

        return null;
    }

    /**
     * Determine whether a feature is included/enabled on the company's current plan.
     */
    public function hasFeature(Company $company, string $featureKey): bool
    {
        $entitlements = $this->getCompanyEntitlements($company);

        return (bool) ($entitlements['features'][$featureKey]['enabled'] ?? false);
    }

    /**
     * Resolve a numeric quota limit for the company.
     * Returns `null` ONLY when configured as Unlimited on a valid plan;
     * returns safe zero (`0`) when missing plan assignment or unconfigured.
     */
    public function getLimit(Company $company, string $limitKey): ?int
    {
        $entitlements = $this->getCompanyEntitlements($company);

        if (!$entitlements['has_plan'] || !isset($entitlements['limits'][$limitKey])) {
            return 0;
        }

        $entry = $entitlements['limits'][$limitKey];
        if ($entry['is_unlimited']) {
            return null;
        }

        return $entry['limit'];
    }

    /**
     * Explicit ambiguity-free helper indicating whether a LIMIT key is configured as Unlimited.
     */
    public function isUnlimited(Company $company, string $limitKey): bool
    {
        $entitlements = $this->getCompanyEntitlements($company);

        return (bool) ($entitlements['limits'][$limitKey]['is_unlimited'] ?? false);
    }

    /**
     * Structured entitlement map consumed by Platform UI and future Round 22 enforcement.
     */
    public function getCompanyEntitlements(Company $company): array
    {
        $plan = $this->getCompanyPlan($company);
        $activeFeatures = SubscriptionFeature::where('is_active', 1)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $planIsValid = $plan !== null && (bool) $plan->is_active;

        $pivotValues = [];
        if ($plan !== null) {
            foreach ($plan->features as $assigned) {
                $pivotValues[$assigned->feature_key] = $assigned->pivot?->value;
            }
        }

        $featuresMap = [];
        $limitsMap = [];

        foreach ($activeFeatures as $feature) {
            $key = $feature->feature_key;
            $hasPivot = array_key_exists($key, $pivotValues);
            $raw = $hasPivot ? $pivotValues[$key] : null;

            if (!$planIsValid || !$hasPivot) {
                if ($feature->feature_type === SubscriptionFeature::TYPE_BOOLEAN) {
                    $featuresMap[$key] = [
                        'key' => $key,
                        'name' => $feature->name,
                        'category' => $feature->category,
                        'type' => $feature->feature_type,
                        'enabled' => false,
                        'value' => false,
                        'display_value' => 'Not Included',
                        'is_unlimited' => false,
                    ];
                } elseif ($feature->feature_type === SubscriptionFeature::TYPE_LIMIT) {
                    $featuresMap[$key] = [
                        'key' => $key,
                        'name' => $feature->name,
                        'category' => $feature->category,
                        'type' => $feature->feature_type,
                        'enabled' => false,
                        'value' => 0,
                        'display_value' => '0 (No Plan / Unconfigured)',
                        'is_unlimited' => false,
                    ];
                    $limitsMap[$key] = [
                        'name' => $feature->name,
                        'limit' => 0,
                        'is_unlimited' => false,
                        'configured' => false,
                    ];
                } else {
                    $featuresMap[$key] = [
                        'key' => $key,
                        'name' => $feature->name,
                        'category' => $feature->category,
                        'type' => $feature->feature_type,
                        'enabled' => false,
                        'value' => null,
                        'display_value' => '—',
                        'is_unlimited' => false,
                    ];
                }
                continue;
            }

            if ($feature->feature_type === SubscriptionFeature::TYPE_BOOLEAN) {
                $enabled = in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
                $featuresMap[$key] = [
                    'key' => $key,
                    'name' => $feature->name,
                    'category' => $feature->category,
                    'type' => $feature->feature_type,
                    'enabled' => $enabled,
                    'value' => $enabled,
                    'display_value' => $enabled ? 'Included' : 'Not Included',
                    'is_unlimited' => false,
                ];
            } elseif ($feature->feature_type === SubscriptionFeature::TYPE_LIMIT) {
                $isUnlimited = $raw === null || trim((string) $raw) === '';
                $limitInt = $isUnlimited ? null : max(0, (int) $raw);
                $featuresMap[$key] = [
                    'key' => $key,
                    'name' => $feature->name,
                    'category' => $feature->category,
                    'type' => $feature->feature_type,
                    'enabled' => true,
                    'value' => $limitInt,
                    'display_value' => $isUnlimited ? 'Unlimited' : number_format($limitInt, 0, ',', '.'),
                    'is_unlimited' => $isUnlimited,
                ];
                $limitsMap[$key] = [
                    'name' => $feature->name,
                    'limit' => $limitInt,
                    'is_unlimited' => $isUnlimited,
                    'configured' => true,
                ];
            } else {
                $textVal = $raw !== null && trim((string) $raw) !== '' ? trim((string) $raw) : null;
                $featuresMap[$key] = [
                    'key' => $key,
                    'name' => $feature->name,
                    'category' => $feature->category,
                    'type' => $feature->feature_type,
                    'enabled' => $textVal !== null,
                    'value' => $textVal,
                    'display_value' => $textVal ?? '—',
                    'is_unlimited' => false,
                ];
            }
        }

        return [
            'has_plan' => $planIsValid,
            'plan' => $plan ? [
                'id' => (int) $plan->id,
                'code' => $plan->code,
                'tier' => $plan->tier,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'formatted_price' => $plan->formattedPrice(),
                'billing_interval' => $plan->billing_interval,
                'is_active' => (bool) $plan->is_active,
                'is_recommended' => (bool) $plan->is_recommended,
            ] : null,
            'access_type' => $company->access_type,
            'effective_status' => $company->effectiveStatus(),
            'features' => $featuresMap,
            'limits' => $limitsMap,
        ];
    }

    /**
     * Create a new subscription plan and persist its feature matrix atomically.
     */
    public function createPlan(
        PlatformAdmin $admin,
        array $attributes,
        array $featureInput,
        ?string $ipAddress = null
    ): SubscriptionPlan {
        $code = strtoupper(trim((string) $attributes['code']));
        $tier = strtoupper(trim((string) ($attributes['tier'] ?? Company::ACCESS_FREE)));
        $interval = strtoupper(trim((string) ($attributes['billing_interval'] ?? SubscriptionPlan::INTERVAL_MONTHLY)));

        $this->assertValidPlanAttributes($tier, $interval, (float) ($attributes['price'] ?? 0));

        return DB::transaction(function () use ($admin, $attributes, $code, $tier, $interval, $featureInput, $ipAddress): SubscriptionPlan {
            if (!empty($attributes['is_recommended'])) {
                SubscriptionPlan::query()->where('is_recommended', 1)->update(['is_recommended' => 0]);
            }

            $plan = SubscriptionPlan::create([
                'code' => $code,
                'tier' => $tier,
                'name' => trim((string) $attributes['name']),
                'description' => isset($attributes['description']) && trim((string) $attributes['description']) !== ''
                    ? trim((string) $attributes['description'])
                    : null,
                'price' => round((float) $attributes['price'], 2),
                'billing_interval' => $interval,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'is_internal' => (bool) ($attributes['is_internal'] ?? false),
                'is_recommended' => !empty($attributes['is_internal']) ? false : (bool) ($attributes['is_recommended'] ?? false),
                'sort_order' => (int) ($attributes['sort_order'] ?? 10),
            ]);

            $syncedFeatures = $this->syncPlanFeatureMatrix($plan, $featureInput);

            $this->audit->record(
                action: 'PLAN_CREATED',
                platformAdminId: (int) $admin->id,
                previousValues: null,
                newValues: array_merge($this->planSnapshot($plan), ['features' => $syncedFeatures]),
                note: "Created subscription plan {$plan->code} ({$plan->name}).",
                ipAddress: $ipAddress,
                subscriptionPlanId: (int) $plan->id
            );

            return $plan->fresh(['features']);
        });
    }

    /**
     * Update a subscription plan and its configured features/limits atomically.
     */
    public function updatePlan(
        SubscriptionPlan $plan,
        PlatformAdmin $admin,
        array $attributes,
        array $featureInput,
        ?string $ipAddress = null
    ): SubscriptionPlan {
        $tier = strtoupper(trim((string) ($attributes['tier'] ?? $plan->tier)));
        $interval = strtoupper(trim((string) ($attributes['billing_interval'] ?? $plan->billing_interval)));
        $this->assertValidPlanAttributes($tier, $interval, (float) ($attributes['price'] ?? $plan->price));

        return DB::transaction(function () use ($plan, $admin, $attributes, $tier, $interval, $featureInput, $ipAddress): SubscriptionPlan {
            $locked = SubscriptionPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();
            $previous = array_merge($this->planSnapshot($locked), [
                'features' => $this->loadPlanFeatureSnapshot($locked),
            ]);

            $nextCode = $locked->isCoreSystemPlan()
                ? $locked->code
                : strtoupper(trim((string) ($attributes['code'] ?? $locked->code)));

            $nextTier = array_key_exists(strtoupper((string) $locked->code), Company::ACCESS_TYPES)
                ? $locked->code
                : $tier;

            $isInternal = $locked->is_internal || (bool) ($attributes['is_internal'] ?? false);
            $isRecommended = $isInternal ? false : (bool) ($attributes['is_recommended'] ?? false);
            if ($isRecommended) {
                SubscriptionPlan::query()->where('id', '!=', $locked->id)->where('is_recommended', 1)->update(['is_recommended' => 0]);
            }

            $locked->update([
                'code' => $nextCode,
                'tier' => $nextTier,
                'name' => trim((string) $attributes['name']),
                'description' => isset($attributes['description']) && trim((string) $attributes['description']) !== ''
                    ? trim((string) $attributes['description'])
                    : null,
                'price' => round((float) $attributes['price'], 2),
                'billing_interval' => $interval,
                'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : $locked->is_active,
                'is_internal' => $isInternal,
                'is_recommended' => $isRecommended,
                'sort_order' => (int) ($attributes['sort_order'] ?? $locked->sort_order),
            ]);

            $syncedFeatures = $this->syncPlanFeatureMatrix($locked, $featureInput);

            // Keep companies assigned to this plan synced on access_type if tier changed
            if (($previous['tier'] ?? null) !== $locked->tier) {
                $this->tenantContext->withoutScope(function () use ($locked): void {
                    Company::where('subscription_plan_id', $locked->id)
                        ->update(['access_type' => $locked->tier]);
                });
            }

            $this->audit->record(
                action: 'PLAN_UPDATED',
                platformAdminId: (int) $admin->id,
                previousValues: $previous,
                newValues: array_merge($this->planSnapshot($locked), ['features' => $syncedFeatures]),
                note: "Updated subscription plan {$locked->code} ({$locked->name}).",
                ipAddress: $ipAddress,
                subscriptionPlanId: (int) $locked->id
            );

            return $locked->fresh(['features']);
        });
    }

    public function setPlanActive(
        SubscriptionPlan $plan,
        PlatformAdmin $admin,
        bool $active,
        ?string $note = null,
        ?string $ipAddress = null
    ): SubscriptionPlan {
        return DB::transaction(function () use ($plan, $admin, $active, $note, $ipAddress): SubscriptionPlan {
            $locked = SubscriptionPlan::where('id', $plan->id)->lockForUpdate()->firstOrFail();

            if ((bool) $locked->is_active === $active) {
                throw new DomainException($active
                    ? "Plan {$locked->name} is already active."
                    : "Plan {$locked->name} is already inactive.");
            }

            $previous = $this->planSnapshot($locked);
            $locked->update(['is_active' => $active]);

            $this->audit->record(
                action: $active ? 'PLAN_ACTIVATED' : 'PLAN_DEACTIVATED',
                platformAdminId: (int) $admin->id,
                previousValues: $previous,
                newValues: $this->planSnapshot($locked),
                note: $note ?: ($active ? "Activated plan {$locked->code}." : "Deactivated plan {$locked->code}."),
                ipAddress: $ipAddress,
                subscriptionPlanId: (int) $locked->id
            );

            return $locked->fresh();
        });
    }

    public function deletePlan(SubscriptionPlan $plan): void
    {
        if ($plan->isCoreSystemPlan()) {
            throw new DomainException("Core tier plan {$plan->code} cannot be deleted. Deactivate or edit it instead.");
        }

        if ($plan->companies()->exists()) {
            throw new DomainException("Plan {$plan->code} is currently assigned to one or more customer companies and cannot be deleted.");
        }

        $plan->delete();
    }

    public function createFeature(
        PlatformAdmin $admin,
        array $attributes,
        ?string $ipAddress = null
    ): SubscriptionFeature {
        $type = strtoupper(trim((string) ($attributes['feature_type'] ?? SubscriptionFeature::TYPE_BOOLEAN)));
        if (!array_key_exists($type, SubscriptionFeature::TYPES)) {
            throw new DomainException('Invalid feature type selected.');
        }

        $defaultNormalized = $this->normalizeValueForFeatureType($type, $attributes['default_value'] ?? null);

        return DB::transaction(function () use ($admin, $attributes, $type, $defaultNormalized, $ipAddress): SubscriptionFeature {
            $feature = SubscriptionFeature::create([
                'feature_key' => strtolower(trim((string) $attributes['feature_key'])),
                'name' => trim((string) $attributes['name']),
                'description' => isset($attributes['description']) && trim((string) $attributes['description']) !== ''
                    ? trim((string) $attributes['description'])
                    : null,
                'category' => trim((string) $attributes['category']),
                'feature_type' => $type,
                'default_value' => $defaultNormalized,
                'sort_order' => (int) ($attributes['sort_order'] ?? 900),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
            ]);

            $this->audit->record(
                action: 'FEATURE_CREATED',
                platformAdminId: (int) $admin->id,
                previousValues: null,
                newValues: $this->featureSnapshot($feature),
                note: "Created feature {$feature->feature_key} ({$feature->name}).",
                ipAddress: $ipAddress,
                subscriptionFeatureId: (int) $feature->id
            );

            return $feature->fresh();
        });
    }

    public function updateFeature(
        SubscriptionFeature $feature,
        PlatformAdmin $admin,
        array $attributes,
        ?string $ipAddress = null
    ): SubscriptionFeature {
        $type = strtoupper(trim((string) ($attributes['feature_type'] ?? $feature->feature_type)));
        if (!array_key_exists($type, SubscriptionFeature::TYPES)) {
            throw new DomainException('Invalid feature type selected.');
        }

        return DB::transaction(function () use ($feature, $admin, $attributes, $type, $ipAddress): SubscriptionFeature {
            $locked = SubscriptionFeature::where('id', $feature->id)->lockForUpdate()->firstOrFail();

            if ($locked->feature_type !== $type && $locked->planFeatures()->exists()) {
                throw new DomainException("Cannot change feature type for {$locked->feature_key} while it is configured on existing plans.");
            }

            $previous = $this->featureSnapshot($locked);
            $defaultNormalized = $this->normalizeValueForFeatureType($type, $attributes['default_value'] ?? $locked->default_value);

            $locked->update([
                'name' => trim((string) $attributes['name']),
                'description' => isset($attributes['description']) && trim((string) $attributes['description']) !== ''
                    ? trim((string) $attributes['description'])
                    : null,
                'category' => trim((string) $attributes['category']),
                'feature_type' => $type,
                'default_value' => $defaultNormalized,
                'sort_order' => (int) ($attributes['sort_order'] ?? $locked->sort_order),
            ]);

            $this->audit->record(
                action: 'FEATURE_UPDATED',
                platformAdminId: (int) $admin->id,
                previousValues: $previous,
                newValues: $this->featureSnapshot($locked),
                note: "Updated feature {$locked->feature_key}.",
                ipAddress: $ipAddress,
                subscriptionFeatureId: (int) $locked->id
            );

            return $locked->fresh();
        });
    }

    public function setFeatureActive(
        SubscriptionFeature $feature,
        PlatformAdmin $admin,
        bool $active,
        ?string $ipAddress = null
    ): SubscriptionFeature {
        return DB::transaction(function () use ($feature, $admin, $active, $ipAddress): SubscriptionFeature {
            $locked = SubscriptionFeature::where('id', $feature->id)->lockForUpdate()->firstOrFail();

            if ((bool) $locked->is_active === $active) {
                throw new DomainException($active
                    ? "Feature {$locked->feature_key} is already active."
                    : "Feature {$locked->feature_key} is already inactive.");
            }

            $previous = $this->featureSnapshot($locked);
            $locked->update(['is_active' => $active]);

            $this->audit->record(
                action: $active ? 'FEATURE_ACTIVATED' : 'FEATURE_DEACTIVATED',
                platformAdminId: (int) $admin->id,
                previousValues: $previous,
                newValues: $this->featureSnapshot($locked),
                note: $active ? "Activated feature {$locked->feature_key}." : "Deactivated feature {$locked->feature_key}.",
                ipAddress: $ipAddress,
                subscriptionFeatureId: (int) $locked->id
            );

            return $locked->fresh();
        });
    }

    public function deleteFeature(SubscriptionFeature $feature): void
    {
        if ($feature->planFeatures()->exists()) {
            throw new DomainException("Cannot delete feature {$feature->feature_key} because it is still attached to existing subscription plans. Deactivate it instead.");
        }

        $feature->delete();
    }

    /**
     * Assign or change a customer company's subscription plan while strictly
     * preserving Round 20 lifecycle status, activation state, ownership, memberships,
     * and existing expiration date unless explicitly updated/cleared.
     */
    public function assignPlanToCompany(
        Company $company,
        SubscriptionPlan $plan,
        PlatformAdmin $admin,
        ?Carbon $expiresAt = null,
        bool $clearExpiration = false,
        ?string $adminNotes = null,
        ?string $note = null,
        ?string $ipAddress = null
    ): Company {
        if (!$plan->is_active) {
            throw new DomainException("Cannot assign inactive subscription plan {$plan->name} to a customer.");
        }

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new DomainException('Access expiration date cannot be in the past.');
        }

        return DB::transaction(function () use (
            $company,
            $plan,
            $admin,
            $expiresAt,
            $clearExpiration,
            $adminNotes,
            $note,
            $ipAddress
        ): Company {
            return $this->tenantContext->withoutScope(function () use (
                $company,
                $plan,
                $admin,
                $expiresAt,
                $clearExpiration,
                $adminNotes,
                $note,
                $ipAddress
            ): Company {
                $locked = Company::with('subscriptionPlan')->where('id', $company->id)->lockForUpdate()->firstOrFail();

                $hadPlanBefore = $locked->subscription_plan_id !== null;
                $previous = [
                    'subscription_plan_id' => $locked->subscription_plan_id,
                    'plan_code' => $locked->subscriptionPlan?->code,
                    'access_type' => $locked->access_type,
                    'access_expires_at' => $locked->access_expires_at?->toIso8601String(),
                    'account_status' => $locked->account_status,
                    'is_active' => (bool) $locked->is_active,
                ];

                $updates = [
                    'subscription_plan_id' => $plan->id,
                    'access_type' => $plan->tier,
                ];

                if ($clearExpiration) {
                    $updates['access_expires_at'] = null;
                } elseif ($expiresAt !== null) {
                    $updates['access_expires_at'] = $expiresAt;
                }

                if ($adminNotes !== null) {
                    $cleanNote = trim($adminNotes);
                    $updates['admin_notes'] = $cleanNote !== '' ? $cleanNote : null;
                }

                $locked->update($updates);
                $locked->refresh()->load('subscriptionPlan');

                $action = $hadPlanBefore ? 'COMPANY_PLAN_CHANGED' : 'COMPANY_PLAN_ASSIGNED';

                $this->audit->record(
                    action: $action,
                    companyId: (int) $locked->id,
                    platformAdminId: (int) $admin->id,
                    previousValues: $previous,
                    newValues: [
                        'subscription_plan_id' => $locked->subscription_plan_id,
                        'plan_code' => $plan->code,
                        'access_type' => $locked->access_type,
                        'access_expires_at' => $locked->access_expires_at?->toIso8601String(),
                        'account_status' => $locked->account_status,
                        'is_active' => (bool) $locked->is_active,
                    ],
                    note: ($note !== null && trim($note) !== '')
                        ? trim($note)
                        : ($hadPlanBefore
                            ? "Changed subscription plan to {$plan->name} ({$plan->code})."
                            : "Assigned subscription plan {$plan->name} ({$plan->code})."),
                    ipAddress: $ipAddress,
                    subscriptionPlanId: (int) $plan->id
                );

                return $locked;
            });
        });
    }

    /**
     * Load active plans & categorized features for public/admin comparison matrix.
     */
    public function getComparisonData(bool $onlyActivePlans = true, bool $excludeInternal = true): array
    {
        $plansQuery = SubscriptionPlan::with(['features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
            ->when($excludeInternal, fn ($q) => $q->where('is_internal', 0))
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($onlyActivePlans) {
            $plansQuery->where('is_active', 1);
        }

        $plans = $plansQuery->get();

        $features = SubscriptionFeature::where('is_active', 1)
            ->orderByRaw("FIELD(category, 'Resource Limits', 'Inventory Management', 'Purchasing', 'Reporting', 'Team & Access Management', 'Advanced Capabilities') ASC")
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groupedFeatures = $features->groupBy('category');

        $matrix = [];
        foreach ($plans as $plan) {
            foreach ($plan->features as $feature) {
                $matrix[$plan->id][$feature->feature_key] = $feature->pivot?->value;
            }
        }

        return compact('plans', 'features', 'groupedFeatures', 'matrix');
    }

    private function assertValidPlanAttributes(string $tier, string $interval, float $price): void
    {
        if (!array_key_exists($tier, Company::ACCESS_TYPES)) {
            throw new DomainException('Selected plan tier is invalid.');
        }

        if (!array_key_exists($interval, SubscriptionPlan::BILLING_INTERVALS)) {
            throw new DomainException('Selected billing interval is invalid.');
        }

        if ($price < 0) {
            throw new DomainException('Plan price cannot be negative.');
        }
    }

    private function syncPlanFeatureMatrix(SubscriptionPlan $plan, array $featureInput): array
    {
        $features = SubscriptionFeature::orderBy('id')->get()->keyBy('id');
        $snapshot = [];

        foreach ($features as $featureId => $feature) {
            $hasSubmitted = array_key_exists($featureId, $featureInput)
                || array_key_exists($feature->feature_key, $featureInput);

            $raw = $featureInput[$featureId] ?? ($featureInput[$feature->feature_key] ?? null);

            if ($feature->feature_type === SubscriptionFeature::TYPE_BOOLEAN) {
                $val = $hasSubmitted && in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
            } else {
                $val = $this->normalizeValueForFeatureType($feature->feature_type, $raw);
            }

            SubscriptionPlanFeature::updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'subscription_feature_id' => $feature->id,
                ],
                ['value' => $val]
            );

            $snapshot[$feature->feature_key] = $val;
        }

        return $snapshot;
    }

    private function normalizeValueForFeatureType(string $type, mixed $raw): ?string
    {
        if ($type === SubscriptionFeature::TYPE_BOOLEAN) {
            return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        if ($type === SubscriptionFeature::TYPE_LIMIT) {
            if ($raw === null || trim((string) $raw) === '') {
                return null;
            }
            if (!is_numeric($raw) || (int) $raw < 0) {
                throw new DomainException('Limit values must be non-negative integers or left blank for Unlimited.');
            }

            return (string) (int) $raw;
        }

        $str = trim((string) ($raw ?? ''));

        return $str !== '' ? $str : null;
    }

    private function planSnapshot(SubscriptionPlan $plan): array
    {
        return [
            'id' => (int) $plan->id,
            'code' => $plan->code,
            'tier' => $plan->tier,
            'name' => $plan->name,
            'price' => (float) $plan->price,
            'billing_interval' => $plan->billing_interval,
            'is_active' => (bool) $plan->is_active,
            'is_recommended' => (bool) $plan->is_recommended,
            'is_internal' => (bool) $plan->is_internal,
            'sort_order' => (int) $plan->sort_order,
        ];
    }

    private function loadPlanFeatureSnapshot(SubscriptionPlan $plan): array
    {
        $map = [];
        $plan->loadMissing('features');
        foreach ($plan->features as $feature) {
            $map[$feature->feature_key] = $feature->pivot?->value;
        }
        ksort($map);

        return $map;
    }

    public function hasActiveTenantFeature(string $featureKey): bool
    {
        $companyId = $this->tenantContext->id();
        if ($companyId === null) {
            return false;
        }

        $company = Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
            ->find($companyId);

        if (!$company instanceof Company) {
            return false;
        }

        return $this->hasFeature($company, $featureKey);
    }

    /**
     * Resolve usage counter for one of the 6 measurable LIMIT feature keys.
     */
    public function getUsage(Company|int $companyOrId, string $limitKey, ?ConnectionInterface $conn = null): int
    {
        $companyId = $companyOrId instanceof Company ? (int) $companyOrId->id : (int) $companyOrId;
        $connection = $conn ?? DB::connection('mysql');

        return $this->tenantContext->withoutScope(function () use ($companyId, $limitKey, $connection): int {
            if ($limitKey === 'max_users') {
                $row = $connection->selectOne(
                    'SELECT COUNT(*) AS cnt FROM `company_user` WHERE `company_id` = ? AND `is_active` = 1',
                    [$companyId]
                );

                return (int) ($row->cnt ?? 0);
            }

            if ($limitKey === 'max_warehouses') {
                $row = $connection->selectOne(
                    'SELECT COUNT(*) AS cnt FROM `warehouses` WHERE `company_id` = ? AND `is_active` = 1',
                    [$companyId]
                );

                return (int) ($row->cnt ?? 0);
            }

            if ($limitKey === 'max_products') {
                $row = $connection->selectOne(
                    'SELECT COUNT(*) AS cnt FROM `products` WHERE `company_id` = ?',
                    [$companyId]
                );

                return (int) ($row->cnt ?? 0);
            }

            if ($limitKey === 'max_suppliers') {
                $row = $connection->selectOne(
                    'SELECT COUNT(*) AS cnt FROM `suppliers` WHERE `company_id` = ?',
                    [$companyId]
                );

                return (int) ($row->cnt ?? 0);
            }

            if ($limitKey === 'max_monthly_purchases') {
                $monthStart = Carbon::now(config('app.timezone', 'UTC'))->startOfMonth()->format('Y-m-d H:i:s');
                $monthEnd = Carbon::now(config('app.timezone', 'UTC'))->endOfMonth()->format('Y-m-d H:i:s');

                $row = $connection->selectOne(
                    'SELECT COUNT(*) AS cnt FROM `purchases` WHERE `company_id` = ? AND `created_at` >= ? AND `created_at` <= ?',
                    [$companyId, $monthStart, $monthEnd]
                );

                return (int) ($row->cnt ?? 0);
            }

            if ($limitKey === 'max_monthly_stock_movements') {
                // Uses DB session NOW() window to stay synchronized with InventoryService raw NOW() timestamps.
                $row = $connection->selectOne(
                    "SELECT COUNT(*) AS cnt FROM `stock_movements`
                     WHERE `company_id` = ?
                       AND `created_at` >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')
                       AND `created_at` < DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH), '%Y-%m-01 00:00:00')",
                    [$companyId]
                );

                return (int) ($row->cnt ?? 0);
            }

            throw new DomainException("Unsupported quota limit key: {$limitKey}");
        });
    }

    public function getPendingInvitationSeatReservations(int $companyId, ?ConnectionInterface $conn = null): int
    {
        $connection = $conn ?? DB::connection('mysql');
        $row = $connection->selectOne(
            "SELECT COUNT(*) AS cnt FROM `company_invitations` WHERE `company_id` = ? AND `status` = 'pending' AND `expires_at` > ?",
            [$companyId, now()->format('Y-m-d H:i:s')]
        );

        return (int) ($row->cnt ?? 0);
    }

    public function getRemaining(Company $company, string $limitKey): ?int
    {
        $limit = $this->getLimit($company, $limitKey);
        if ($limit === null) {
            return null;
        }

        $usage = $this->getUsage($company, $limitKey);

        return max(0, $limit - $usage);
    }

    public function canCreate(Company $company, string $limitKey, int $delta = 1): bool
    {
        $limit = $this->getLimit($company, $limitKey);
        if ($limit === null) {
            return true;
        }

        $usage = $this->getUsage($company, $limitKey);

        return ($usage + max(1, $delta)) <= $limit;
    }

    /**
     * Atomically enforce quota under an exclusive company row lock inside an active transaction.
     * Must NOT be bypassed by client input; throws QuotaExceededException cleanly on breach.
     */
    public function assertCanCreateQuota(
        Company|int $companyOrId,
        string $limitKey,
        int $delta = 1,
        ?ConnectionInterface $conn = null,
        bool $includePendingInvitesForSeats = false
    ): void {
        $companyId = $companyOrId instanceof Company ? (int) $companyOrId->id : (int) $companyOrId;
        $connection = $conn ?? DB::connection('mysql');
        $increment = max(1, $delta);

        $connection->selectOne(
            'SELECT `id` FROM `companies` WHERE `id` = ? FOR UPDATE',
            [$companyId]
        );

        $company = $this->tenantContext->withoutScope(function () use ($companyId): Company {
            return Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
                ->findOrFail($companyId);
        });

        $plan = $this->getCompanyPlan($company);
        $planName = $plan?->name ?? ($company->access_type ?: Company::ACCESS_FREE);
        $limit = $this->getLimit($company, $limitKey);

        if ($limit === null) {
            return;
        }

        $usage = $this->getUsage($company, $limitKey, $connection);
        if (($usage + $increment) > $limit) {
            throw new QuotaExceededException($limitKey, $usage, $limit, $increment, $planName);
        }

        if ($includePendingInvitesForSeats && $limitKey === 'max_users') {
            $pendingInvites = $this->getPendingInvitationSeatReservations($companyId, $connection);
            $effectiveSeats = $usage + $pendingInvites;
            if (($effectiveSeats + $increment) > $limit) {
                throw new QuotaExceededException($limitKey, $effectiveSeats, $limit, $increment, $planName);
            }
        }
    }

    /**
     * Complete resource usage summary across all 6 measurable quotas for dashboard & platform views.
     */
    public function getUsageSummary(Company $company): array
    {
        $entitlements = $this->getCompanyEntitlements($company);
        $pendingInvites = $this->getPendingInvitationSeatReservations((int) $company->id);
        $monthlyKeys = ['max_monthly_purchases', 'max_monthly_stock_movements'];

        $items = [];
        foreach (SubscriptionCatalog::LIMIT_KEYS as $key) {
            $limitInfo = $entitlements['limits'][$key] ?? [
                'name' => $key,
                'limit' => 0,
                'is_unlimited' => false,
                'configured' => false,
            ];

            $usage = $this->getUsage($company, $key);
            $isUnlimited = (bool) ($limitInfo['is_unlimited'] ?? false);
            $limit = $isUnlimited ? null : (int) ($limitInfo['limit'] ?? 0);
            $remaining = $isUnlimited ? null : max(0, $limit - $usage);

            if ($isUnlimited) {
                $percentage = null;
                $status = 'unlimited';
            } elseif ($limit <= 0) {
                $percentage = 100;
                $status = 'reached';
            } else {
                $percentage = min(100, (int) round(($usage / $limit) * 100));
                if ($usage >= $limit) {
                    $status = 'reached';
                } elseif ($percentage >= 80) {
                    $status = 'warning';
                } else {
                    $status = 'ok';
                }
            }

            $items[$key] = [
                'key' => $key,
                'name' => $limitInfo['name'] ?? $key,
                'basis' => in_array($key, $monthlyKeys, true) ? 'monthly' : 'lifetime',
                'usage' => $usage,
                'limit' => $limit,
                'is_unlimited' => $isUnlimited,
                'remaining' => $remaining,
                'percentage' => $percentage,
                'status' => $status,
                'pending_reservations' => $key === 'max_users' ? $pendingInvites : 0,
            ];
        }

        return [
            'has_plan' => (bool) ($entitlements['has_plan'] ?? false),
            'plan' => $entitlements['plan'] ?? null,
            'items' => $items,
            'has_warnings' => collect($items)->contains(fn (array $item) => in_array($item['status'], ['warning', 'reached'], true)),
            'has_reached' => collect($items)->contains(fn (array $item) => $item['status'] === 'reached'),
        ];
    }

    private function featureSnapshot(SubscriptionFeature $feature): array
    {
        return [
            'id' => (int) $feature->id,
            'feature_key' => $feature->feature_key,
            'name' => $feature->name,
            'category' => $feature->category,
            'feature_type' => $feature->feature_type,
            'default_value' => $feature->default_value,
            'is_active' => (bool) $feature->is_active,
            'sort_order' => (int) $feature->sort_order,
        ];
    }
}
