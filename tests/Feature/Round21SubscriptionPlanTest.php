<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanFeature;
use App\Models\User;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Database\Seeders\SubscriptionPlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Round21SubscriptionPlanTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function createPlatformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::create([
            'name' => 'Round 21 Operator',
            'email' => 'r21-op-' . uniqid() . '@stockora.app',
            'password' => Hash::make('PlatformStrong2026!'),
            'is_active' => true,
        ]);
    }

    private function createTenantOwnerCompany(
        string $status = Company::STATUS_ACTIVE,
        ?string $accessType = Company::ACCESS_STARTER,
        ?int $planId = null
    ): array {
        $tag = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $user = User::factory()->create();

        $company = Company::create([
            'code' => 'R21CO-' . $tag,
            'name' => "PT Round21 Tenant {$tag}",
            'is_active' => $status === Company::STATUS_ACTIVE,
            'account_status' => $status,
            'access_type' => $accessType ?? Company::ACCESS_FREE,
            'subscription_plan_id' => $planId,
            'access_starts_at' => now()->subDays(5),
            'access_expires_at' => now()->addDays(45),
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $company];
    }

    public function test_plan_crud_validation_activation_deactivation_and_safe_deletion(): void
    {
        $admin = $this->createPlatformAdmin();

        // Unique plan code + negative price + invalid negative limit validation
        $this->actingAs($admin, 'platform')
            ->from('/platform/plans/create')
            ->post('/platform/plans', [
                'code' => 'STARTER',
                'tier' => Company::ACCESS_STARTER,
                'name' => 'Duplicate Starter',
                'price' => -100,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'sort_order' => 10,
            ])
            ->assertRedirect('/platform/plans/create')
            ->assertSessionHasErrors(['code', 'price']);

        $maxUsersFeature = SubscriptionFeature::where('feature_key', 'max_users')->firstOrFail();
        $productsFeature = SubscriptionFeature::where('feature_key', 'module_products')->firstOrFail();
        $supportFeature = SubscriptionFeature::where('feature_key', 'capability_support_tier')->firstOrFail();

        $this->actingAs($admin, 'platform')
            ->from('/platform/plans/create')
            ->post('/platform/plans', [
                'code' => 'NEG_TEST',
                'tier' => Company::ACCESS_STARTER,
                'name' => 'Negative Limit Test',
                'price' => 100000,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'sort_order' => 11,
                'features' => [
                    $maxUsersFeature->id => '-5',
                ],
            ])
            ->assertRedirect('/platform/plans/create')
            ->assertSessionHasErrors(["features.{$maxUsersFeature->id}"]);

        // Valid plan creation with null Unlimited limit + boolean + text
        $planCode = 'GROWTH_' . strtoupper(substr(uniqid(), -5));
        $this->actingAs($admin, 'platform')
            ->post('/platform/plans', [
                'code' => $planCode,
                'tier' => Company::ACCESS_PROFESSIONAL,
                'name' => 'Growth Custom Pack',
                'description' => 'Custom B2B growth pack',
                'price' => 550000,
                'billing_interval' => SubscriptionPlan::INTERVAL_YEARLY,
                'sort_order' => 15,
                'is_active' => '1',
                'is_recommended' => '0',
                'features' => [
                    $productsFeature->id => '1',
                    $maxUsersFeature->id => '', // Unlimited (null)
                    $supportFeature->id => 'Dedicated SLA Test',
                ],
            ])
            ->assertSessionHasNoErrors();

        $plan = SubscriptionPlan::where('code', $planCode)->firstOrFail();
        $this->assertSame('Growth Custom Pack', $plan->name);
        $this->assertSame(Company::ACCESS_PROFESSIONAL, $plan->tier);
        $this->assertSame(550000.00, (float) $plan->price);

        $createdLog = PlatformAuditLog::where('subscription_plan_id', $plan->id)
            ->where('action', 'PLAN_CREATED')
            ->first();
        $this->assertNotNull($createdLog);

        // Null limit stored accurately
        $limitPivot = SubscriptionPlanFeature::where('subscription_plan_id', $plan->id)
            ->where('subscription_feature_id', $maxUsersFeature->id)
            ->firstOrFail();
        $this->assertNull($limitPivot->value);

        // Update plan & verify PLAN_UPDATED audit
        $this->actingAs($admin, 'platform')
            ->put(route('platform.plans.update', $plan), [
                'code' => $planCode,
                'tier' => Company::ACCESS_PROFESSIONAL,
                'name' => 'Growth Pack V2',
                'description' => 'Updated plan notes',
                'price' => 650000,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'sort_order' => 16,
                'is_active' => '1',
                'features' => [
                    $productsFeature->id => '1',
                    $maxUsersFeature->id => '42',
                    $supportFeature->id => 'Updated SLA',
                ],
            ])
            ->assertRedirect(route('platform.plans.show', $plan));

        $plan->refresh();
        $this->assertSame('Growth Pack V2', $plan->name);
        $this->assertSame(650000.00, (float) $plan->price);
        $this->assertSame('42', SubscriptionPlanFeature::where('subscription_plan_id', $plan->id)
            ->where('subscription_feature_id', $maxUsersFeature->id)
            ->value('value'));
        $this->assertNotNull(PlatformAuditLog::where('subscription_plan_id', $plan->id)->where('action', 'PLAN_UPDATED')->first());

        // Deactivate & activate
        $this->actingAs($admin, 'platform')
            ->post(route('platform.plans.deactivate', $plan))
            ->assertSessionHas('success');
        $this->assertFalse((bool) $plan->fresh()->is_active);
        $this->assertNotNull(PlatformAuditLog::where('subscription_plan_id', $plan->id)->where('action', 'PLAN_DEACTIVATED')->first());

        $this->actingAs($admin, 'platform')
            ->post(route('platform.plans.activate', $plan))
            ->assertSessionHas('success');
        $this->assertTrue((bool) $plan->fresh()->is_active);
        $this->assertNotNull(PlatformAuditLog::where('subscription_plan_id', $plan->id)->where('action', 'PLAN_ACTIVATED')->first());

        // Prevent deleting core tier plans or assigned plans
        $starterPlan = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $this->actingAs($admin, 'platform')
            ->delete(route('platform.plans.destroy', $starterPlan))
            ->assertSessionHas('error');
        $this->assertNotNull(SubscriptionPlan::find($starterPlan->id));

        [, $assignedCompany] = $this->createTenantOwnerCompany(Company::STATUS_ACTIVE, Company::ACCESS_PROFESSIONAL, $plan->id);
        $this->actingAs($admin, 'platform')
            ->delete(route('platform.plans.destroy', $plan))
            ->assertSessionHas('error');
        $this->assertNotNull(SubscriptionPlan::find($plan->id));

        // Unassign then unreferenced custom plan can be deleted safely
        $assignedCompany->update(['subscription_plan_id' => $starterPlan->id, 'access_type' => Company::ACCESS_STARTER]);
        $this->actingAs($admin, 'platform')
            ->delete(route('platform.plans.destroy', $plan))
            ->assertRedirect(route('platform.plans.index'));
        $this->assertNull(SubscriptionPlan::find($plan->id));
    }

    public function test_feature_catalog_crud_enable_disable_and_safe_deletion_protection(): void
    {
        $admin = $this->createPlatformAdmin();

        // Referenced feature cannot be deleted
        $referencedFeature = SubscriptionFeature::where('feature_key', 'module_products')->firstOrFail();
        $this->actingAs($admin, 'platform')
            ->delete(route('platform.features.destroy', $referencedFeature))
            ->assertSessionHas('error');
        $this->assertNotNull(SubscriptionFeature::find($referencedFeature->id));

        // Create new catalog feature
        $featKey = 'module_r21_test_' . strtolower(substr(uniqid(), -5));
        $this->actingAs($admin, 'platform')
            ->post('/platform/features', [
                'feature_key' => $featKey,
                'name' => 'Custom Barcode Export',
                'description' => 'Future printable labels',
                'category' => 'Advanced Capabilities',
                'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
                'default_value' => '1',
                'sort_order' => 910,
            ])
            ->assertRedirect(route('platform.features.index'))
            ->assertSessionHas('success');

        $feature = SubscriptionFeature::where('feature_key', $featKey)->firstOrFail();
        $this->assertNotNull(PlatformAuditLog::where('subscription_feature_id', $feature->id)->where('action', 'FEATURE_CREATED')->first());

        // Update feature
        $this->actingAs($admin, 'platform')
            ->put(route('platform.features.update', $feature), [
                'name' => 'Custom Barcode Labels v2',
                'description' => 'Updated description',
                'category' => 'Advanced Capabilities',
                'default_value' => '0',
                'sort_order' => 915,
            ])
            ->assertRedirect(route('platform.features.index'));
        $this->assertSame('Custom Barcode Labels v2', $feature->fresh()->name);
        $this->assertNotNull(PlatformAuditLog::where('subscription_feature_id', $feature->id)->where('action', 'FEATURE_UPDATED')->first());

        // Deactivate & reactivate feature
        $this->actingAs($admin, 'platform')
            ->post(route('platform.features.deactivate', $feature))
            ->assertSessionHas('success');
        $this->assertFalse((bool) $feature->fresh()->is_active);
        $this->assertNotNull(PlatformAuditLog::where('subscription_feature_id', $feature->id)->where('action', 'FEATURE_DEACTIVATED')->first());

        $this->actingAs($admin, 'platform')
            ->post(route('platform.features.activate', $feature))
            ->assertSessionHas('success');
        $this->assertTrue((bool) $feature->fresh()->is_active);
        $this->assertNotNull(PlatformAuditLog::where('subscription_feature_id', $feature->id)->where('action', 'FEATURE_ACTIVATED')->first());

        // Unreferenced feature deletes cleanly
        $this->actingAs($admin, 'platform')
            ->delete(route('platform.features.destroy', $feature))
            ->assertRedirect(route('platform.features.index'));
        $this->assertNull(SubscriptionFeature::find($feature->id));
    }

    public function test_entitlement_service_resolves_features_limits_unlimited_and_missing_plan_safely(): void
    {
        /** @var SubscriptionPlanService $planService */
        $planService = app(SubscriptionPlanService::class);
        $tenantCtx = app(TenantContext::class);

        $freePlan = SubscriptionPlan::where('code', Company::ACCESS_FREE)->firstOrFail();
        $entPlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->firstOrFail();

        [, $freeCo] = $this->createTenantOwnerCompany(Company::STATUS_ACTIVE, Company::ACCESS_FREE, $freePlan->id);
        [, $entCo] = $this->createTenantOwnerCompany(Company::STATUS_ACTIVE, Company::ACCESS_ENTERPRISE, $entPlan->id);

        // FREE company: boolean false for report exports, numeric finite limits
        $this->assertSame($freePlan->id, $planService->getCompanyPlan($freeCo)?->id);
        $this->assertTrue($planService->hasFeature($freeCo, 'module_products'));
        $this->assertFalse($planService->hasFeature($freeCo, 'module_report_exports'));
        $this->assertSame(2, $planService->getLimit($freeCo, 'max_users'));
        $this->assertSame(1, $planService->getLimit($freeCo, 'max_warehouses'));
        $this->assertFalse($planService->isUnlimited($freeCo, 'max_users'));

        // ENTERPRISE company: unlimited (`null`) representation without ambiguity
        $this->assertTrue($planService->hasFeature($entCo, 'module_report_exports'));
        $this->assertNull($planService->getLimit($entCo, 'max_users'));
        $this->assertTrue($planService->isUnlimited($entCo, 'max_users'));
        $this->assertNull($planService->getLimit($entCo, 'max_products'));
        $this->assertTrue($planService->isUnlimited($entCo, 'max_products'));

        // Tenant-scoped active plan isolation (ignores untrusted request parameters)
        $tenantCtx->setCompany((int) $freeCo->id);
        request()->merge(['company_id' => $entCo->id, 'subscription_plan_id' => $entPlan->id]);
        $activeTenantPlan = $planService->getActiveTenantPlan();
        $this->assertSame($freePlan->id, $activeTenantPlan?->id);
        $tenantCtx->clear();

        // Missing/deactivated plan fallback returns safe conservative defaults (no crash, limit = 0, never unlimited)
        $inactivePlan = SubscriptionPlan::create([
            'code' => 'DEAD_' . uniqid(),
            'tier' => Company::ACCESS_FREE,
            'name' => 'Dead Inactive Plan',
            'price' => 0,
            'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'is_active' => false,
            'is_recommended' => false,
            'sort_order' => 99,
        ]);
        [, $orphanCo] = $this->createTenantOwnerCompany(Company::STATUS_ACTIVE, Company::ACCESS_FREE, $inactivePlan->id);

        $orphanEntitlements = $planService->getCompanyEntitlements($orphanCo);
        $this->assertFalse($orphanEntitlements['has_plan']);
        $this->assertFalse($planService->hasFeature($orphanCo, 'module_products'));
        $this->assertSame(0, $planService->getLimit($orphanCo, 'max_users'));
        $this->assertFalse($planService->isUnlimited($orphanCo, 'max_users'));
    }

    public function test_customer_plan_assignment_preserves_lifecycle_status_and_expiration_unless_explicit(): void
    {
        $admin = $this->createPlatformAdmin();
        $starterPlan = SubscriptionPlan::where('code', Company::ACCESS_STARTER)->firstOrFail();
        $proPlan = SubscriptionPlan::where('code', Company::ACCESS_PROFESSIONAL)->firstOrFail();
        $entPlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->firstOrFail();

        // 1) Assigning/changing plan on PENDING_ACTIVATION company MUST preserve PENDING_ACTIVATION + existing expiration
        [, $pendingCo] = $this->createTenantOwnerCompany(Company::STATUS_PENDING_ACTIVATION, Company::ACCESS_FREE, null);
        $originalExp = $pendingCo->access_expires_at->toDateTimeString();

        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.assign-plan', $pendingCo), [
                'subscription_plan_id' => $proPlan->id,
                'note' => 'Pre-assign Pro tier while still pending verification',
            ])
            ->assertRedirect(route('platform.customers.show', $pendingCo));

        $pendingCo->refresh();
        $this->assertSame($proPlan->id, (int) $pendingCo->subscription_plan_id);
        $this->assertSame(Company::ACCESS_PROFESSIONAL, $pendingCo->access_type);
        $this->assertSame(Company::STATUS_PENDING_ACTIVATION, $pendingCo->account_status);
        $this->assertFalse($pendingCo->is_active);
        $this->assertSame($originalExp, $pendingCo->access_expires_at->toDateTimeString());
        $this->assertNotNull(PlatformAuditLog::where('company_id', $pendingCo->id)->where('action', 'COMPANY_PLAN_ASSIGNED')->first());

        // 2) Assigning plan on SUSPENDED company MUST preserve SUSPENDED status
        [, $suspendedCo] = $this->createTenantOwnerCompany(Company::STATUS_SUSPENDED, Company::ACCESS_STARTER, $starterPlan->id);
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.assign-plan', $suspendedCo), [
                'subscription_plan_id' => $entPlan->id,
                'note' => 'Upgraded contract tier before billing reactivation',
            ])
            ->assertRedirect(route('platform.customers.show', $suspendedCo));

        $suspendedCo->refresh();
        $this->assertSame($entPlan->id, (int) $suspendedCo->subscription_plan_id);
        $this->assertSame(Company::ACCESS_ENTERPRISE, $suspendedCo->access_type);
        $this->assertSame(Company::STATUS_SUSPENDED, $suspendedCo->account_status);
        $this->assertFalse($suspendedCo->is_active);
        $this->assertNotNull(PlatformAuditLog::where('company_id', $suspendedCo->id)->where('action', 'COMPANY_PLAN_CHANGED')->first());

        // 3) Explicitly providing new expiration date updates it cleanly; assigning inactive plan fails cleanly
        $newExpiry = now()->addMonths(6)->toDateString();
        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.assign-plan', $suspendedCo), [
                'subscription_plan_id' => $proPlan->id,
                'access_expires_at' => $newExpiry,
                'note' => 'Explicit expiration update on plan switch',
            ])
            ->assertSessionHas('success');
        $this->assertSame($newExpiry, $suspendedCo->fresh()->access_expires_at->toDateString());

        $inactivePlan = SubscriptionPlan::create([
            'code' => 'OFF_' . uniqid(),
            'tier' => Company::ACCESS_FREE,
            'name' => 'Archived Tier',
            'price' => 0,
            'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'is_active' => false,
            'is_recommended' => false,
            'sort_order' => 99,
        ]);

        $this->actingAs($admin, 'platform')
            ->post(route('platform.customers.assign-plan', $suspendedCo), [
                'subscription_plan_id' => $inactivePlan->id,
            ])
            ->assertSessionHas('error');

        // Customer detail shows current plan and resolved limits cleanly
        $this->actingAs($admin, 'platform')
            ->get(route('platform.customers.show', $suspendedCo))
            ->assertOk()
            ->assertSee($proPlan->name)
            ->assertSee($proPlan->formattedPrice());
    }

    public function test_plan_comparison_reads_database_without_leaking_admin_notes_and_security_isolation_holds(): void
    {
        $admin = $this->createPlatformAdmin();
        [$tenantOwner, $company] = $this->createTenantOwnerCompany(Company::STATUS_ACTIVE, Company::ACCESS_FREE, null);
        $company->update(['admin_notes' => 'CONFIDENTIAL-ADMIN-NOTE-R21-' . uniqid()]);

        // Public comparison renders all 4 database-driven tiers cleanly without leaking admin notes
        $proPlan = SubscriptionPlan::where('code', Company::ACCESS_PROFESSIONAL)->firstOrFail();
        $proPlan->update(['price' => 888000]);

        $this->get('/plans')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Starter')
            ->assertSee('Professional')
            ->assertSee('Enterprise')
            ->assertSee('888.000')
            ->assertSee('Unlimited')
            ->assertDontSee($company->admin_notes);

        // Tenant OWNER cannot access platform plan/feature management routes
        $this->actingAs($tenantOwner, 'web')
            ->withSession(['active_company_id' => $company->id])
            ->get('/platform/plans')
            ->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->get('/platform/plans/create')->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->get('/platform/features')->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->post(route('platform.customers.assign-plan', $company), ['subscription_plan_id' => $proPlan->id])->assertRedirect(route('platform.login'));
        $this->actingAs($tenantOwner, 'web')->getJson('/platform/plans')->assertStatus(401);

        // Guest blocked cleanly
        $this->actingAs($tenantOwner, 'web')->post('/logout');
        $this->get('/platform/plans')->assertRedirect(route('platform.login'));
        $this->get('/platform/features')->assertRedirect(route('platform.login'));

        // Seeder idempotency does not overwrite customized price or alter DEFAULT/DEMOQA/PT-NUSANTARA-LOGIS status
        (new SubscriptionPlanSeeder())->run();
        $this->assertSame(888000.00, (float) $proPlan->fresh()->price);
        foreach (['DEFAULT', 'DEMOQA', 'PT-NUSANTARA-LOGIS'] as $code) {
            $co = Company::where('code', $code)->first();
            if ($co) {
                $this->assertSame(Company::STATUS_ACTIVE, $co->account_status);
                $this->assertNotNull($co->subscription_plan_id);
            }
        }
    }
}
