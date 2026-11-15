<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use App\Services\SubscriptionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Idempotently seed the centralized feature catalog, provisional baseline
     * subscription plans (FREE/STARTER/PROFESSIONAL/ENTERPRISE), and missing
     * plan-feature links without overwriting any existing admin customizations
     * or altering existing company statuses/expiration dates.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $featuresByKey = [];

            foreach (SubscriptionCatalog::FEATURES as $definition) {
                $feature = SubscriptionFeature::firstOrCreate(
                    ['feature_key' => $definition['feature_key']],
                    [
                        'name' => $definition['name'],
                        'description' => $definition['description'],
                        'category' => $definition['category'],
                        'feature_type' => $definition['feature_type'],
                        'default_value' => $definition['default_value'],
                        'sort_order' => $definition['sort_order'],
                        'is_active' => true,
                    ]
                );

                $featuresByKey[$feature->feature_key] = $feature;
            }

            foreach (SubscriptionCatalog::provisionalPlans() as $planDef) {
                $featureValues = $planDef['features'];
                unset($planDef['features']);

                $isInternal = (bool) ($planDef['is_internal'] ?? false);
                $plan = SubscriptionPlan::firstOrCreate(
                    ['code' => $planDef['code']],
                    $planDef
                );

                if ($isInternal && !$plan->is_internal) {
                    $plan->forceFill(['is_internal' => true, 'is_recommended' => false])->save();
                }

                foreach ($featureValues as $featureKey => $value) {
                    $feature = $featuresByKey[$featureKey] ?? null;
                    if (!$feature) {
                        continue;
                    }

                    SubscriptionPlanFeature::firstOrCreate(
                        [
                            'subscription_plan_id' => $plan->id,
                            'subscription_feature_id' => $feature->id,
                        ],
                        [
                            'value' => $value,
                        ]
                    );
                }
            }

            // Safely link existing companies with null subscription_plan_id
            // using their Round 20 access_type, preserving status & dates.
            $plansByCode = SubscriptionPlan::whereIn('code', array_keys(Company::ACCESS_TYPES))
                ->pluck('id', 'code');

            Company::whereNull('subscription_plan_id')
                ->get(['id', 'access_type'])
                ->each(function (Company $company) use ($plansByCode): void {
                    $code = strtoupper((string) ($company->access_type ?: Company::ACCESS_FREE));
                    $planId = $plansByCode[$code] ?? ($plansByCode[Company::ACCESS_FREE] ?? null);
                    if ($planId !== null) {
                        DB::table('companies')
                            ->where('id', $company->id)
                            ->whereNull('subscription_plan_id')
                            ->update(['subscription_plan_id' => $planId]);
                    }
                });
        });
    }
}
