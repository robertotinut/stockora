<?php

namespace Tests\Feature;

use App\Exceptions\QuotaExceededException;
use App\Models\Category;
use App\Models\Company;
use App\Models\PlatformAdmin;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SubscriptionCatalog;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class Round22FeatureEnforcementTest extends TestCase
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
            'name' => 'R22 Operator',
            'email' => 'r22-op-' . uniqid() . '@stockora.app',
            'password' => Hash::make('PlatformStrong2026!'),
            'is_active' => true,
        ]);
    }

    private function createWorkspaceWithPlan(SubscriptionPlan $plan): array
    {
        $tag = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $owner = User::factory()->create([
            'email' => "owner-{$tag}@r22.test",
            'name' => "Owner {$tag}",
        ]);

        $company = Company::create([
            'code' => "R22{$tag}",
            'name' => "PT R22 {$tag}",
            'is_active' => true,
            'account_status' => Company::STATUS_ACTIVE,
            'access_type' => $plan->tier,
            'subscription_plan_id' => $plan->id,
            'access_starts_at' => now()->subDays(2),
            'access_expires_at' => now()->addDays(60),
        ]);

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $whMain = app(TenantContext::class)->withoutScope(fn () => Warehouse::create([
            'company_id' => $company->id,
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'is_default' => true,
            'is_active' => true,
        ]));

        return [$owner, $company, $whMain];
    }

    private function seedTenantRecords(Company $company, User $owner, Warehouse $whMain): array
    {
        return app(TenantContext::class)->withoutScope(function () use ($company, $owner, $whMain): array {
            $category = Category::create([
                'company_id' => $company->id,
                'code' => 'CAT-R22',
                'name' => 'General Category',
            ]);
            $pcs = Unit::create([
                'company_id' => $company->id,
                'name' => 'Piece',
                'symbol' => 'pcs',
            ]);
            $box = Unit::create([
                'company_id' => $company->id,
                'name' => 'Box',
                'symbol' => 'box',
            ]);
            $conversion = UnitConversion::create([
                'company_id' => $company->id,
                'from_unit_id' => $box->id,
                'to_unit_id' => $pcs->id,
                'factor' => '12.000000',
                'is_active' => 1,
            ]);
            $supplier = Supplier::create([
                'company_id' => $company->id,
                'code' => 'SUP-R22',
                'name' => 'PT Pemasok Uji',
            ]);
            $product = Product::create([
                'company_id' => $company->id,
                'code' => 'PRD-R22-1',
                'name' => 'Test Paper Ream',
                'category_id' => $category->id,
                'unit_id' => $pcs->id,
                'purchase_price' => '100.000000',
                'selling_price' => '150.000000',
                'stock' => '0.0000',
                'min_stock' => '2.0000',
                'legacy_reconciliation_status' => 'CHAIN_CONSISTENT',
            ]);
            $purchase = Purchase::create([
                'company_id' => $company->id,
                'purchase_number' => 'PO-R22-BASE',
                'supplier_id' => $supplier->id,
                'user_id' => $owner->id,
                'date' => now()->toDateString(),
                'total_amount' => '1000.0000',
                'status' => 'ordered',
                'payment_status' => 'unpaid',
            ]);
            $purchaseItem = PurchaseItem::create([
                'company_id' => $company->id,
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'unit_id' => $pcs->id,
                'quantity' => '10.0000',
                'received_quantity' => '0.0000',
                'unit_price' => '100.000000',
                'subtotal' => '1000.0000',
            ]);

            return compact('category', 'pcs', 'box', 'conversion', 'supplier', 'product', 'purchase', 'purchaseItem');
        });
    }

    private function createCustomPlan(PlatformAdmin $admin, array $featureOverrides = []): SubscriptionPlan
    {
        $featuresInput = [];
        foreach (SubscriptionCatalog::FEATURES as $def) {
            $key = $def['feature_key'];
            if (array_key_exists($key, $featureOverrides)) {
                $featuresInput[$key] = $featureOverrides[$key];
            } elseif ($def['feature_type'] === SubscriptionFeature::TYPE_BOOLEAN) {
                $featuresInput[$key] = '1';
            } elseif ($def['feature_type'] === SubscriptionFeature::TYPE_LIMIT) {
                $featuresInput[$key] = null;
            } else {
                $featuresInput[$key] = 'Custom Test Support';
            }
        }

        return app(SubscriptionPlanService::class)->createPlan($admin, [
            'code' => 'R22_TEST_' . strtoupper(substr(uniqid(), -5)),
            'tier' => Company::ACCESS_PROFESSIONAL,
            'name' => 'R22 Custom Plan ' . uniqid(),
            'description' => 'Test plan for Round 22 enforcement',
            'price' => 123000,
            'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
            'is_active' => true,
            'is_recommended' => false,
            'sort_order' => 99,
        ], $featuresInput);
    }

    public function test_all_17_boolean_modules_are_enforced_across_web_writes_and_inventory_json_api(): void
    {
        $admin = $this->createPlatformAdmin();
        $lockedOverrides = [];
        foreach (SubscriptionCatalog::FEATURES as $def) {
            if ($def['feature_type'] === SubscriptionFeature::TYPE_BOOLEAN) {
                $lockedOverrides[$def['feature_key']] = '0';
            }
        }

        $zeroModulesPlan = $this->createCustomPlan($admin, $lockedOverrides);
        [$owner, $company, $whMain] = $this->createWorkspaceWithPlan($zeroModulesPlan);
        $records = $this->seedTenantRecords($company, $owner, $whMain);
        $session = ['active_company_id' => $company->id];

        // 1. module_products (products, categories, units)
        $this->actingAs($owner)->withSession($session)->get('/products')->assertForbidden()->assertSee('Feature Not Included in Plan');
        $this->actingAs($owner)->withSession($session)->get('/categories')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->get('/units')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/products', [])->assertForbidden();

        // 2. module_warehouses
        $this->actingAs($owner)->withSession($session)->get('/warehouses')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/warehouses', [])->assertForbidden();

        // 3. module_stock_monitoring
        $this->actingAs($owner)->withSession($session)->get('/inventory/warehouses')->assertForbidden();

        // 4. module_stock_adjustments (web + JSON API + reversal)
        $this->actingAs($owner)->withSession($session)->get('/adjustments')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/adjustments', [])->assertForbidden();
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/adjustments', [])->assertForbidden()->assertJsonPath('code', 'FEATURE_LOCKED');

        // 5. module_stock_transfers (web + JSON API)
        $this->actingAs($owner)->withSession($session)->get('/transfers')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/transfers', [])->assertForbidden();
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/transfers', [])->assertForbidden()->assertJsonPath('code', 'FEATURE_LOCKED');

        // 6. module_stock_issues (web + JSON API)
        $this->actingAs($owner)->withSession($session)->get('/issues')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/issues', [])->assertForbidden();
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/issues', [])->assertForbidden()->assertJsonPath('code', 'FEATURE_LOCKED');

        // 7. module_stock_movements
        $this->actingAs($owner)->withSession($session)->get('/stock-movements')->assertForbidden();

        // 8. module_suppliers
        $this->actingAs($owner)->withSession($session)->get('/suppliers')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/suppliers', [])->assertForbidden();

        // 9. module_purchase_orders
        $this->actingAs($owner)->withSession($session)->get('/purchases')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/purchases', [])->assertForbidden();

        // 10. module_grn (web + JSON API)
        $this->actingAs($owner)->withSession($session)->get('/grns')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/grns', [])->assertForbidden();
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/grn', [])->assertForbidden()->assertJsonPath('code', 'FEATURE_LOCKED');

        // 11. module_inventory_reports
        $this->actingAs($owner)->withSession($session)->get('/reports')->assertForbidden();

        // 12. module_report_exports
        $this->actingAs($owner)->withSession($session)->get('/reports/export?type=valuation')->assertForbidden();

        // 13. module_reconciliation_audits
        $this->actingAs($owner)->withSession($session)->get('/reconciliation-audits')->assertForbidden();

        // 14. module_team_management
        $this->actingAs($owner)->withSession($session)->get('/team')->assertForbidden();

        // 15. module_role_permissions
        $this->actingAs($owner)->withSession($session)->get('/team/permissions')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->put('/team/permissions/staff', [])->assertForbidden();

        // 16. module_team_invitations
        $this->actingAs($owner)->withSession($session)->get('/team/invitations')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->post('/team/invitations', [])->assertForbidden();

        // 17. module_unit_conversions
        $this->actingAs($owner)->withSession($session)->post('/unit-conversions', [])->assertForbidden();
        $this->actingAs($owner)->withSession($session)->delete('/unit-conversions/' . $records['conversion']->id)->assertForbidden();

        // JSON Reversal endpoint checks corresponding module feature
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/reversals', [
            'document_type' => 'PurchaseReceipt',
            'document_id' => 999999,
            'adjustment_number' => 'REV-LOCKED-1',
            'idempotency_key' => 'idem-rev-locked-1',
            'reason' => 'Locked test',
        ])->assertForbidden()->assertJsonPath('code', 'FEATURE_LOCKED')->assertJsonPath('feature', 'module_grn');
    }

    public function test_combined_plan_and_rbac_enforcement_and_upgrade_unlocks_immediately(): void
    {
        $admin = $this->createPlatformAdmin();
        $freePlan = SubscriptionPlan::where('code', Company::ACCESS_FREE)->firstOrFail();
        $enterprisePlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->firstOrFail();

        [$owner, $company, $whMain] = $this->createWorkspaceWithPlan($freePlan);
        $session = ['active_company_id' => $company->id];

        $staff = User::factory()->create(['email' => 'staff-' . uniqid() . '@r22.test']);
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'role' => 'staff',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Case A: OWNER has full RBAC, but FREE plan locks these modules -> 403 FEATURE_LOCKED
        $this->actingAs($owner)->withSession($session)->get('/transfers')->assertForbidden()->assertSee('Feature Not Included in Plan');
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/transfers', [])->assertForbidden()->assertJsonPath('code', 'FEATURE_LOCKED');
        $this->actingAs($owner)->withSession($session)->get('/issues')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->get('/reports/export?type=valuation')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->get('/reconciliation-audits')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->get('/team/permissions')->assertForbidden();
        $this->actingAs($owner)->withSession($session)->get('/team/invitations')->assertForbidden();

        // Case B: Upgrade FREE -> ENTERPRISE immediately unlocks owner modules
        app(SubscriptionPlanService::class)->assignPlanToCompany($company, $enterprisePlan, $admin);

        $this->actingAs($owner)->withSession($session)->get('/transfers')->assertOk();
        $this->actingAs($owner)->withSession($session)->get('/issues')->assertOk();
        $this->actingAs($owner)->withSession($session)->get('/reports/export?type=valuation')->assertOk();
        $this->actingAs($owner)->withSession($session)->get('/reconciliation-audits')->assertOk();
        $this->actingAs($owner)->withSession($session)->get('/team/permissions')->assertOk();
        $this->actingAs($owner)->withSession($session)->get('/team/invitations')->assertOk();

        // Case C: On ENTERPRISE plan (modules enabled), STAFF still hits 403 when RBAC denies action
        $this->actingAs($staff)->withSession($session)->get('/team/permissions')->assertForbidden();
        $this->actingAs($staff)->withSession($session)->get('/reports/export?type=valuation')->assertForbidden();
    }

    public function test_all_six_resource_quotas_enforced_with_seat_reservation_reactivation_batch_and_exemptions(): void
    {
        Notification::fake();

        $admin = $this->createPlatformAdmin();
        $restrictedPlan = $this->createCustomPlan($admin, [
            'max_users' => '2',
            'max_warehouses' => '1',
            'max_products' => '1',
            'max_suppliers' => '1',
            'max_monthly_purchases' => '1',
            'max_monthly_stock_movements' => '2',
        ]);

        [$owner, $company, $whMain] = $this->createWorkspaceWithPlan($restrictedPlan);
        $session = ['active_company_id' => $company->id];

        $category = app(TenantContext::class)->withoutScope(fn () => Category::create([
            'company_id' => $company->id,
            'code' => 'CAT-Q',
            'name' => 'Quota Category',
        ]));
        $pcs = app(TenantContext::class)->withoutScope(fn () => Unit::create([
            'company_id' => $company->id,
            'name' => 'Piece',
            'symbol' => 'pcs',
        ]));

        // 1. max_users = 2: owner occupies 1 seat. 1 pending invitation reserves seat 2.
        $this->actingAs($owner)->withSession($session)
            ->post(route('team.invitations.store'), [
                'email' => 'invitee1-' . uniqid() . '@r22.test',
                'role' => 'staff',
            ])->assertRedirect();

        // Seat 3 (2nd pending invite) blocked!
        $this->actingAs($owner)->withSession($session)
            ->from(route('team.invitations.index'))
            ->post(route('team.invitations.store'), [
                'email' => 'invitee2-' . uniqid() . '@r22.test',
                'role' => 'staff',
            ])->assertSessionHas('error');

        // Revoking pending invite frees seat 2; adding 1 active user fills seat 2, so reactivating a 3rd inactive user fails!
        $inviteId = DB::table('company_invitations')->where('company_id', $company->id)->value('id');
        $this->actingAs($owner)->withSession($session)
            ->delete(route('team.invitations.destroy', $inviteId))
            ->assertRedirect();

        $staffActive = User::factory()->create();
        $staffInactive = User::factory()->create();
        DB::table('company_user')->insert([
            [
                'company_id' => $company->id,
                'user_id' => $staffActive->id,
                'role' => 'staff',
                'is_default' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => $company->id,
                'user_id' => $staffInactive->id,
                'role' => 'staff',
                'is_default' => 1,
                'is_active' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $inactiveMemberId = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $staffInactive->id)
            ->value('id');

        $this->actingAs($owner)->withSession($session)
            ->patch(route('team.members.status', $inactiveMemberId), ['is_active' => '1'])
            ->assertSessionHas('error');

        // 2. max_warehouses = 1 (WH-MAIN is active)
        $this->actingAs($owner)->withSession($session)
            ->post(route('warehouses.store'), [
                'code' => 'WH-BLOCKED',
                'name' => 'Blocked Active Site',
                'is_active' => 1,
            ])->assertSessionHas('error');

        $this->actingAs($owner)->withSession($session)
            ->postJson(route('warehouses.store'), [
                'code' => 'WH-BLOCKED-JSON',
                'name' => 'Blocked JSON Site',
                'is_active' => 1,
            ])->assertStatus(422)->assertJsonPath('code', 'QUOTA_EXCEEDED')->assertJsonPath('limit_key', 'max_warehouses');

        // Inactive (archived) warehouse creation succeeds, but reactivating it fails while WH-MAIN uses slot 1/1
        $this->actingAs($owner)->withSession($session)
            ->post(route('warehouses.store'), [
                'code' => 'WH-ARCHIVED',
                'name' => 'Archived Site',
                'is_active' => 0,
            ])->assertRedirect(route('warehouses.index'));

        $archivedWh = app(TenantContext::class)->withoutScope(fn () => Warehouse::where('company_id', $company->id)->where('code', 'WH-ARCHIVED')->firstOrFail());
        $this->actingAs($owner)->withSession($session)
            ->put(route('warehouses.update', $archivedWh), [
                'code' => 'WH-ARCHIVED',
                'name' => 'Archived Site',
                'is_active' => 1,
            ])->assertSessionHas('error');

        // 3. max_products = 1
        $this->actingAs($owner)->withSession($session)
            ->post(route('products.store'), [
                'code' => 'PRD-Q1',
                'name' => 'Quota Product 1',
                'category_id' => $category->id,
                'unit_id' => $pcs->id,
                'purchase_price' => 50,
                'selling_price' => 80,
                'min_stock' => 1,
            ])->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('products.store'), [
                'code' => 'PRD-Q2',
                'name' => 'Quota Product 2',
                'category_id' => $category->id,
                'unit_id' => $pcs->id,
                'purchase_price' => 50,
                'selling_price' => 80,
                'min_stock' => 1,
            ])->assertSessionHas('error');

        $product = app(TenantContext::class)->withoutScope(fn () => Product::where('company_id', $company->id)->where('code', 'PRD-Q1')->firstOrFail());

        // 4. max_suppliers = 1
        $this->actingAs($owner)->withSession($session)
            ->post(route('suppliers.store'), [
                'code' => 'SUP-Q1',
                'name' => 'Quota Supplier 1',
            ])->assertRedirect(route('suppliers.index'));

        $this->actingAs($owner)->withSession($session)
            ->post(route('suppliers.store'), [
                'code' => 'SUP-Q2',
                'name' => 'Quota Supplier 2',
            ])->assertSessionHas('error');

        $supplier = app(TenantContext::class)->withoutScope(fn () => Supplier::where('company_id', $company->id)->where('code', 'SUP-Q1')->firstOrFail());

        // 5. max_monthly_purchases = 1
        $this->actingAs($owner)->withSession($session)
            ->post(route('purchases.store'), [
                'purchase_number' => 'PO-Q1',
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'payment_status' => 'unpaid',
                'submit_action' => 'confirm',
                'items' => [[
                    'product_id' => $product->id,
                    'unit_id' => $pcs->id,
                    'quantity' => 10,
                    'unit_price' => 50,
                ]],
            ])->assertRedirect();

        $this->actingAs($owner)->withSession($session)
            ->post(route('purchases.store'), [
                'purchase_number' => 'PO-Q2',
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'payment_status' => 'unpaid',
                'items' => [[
                    'product_id' => $product->id,
                    'unit_id' => $pcs->id,
                    'quantity' => 5,
                    'unit_price' => 50,
                ]],
            ])->assertSessionHas('error');

        $purchase = app(TenantContext::class)->withoutScope(fn () => Purchase::where('company_id', $company->id)->latest('id')->firstOrFail());
        $this->assertNotSame('PO-Q1', $purchase->purchase_number);
        $this->assertStringStartsWith('PO-', $purchase->purchase_number);
        $purchaseItem = app(TenantContext::class)->withoutScope(fn () => PurchaseItem::where('purchase_id', $purchase->id)->firstOrFail());

        // 6. max_monthly_stock_movements = 2
        // Draft stock adjustment is exempt (0 movements added)
        $this->actingAs($owner)->withSession($session)
            ->post(route('adjustments.store'), [
                'warehouse_id' => $whMain->id,
                'adjustment_number' => 'ADJ-DRAFT-EXEMPT',
                'idempotency_key' => 'idem-adj-draft-q',
                'reason' => 'Draft inspection before count',
                'submit_action' => 'draft',
                'lines' => [['product_id' => $product->id, 'direction' => 'in', 'quantity' => 1, 'unit_cost' => 50]],
            ])->assertRedirect();

        // Post 1-line GRN -> consumes 1/2 monthly movements
        $grnPayload = [
            'purchase_id' => $purchase->id,
            'warehouse_id' => $whMain->id,
            'receipt_number' => 'GRN-Q1',
            'idempotency_key' => 'idem-grn-q1',
            'lines' => [[
                'purchase_item_id' => $purchaseItem->id,
                'received_unit_id' => $pcs->id,
                'received_quantity' => 10,
            ]],
        ];
        $grnRes = $this->actingAs($owner)->withSession($session)->postJson('/inventory/grn', $grnPayload)->assertStatus(201);
        $grnId = (int) $grnRes->json('receipt.id');

        // Raise limit to 3 so 2nd movement (2/3) succeeds via issue, leaving remaining = 1;
        // then test batch transfer (needs 2 movements) fails atomically, while idempotent replay & reversal remain exempt!
        $featureMov = SubscriptionFeature::where('feature_key', 'max_monthly_stock_movements')->firstOrFail();
        $featureWh = SubscriptionFeature::where('feature_key', 'max_warehouses')->firstOrFail();
        DB::table('subscription_plan_features')
            ->where('subscription_plan_id', $restrictedPlan->id)
            ->where('subscription_feature_id', $featureMov->id)
            ->update(['value' => '3']);
        DB::table('subscription_plan_features')
            ->where('subscription_plan_id', $restrictedPlan->id)
            ->where('subscription_feature_id', $featureWh->id)
            ->update(['value' => '2']);

        $whBranch = app(TenantContext::class)->withoutScope(fn () => Warehouse::create([
            'company_id' => $company->id,
            'code' => 'WH-Q2',
            'name' => 'Branch Q2',
            'is_active' => 1,
            'is_default' => 0,
        ]));

        // Post 1 issue (usage = 2/3, remaining = 1)
        $issRes = $this->actingAs($owner)->withSession($session)->postJson('/inventory/issues', [
            'warehouse_id' => $whMain->id,
            'issue_number' => 'ISS-Q1',
            'idempotency_key' => 'idem-iss-q1',
            'lines' => [['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2]],
        ])->assertStatus(201);
        $issueId = (int) $issRes->json('issue_id');

        // 1-line transfer requires 2 movements (2 + 2 = 4 > 3) -> atomic rejection (422 QUOTA_EXCEEDED)
        $this->actingAs($owner)->withSession($session)->postJson('/inventory/transfers', [
            'from_warehouse_id' => $whMain->id,
            'to_warehouse_id' => $whBranch->id,
            'transfer_number' => 'TRF-OVER-QUOTA',
            'idempotency_key' => 'idem-trf-over-q',
            'lines' => [['product_id' => $product->id, 'unit_id' => $pcs->id, 'quantity' => 2]],
        ])->assertStatus(422)
            ->assertJsonPath('code', 'QUOTA_EXCEEDED')
            ->assertJsonPath('limit_key', 'max_monthly_stock_movements')
            ->assertJsonPath('requested_delta', 2);

        // Verify zero partial ledger rows inserted for failed transfer
        $movCountBeforeExempt = app(TenantContext::class)->withoutScope(fn () => StockMovement::where('company_id', $company->id)->count());
        $this->assertSame(2, $movCountBeforeExempt);

        // Push usage to 3/3 by posting the saved draft adjustment
        $draftAdj = app(TenantContext::class)->withoutScope(fn () => StockAdjustment::where('company_id', $company->id)->where('idempotency_key', 'idem-adj-draft-q')->firstOrFail());
        $this->assertNotSame('ADJ-DRAFT-EXEMPT', $draftAdj->adjustment_number);
        $this->assertStringStartsWith('ADJ-', $draftAdj->adjustment_number);
        $this->actingAs($owner)->withSession($session)->post(route('adjustments.post', $draftAdj))->assertRedirect();
        $this->assertSame(3, app(TenantContext::class)->withoutScope(fn () => StockMovement::where('company_id', $company->id)->count()));

        // At 3/3 (remaining = 0): Idempotent GRN replay STILL returns 200 idempotent_replay = true
        $this->actingAs($owner)->withSession($session)
            ->postJson('/inventory/grn', $grnPayload)
            ->assertStatus(200)
            ->assertJsonPath('idempotent_replay', true);

        // At 3/3 (remaining = 0): Document reversal STILL allowed (exempt from blocking compensating entries)
        $this->actingAs($owner)->withSession($session)
            ->postJson('/inventory/reversals', [
                'document_type' => 'StockIssue',
                'document_id' => $issueId,
                'adjustment_number' => 'REV-ISS-EXEMPT',
                'idempotency_key' => 'idem-rev-iss-exempt',
                'reason' => 'Exempt reversal at quota limit',
            ])->assertStatus(201);
    }

    public function test_dashboard_usage_widget_quota_banner_and_platform_customer_usage_rendering(): void
    {
        $admin = $this->createPlatformAdmin();
        $freePlan = SubscriptionPlan::where('code', Company::ACCESS_FREE)->firstOrFail();

        [$owner, $company, $whMain] = $this->createWorkspaceWithPlan($freePlan);
        $session = ['active_company_id' => $company->id];

        // Dashboard displays #subscriptionUsageWidget with capacity rows
        $this->actingAs($owner)->withSession($session)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('id="subscriptionUsageWidget"', false)
            ->assertSee('Subscription &amp; Resource Usage', false)
            ->assertSee($freePlan->name);

        // Warehouses create form on Free (1/1 WH-MAIN active = reached) renders contextual quota-banner
        $this->actingAs($owner)->withSession($session)
            ->get(route('warehouses.create'))
            ->assertOk()
            ->assertSee('View Upgrade Options');

        // Platform Super Admin customer detail renders live usage counters & limits
        $this->actingAs($admin, 'platform')
            ->get(route('platform.customers.show', $company))
            ->assertOk()
            ->assertSee(__('subscription.limits.max_users', [], 'en'))
            ->assertSee(__('subscription.limits.max_warehouses', [], 'en'));
    }
}
