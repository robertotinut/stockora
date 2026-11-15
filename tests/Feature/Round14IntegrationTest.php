<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class Round14IntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        InventoryService::$simulateFailureAt = null;
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function createUserInCompany(Company $company, string $emailPrefix = 'user'): User
    {
        if ($company->access_type !== Company::ACCESS_ENTERPRISE) {
            $enterprisePlan = SubscriptionPlan::where('code', Company::ACCESS_ENTERPRISE)->first();
            if ($enterprisePlan) {
                $company->forceFill([
                    'subscription_plan_id' => $enterprisePlan->id,
                    'access_type' => $enterprisePlan->tier,
                ])->save();
            }
        }

        $user = User::factory()->create(['email' => "{$emailPrefix}." . uniqid() . '@stockora.test']);
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_tenant_scope_fails_closed_when_context_is_unset(): void
    {
        $ctx = app(TenantContext::class);
        $ctx->clear();

        $this->assertSame(0, Product::count());
        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, Warehouse::count());
        $this->assertSame(0, StockMovement::count());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TENANT_CONTEXT_MISSING');
        Product::create(['company_id' => 50, 'code' => 'UNSCOPED', 'name' => 'No', 'category_id' => 1, 'unit_id' => 1]);
    }

    public function test_cross_company_isolation_blocks_idor_routes_formrequests_eloquent_and_service(): void
    {
        $companyA = Company::where('code', 'DEFAULT')->firstOrFail();
        $userA = $this->createUserInCompany($companyA, 'usera');

        $companyB = Company::create(['code' => 'TENANT-B-' . uniqid(), 'name' => 'Company B', 'is_active' => 1]);
        $userB = $this->createUserInCompany($companyB, 'userb');

        $ctx = app(TenantContext::class);
        $ctx->setCompany((int) $companyB->id, (int) $userB->id);

        $whB = Warehouse::create(['code' => 'WH-B', 'name' => 'Warehouse B', 'is_default' => 1, 'is_active' => 1]);
        $catB = Category::create(['code' => 'CAT-B', 'name' => 'Category B']);
        $unitB = Unit::create(['name' => 'Piece B', 'symbol' => 'pcb']);
        $supB = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B']);
        $prodB = Product::create([
            'code' => 'PRD-B-1', 'name' => 'Secret B Product',
            'category_id' => $catB->id, 'unit_id' => $unitB->id,
            'purchase_price' => '1000.000000', 'selling_price' => '1500.000000',
            'stock' => '0.0000', 'min_stock' => '1.0000',
        ]);
        $poB = Purchase::create([
            'purchase_number' => 'PO-B-001', 'supplier_id' => $supB->id,
            'user_id' => $userB->id, 'date' => now()->toDateString(),
            'total_amount' => '10000.0000', 'status' => 'ordered', 'payment_status' => 'unpaid',
        ]);
        $piB = PurchaseItem::create([
            'purchase_id' => $poB->id, 'product_id' => $prodB->id,
            'unit_id' => $unitB->id, 'quantity' => '10.0000',
            'unit_price' => '1000.000000', 'subtotal' => '10000.0000',
        ]);

        // 1. User A attempting to switch header into Company B is blocked (403)
        $this->actingAs($userA)
            ->withHeader('X-Company-Id', (string) $companyB->id)
            ->getJson('/dashboard')
            ->assertStatus(403);

        // 2. User A sending Company B's PO / Warehouse IDs to FormRequest is blocked (422)
        $this->actingAs($userA)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->postJson('/inventory/grn', [
                'purchase_id' => $poB->id,
                'warehouse_id' => $whB->id,
                'receipt_number' => 'GRN-IDOR-1',
                'idempotency_key' => 'idor-grn-1',
                'lines' => [['purchase_item_id' => $piB->id, 'received_quantity' => 5]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_id', 'warehouse_id', 'lines.0.purchase_item_id']);

        // 3. Direct Eloquent under Company A cannot see Company B's products or write cross-tenant row
        $ctx->setCompany((int) $companyA->id, (int) $userA->id);
        $this->assertNull(Product::find($prodB->id));
        try {
            Product::create([
                'company_id' => $companyB->id,
                'code' => 'HIJACK',
                'name' => 'Cross Tenant Write',
                'category_id' => $catB->id,
                'unit_id' => $unitB->id,
            ]);
            $this->fail('Expected CROSS_TENANT_WRITE_DENIED');
        } catch (DomainException $e) {
            $this->assertStringContainsString('CROSS_TENANT_WRITE_DENIED', $e->getMessage());
        }

        // 4. Direct service layer rejects cross-tenant PO access even bypassing HTTP
        try {
            app(InventoryService::class)->postPurchaseReceipt(
                (int) $companyA->id,
                (int) $userA->id,
                (int) $poB->id,
                (int) $whB->id,
                'GRN-SVC-IDOR',
                'svc-idor-1',
                [['purchase_item_id' => $piB->id, 'received_quantity' => 1]]
            );
            $this->fail('Expected PURCHASE_NOT_FOUND');
        } catch (DomainException $e) {
            $this->assertStringContainsString('PURCHASE_NOT_FOUND', $e->getMessage());
        }
    }

    public function test_dashboard_excludes_quarantined_chain_break_and_tracks_valuation_basis(): void
    {
        $company = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createUserInCompany($company, 'dash');
        $this->assertNotEquals(1, $company->id, 'Test specifically uses company_id != 1');

        $ctx = app(TenantContext::class);
        $ctx->setCompany((int) $company->id, (int) $user->id);

        // Sum expected trusted baseline (#2..#8 excluding quarantined Product #1)
        $expectedBaseline = '0.0000';
        $pws = ProductWarehouse::where('valuation_basis', 'ESTIMATED_LEGACY_BASELINE')->get();
        foreach ($pws as $pw) {
            $expectedBaseline = bcadd($expectedBaseline, (string) $pw->inventory_value, 4);
        }

        $res = $this->actingAs($user)->getJson('/dashboard');
        $res->assertStatus(200)
            ->assertJsonPath('company_id', (int) $company->id)
            ->assertJsonPath('quarantined_products_count', 1)
            ->assertJsonPath('quarantined_legacy_stock_excluded', '140.0000')
            ->assertJsonPath('purchase_items_needing_review', 3)
            ->assertJsonPath('valuation_breakdown.estimated_legacy_baseline', $expectedBaseline)
            ->assertJsonPath('valuation_breakdown.verified_acquisition', '0.0000')
            ->assertJsonPath('trusted_inventory_value', $expectedBaseline);

        // Quarantined Product #1 cannot be issued or adjusted until resolved
        $whMain = Warehouse::where('is_default', 1)->firstOrFail();
        $this->assertNotEquals(1, $whMain->id, 'Test specifically uses warehouse_id != 1');

        $this->actingAs($user)->postJson('/inventory/issues', [
            'warehouse_id' => $whMain->id,
            'issue_number' => 'ISS-QUAR-1',
            'idempotency_key' => 'iss-quar-1',
            'lines' => [['product_id' => 1, 'quantity' => 5]],
        ])->assertStatus(422)
            ->assertJsonPath('error', fn ($v) => str_contains($v, 'QUARANTINED_CHAIN_BREAK_PRODUCT'));
    }

    public function test_end_to_end_grn_unit_conversion_over_receive_transfer_issue_and_db_triggers(): void
    {
        $company = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createUserInCompany($company, 'ops');
        $ctx = app(TenantContext::class);
        $ctx->setCompany((int) $company->id, (int) $user->id);

        $whMain = Warehouse::where('is_default', 1)->firstOrFail();
        $whSub = Warehouse::create(['code' => 'WH-BRANCH', 'name' => 'Branch Warehouse', 'is_default' => 0, 'is_active' => 1]);
        $cat = Category::firstOrFail();
        $pcs = Unit::where('symbol', 'pcs')->firstOrFail();
        $box = Unit::where('symbol', 'box')->firstOrFail();
        $sup = Supplier::firstOrFail();

        $prod = Product::create([
            'code' => 'PRD-E2E-01', 'name' => 'Paper Boxed Item',
            'category_id' => $cat->id, 'unit_id' => $pcs->id,
            'purchase_price' => '0.000000', 'selling_price' => '200.000000',
            'stock' => '0.0000', 'min_stock' => '2.0000',
        ]);
        UnitConversion::create([
            'product_id' => $prod->id, 'from_unit_id' => $box->id,
            'to_unit_id' => $pcs->id, 'factor' => '12.00000000', 'is_active' => 1,
        ]);

        $po = Purchase::create([
            'purchase_number' => 'PO-E2E-1', 'supplier_id' => $sup->id,
            'user_id' => $user->id, 'date' => now()->toDateString(),
            'total_amount' => '6000.0000', 'status' => 'ordered', 'payment_status' => 'unpaid',
        ]);
        $pi = PurchaseItem::create([
            'purchase_id' => $po->id, 'product_id' => $prod->id,
            'unit_id' => $box->id, 'quantity' => '5.0000',
            'unit_price' => '1200.000000', 'subtotal' => '6000.0000',
        ]);

        // GRN #1: 2 boxes = 24 pcs | Capitalized = 2*1200 - 100 + 50 + 20 = 2370.0000 (recoverable VAT 110 excluded!)
        $grnRes = $this->actingAs($user)->postJson('/inventory/grn', [
            'purchase_id' => $po->id,
            'warehouse_id' => $whMain->id,
            'receipt_number' => 'GRN-E2E-1',
            'idempotency_key' => 'idem-grn-e2e-1',
            'lines' => [[
                'purchase_item_id' => $pi->id,
                'received_unit_id' => $box->id,
                'received_quantity' => 2,
                'unit_price' => 1200,
                'discount_amount' => 100,
                'allocated_freight_amount' => 50,
                'non_recoverable_tax_amount' => 20,
                'recoverable_tax_amount' => 110,
            ]],
        ]);
        $grnRes->assertStatus(201)->assertJsonPath('po_status', 'partial')
            ->assertJsonPath('receipt.total_capitalized_cost', '2370.0000');

        $pwMain = ProductWarehouse::where('product_id', $prod->id)->where('warehouse_id', $whMain->id)->firstOrFail();
        $this->assertSame('24.0000', $pwMain->stock);
        $this->assertSame('2370.0000', $pwMain->inventory_value);
        $this->assertSame('98.750000', $pwMain->moving_average_cost);
        $this->assertSame('VERIFIED_ACQUISITION', $pwMain->valuation_basis);

        // Idempotent replay on identical key -> 200 & zero double count
        $this->actingAs($user)->postJson('/inventory/grn', [
            'purchase_id' => $po->id,
            'warehouse_id' => $whMain->id,
            'receipt_number' => 'GRN-E2E-1',
            'idempotency_key' => 'idem-grn-e2e-1',
            'lines' => [['purchase_item_id' => $pi->id, 'received_unit_id' => $box->id, 'received_quantity' => 2]],
        ])->assertStatus(200)->assertJsonPath('idempotent_replay', true);

        // Over-receiving (4 more boxes when ordered=5, received=2) -> blocked 422
        $this->actingAs($user)->postJson('/inventory/grn', [
            'purchase_id' => $po->id,
            'warehouse_id' => $whMain->id,
            'receipt_number' => 'GRN-E2E-OVER',
            'idempotency_key' => 'idem-grn-over',
            'lines' => [['purchase_item_id' => $pi->id, 'received_unit_id' => $box->id, 'received_quantity' => 4]],
        ])->assertStatus(422);

        // Transfer 1 box (12 pcs) to WH-BRANCH -> 100% value conserved (1185.0000 moved out & in)
        $xferRes = $this->actingAs($user)->postJson('/inventory/transfers', [
            'from_warehouse_id' => $whMain->id,
            'to_warehouse_id' => $whSub->id,
            'transfer_number' => 'XFER-E2E-1',
            'idempotency_key' => 'idem-xfer-e2e-1',
            'lines' => [['product_id' => $prod->id, 'unit_id' => $box->id, 'quantity' => 1]],
        ]);
        $xferRes->assertStatus(201)->assertJsonPath('status', 'posted');

        $pwMain->refresh();
        $pwSub = ProductWarehouse::where('product_id', $prod->id)->where('warehouse_id', $whSub->id)->firstOrFail();
        $this->assertSame('12.0000', $pwMain->stock);
        $this->assertSame('12.0000', $pwSub->stock);
        $this->assertSame('1185.0000', $pwMain->inventory_value);
        $this->assertSame('1185.0000', $pwSub->inventory_value);

        // Issue 12 pcs from WH-BRANCH (full depletion) + verify reversal & double-reversal block
        $issRes = $this->actingAs($user)->postJson('/inventory/issues', [
            'warehouse_id' => $whSub->id,
            'issue_number' => 'ISS-E2E-1',
            'idempotency_key' => 'idem-iss-e2e-1',
            'lines' => [['product_id' => $prod->id, 'unit_id' => $pcs->id, 'quantity' => 12]],
        ]);
        $issRes->assertStatus(201);
        $issueId = (int) $issRes->json('issue_id');

        $pwSub->refresh();
        $this->assertSame('0.0000', $pwSub->stock);
        $this->assertSame('0.0000', $pwSub->inventory_value);

        // Reverse StockIssue once -> restores 12 pcs @ 1185.0000
        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'StockIssue',
            'document_id' => $issueId,
            'adjustment_number' => 'REV-ISS-1',
            'idempotency_key' => 'idem-rev-iss-1',
            'reason' => 'Wrong warehouse issue',
        ])->assertStatus(201);

        $pwSub->refresh();
        $this->assertSame('12.0000', $pwSub->stock);
        $this->assertSame('1185.0000', $pwSub->inventory_value);

        // Reversal idempotency replay -> 200 idempotent_replay=true
        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'StockIssue',
            'document_id' => $issueId,
            'adjustment_number' => 'REV-ISS-1',
            'idempotency_key' => 'idem-rev-iss-1',
            'reason' => 'Wrong warehouse issue',
        ])->assertStatus(200)->assertJsonPath('idempotent_replay', true);

        // Attempt SECOND reversal of same StockIssue with NEW key -> 422 DOCUMENT_ALREADY_REVERSED
        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'StockIssue',
            'document_id' => $issueId,
            'adjustment_number' => 'REV-ISS-2',
            'idempotency_key' => 'idem-rev-iss-2',
            'reason' => 'Double reverse attempt',
        ])->assertStatus(422)
            ->assertJsonPath('error', fn ($v) => str_contains($v, 'DOCUMENT_ALREADY_REVERSED'));

        // Attempt to reverse GRN #1 (24 pcs) while WH-MAIN only has 12 pcs left -> 422 INSUFFICIENT_STOCK!
        $grnId = (int) $grnRes->json('receipt.id');
        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'PurchaseReceipt',
            'document_id' => $grnId,
            'adjustment_number' => 'REV-GRN-FAIL',
            'idempotency_key' => 'idem-rev-grn-fail',
            'reason' => 'Cannot create negative stock',
        ])->assertStatus(422)
            ->assertJsonPath('error', fn ($v) => str_contains($v, 'INSUFFICIENT_STOCK'));

        // Raw DB trigger protection checks (append-only ledger & immutable posted GRN)
        try {
            DB::statement('UPDATE `stock_movements` SET `quantity` = 999 WHERE `company_id` = ? ORDER BY id DESC LIMIT 1', [$company->id]);
            $this->fail('Expected APPEND_ONLY_LEDGER trigger error');
        } catch (QueryException $e) {
            $this->assertStringContainsString('APPEND_ONLY_LEDGER', $e->getMessage());
        }

        try {
            DB::statement("UPDATE `purchase_receipts` SET `notes` = 'tamper' WHERE `id` = ?", [$grnId]);
            $this->fail('Expected IMMUTABLE_GRN trigger error');
        } catch (QueryException $e) {
            $this->assertStringContainsString('IMMUTABLE_GRN', $e->getMessage());
        }

        // Runtime app DB user lacks DDL grants (privilege separation verified on a separate PDO connection
        // because MySQL triggers an implicit COMMIT before/during DDL on the active session).
        $appDdlProbe = new \PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=stockora_sandbox;charset=utf8mb4',
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        try {
            $appDdlProbe->exec('ALTER TABLE `products` COMMENT = "hack"');
            $this->fail('Expected ALTER privilege denied (1142)');
        } catch (\PDOException $e) {
            $this->assertSame(1142, (int) ($e->errorInfo[1] ?? 0));
        }
    }

    public function test_atomic_rollback_on_simulated_failures_leaves_zero_partial_writes(): void
    {
        $company = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createUserInCompany($company, 'fail');
        $ctx = app(TenantContext::class);
        $ctx->setCompany((int) $company->id, (int) $user->id);

        $whMain = Warehouse::where('is_default', 1)->firstOrFail();
        $cat = Category::firstOrFail();
        $pcs = Unit::where('symbol', 'pcs')->firstOrFail();
        $sup = Supplier::firstOrFail();

        $prod = Product::create([
            'code' => 'PRD-FAIL-01', 'name' => 'Rollback Proof Item',
            'category_id' => $cat->id, 'unit_id' => $pcs->id,
            'purchase_price' => '0.000000', 'selling_price' => '200.000000',
            'stock' => '0.0000', 'min_stock' => '0.0000',
        ]);
        $po = Purchase::create([
            'purchase_number' => 'PO-FAIL-1', 'supplier_id' => $sup->id,
            'user_id' => $user->id, 'date' => now()->toDateString(),
            'total_amount' => '1000.0000', 'status' => 'ordered', 'payment_status' => 'unpaid',
        ]);
        $pi = PurchaseItem::create([
            'purchase_id' => $po->id, 'product_id' => $prod->id,
            'unit_id' => $pcs->id, 'quantity' => '10.0000',
            'unit_price' => '100.000000', 'subtotal' => '1000.0000',
        ]);

        $svc = app(InventoryService::class);
        $baseGrnCount = PurchaseReceipt::count();
        $baseSmCount = StockMovement::count();

        foreach (['during_ledger_insert', 'during_warehouse_update', 'after_status_posted'] as $idx => $stage) {
            InventoryService::$simulateFailureAt = $stage;
            try {
                $svc->postPurchaseReceipt(
                    (int) $company->id,
                    (int) $user->id,
                    (int) $po->id,
                    (int) $whMain->id,
                    "GRN-FAIL-{$idx}",
                    "idem-fail-{$stage}",
                    [['purchase_item_id' => $pi->id, 'received_unit_id' => $pcs->id, 'received_quantity' => 5]]
                );
                $this->fail("Expected simulated failure at {$stage}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString("SIMULATED_FAILURE_AT_{$stage}", $e->getMessage());
            } finally {
                InventoryService::$simulateFailureAt = null;
            }

            $po->refresh();
            $pi->refresh();
            $this->assertSame('ordered', $po->status, "PO status mutated after {$stage} failure");
            $this->assertSame('0.0000', $pi->received_quantity, "PO item received_quantity mutated after {$stage}");
            $this->assertSame($baseGrnCount, PurchaseReceipt::count(), "Partial GRN header persisted after {$stage}");
            $this->assertSame($baseSmCount, StockMovement::count(), "Partial movement persisted after {$stage}");
        }
    }

    public function test_mixed_valuation_basis_depletion_residual_and_all_reversal_paths(): void
    {
        $company = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createUserInCompany($company, 'mix');
        $ctx = app(TenantContext::class);
        $ctx->setCompany((int) $company->id, (int) $user->id);

        $whMain = Warehouse::where('is_default', 1)->firstOrFail();
        $whSub = Warehouse::create(['code' => 'WH-MIX-2', 'name' => 'Mix Warehouse 2', 'is_default' => 0, 'is_active' => 1]);
        $cat = Category::firstOrFail();
        $pcs = Unit::where('symbol', 'pcs')->firstOrFail();
        $sup = Supplier::firstOrFail();

        // 1. GRN into legacy ESTIMATED_LEGACY_BASELINE Product #2 -> MIXED_BASELINE_AND_ACQUISITION
        $prodLegacy2 = Product::findOrFail(2);
        $poMix = Purchase::create([
            'purchase_number' => 'PO-MIX-1', 'supplier_id' => $sup->id,
            'user_id' => $user->id, 'date' => now()->toDateString(),
            'total_amount' => '1000.0000', 'status' => 'ordered', 'payment_status' => 'unpaid',
        ]);
        $piMix = PurchaseItem::create([
            'purchase_id' => $poMix->id, 'product_id' => $prodLegacy2->id,
            'unit_id' => $prodLegacy2->unit_id, 'quantity' => '10.0000',
            'unit_price' => '100.000000', 'subtotal' => '1000.0000',
        ]);
        $this->actingAs($user)->postJson('/inventory/grn', [
            'purchase_id' => $poMix->id,
            'warehouse_id' => $whMain->id,
            'receipt_number' => 'GRN-MIX-1',
            'idempotency_key' => 'idem-grn-mix-1',
            'lines' => [['purchase_item_id' => $piMix->id, 'received_unit_id' => $prodLegacy2->unit_id, 'received_quantity' => 10]],
        ])->assertStatus(201)->assertJsonPath('po_status', 'received');

        $pw2 = ProductWarehouse::where('product_id', $prodLegacy2->id)->where('warehouse_id', $whMain->id)->firstOrFail();
        $this->assertSame('MIXED_BASELINE_AND_ACQUISITION', $pw2->valuation_basis);

        // 2. Depletion residual flush check on fractional MAC product (100 / 3 = 33.333333 -> 3 * 33.333333 = 100.0000, use 300.0001 / 7 = 42.857157)
        $prodFrac = Product::create([
            'code' => 'PRD-FRAC-1', 'name' => 'Fractional MAC Product',
            'category_id' => $cat->id, 'unit_id' => $pcs->id,
            'purchase_price' => '0.000000', 'selling_price' => '100.000000',
            'stock' => '0.0000', 'min_stock' => '0.0000',
        ]);
        $poFrac = Purchase::create([
            'purchase_number' => 'PO-FRAC-1', 'supplier_id' => $sup->id,
            'user_id' => $user->id, 'date' => now()->toDateString(),
            'total_amount' => '100.0000', 'status' => 'ordered', 'payment_status' => 'unpaid',
        ]);
        $piFrac = PurchaseItem::create([
            'purchase_id' => $poFrac->id, 'product_id' => $prodFrac->id,
            'unit_id' => $pcs->id, 'quantity' => '3.0000',
            'unit_price' => '33.333333', 'subtotal' => '100.0000',
        ]);
        $grnFracRes = $this->actingAs($user)->postJson('/inventory/grn', [
            'purchase_id' => $poFrac->id,
            'warehouse_id' => $whMain->id,
            'receipt_number' => 'GRN-FRAC-1',
            'idempotency_key' => 'idem-grn-frac-1',
            'lines' => [['purchase_item_id' => $piFrac->id, 'received_unit_id' => $pcs->id, 'received_quantity' => 3, 'unit_price' => '33.333333', 'allocated_freight_amount' => '0.01']],
        ]);
        $grnFracRes->assertStatus(201)->assertJsonPath('po_status', 'received');
        $grnFracId = (int) $grnFracRes->json('receipt.id');

        // Dashboard accurately shows verified + estimated legacy + mixed simultaneously
        $dash = $this->actingAs($user)->getJson('/dashboard');
        $dash->assertStatus(200);
        $this->assertGreaterThan(0, (float) $dash->json('valuation_breakdown.verified_acquisition'));
        $this->assertGreaterThan(0, (float) $dash->json('valuation_breakdown.estimated_legacy_baseline'));
        $this->assertGreaterThan(0, (float) $dash->json('valuation_breakdown.mixed_baseline_and_acquisition'));

        // StockAdjustment + StockAdjustment reversal
        $adjRes = $this->actingAs($user)->postJson('/inventory/adjustments', [
            'warehouse_id' => $whMain->id,
            'adjustment_number' => 'ADJ-REV-TEST',
            'idempotency_key' => 'idem-adj-rev-test',
            'reason' => 'Cycle count surplus',
            'lines' => [['product_id' => $prodFrac->id, 'direction' => 'in', 'quantity' => '2', 'unit_cost' => '35.000000']],
        ])->assertStatus(201);
        $adjId = (int) $adjRes->json('adjustment_id');

        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'StockAdjustment',
            'document_id' => $adjId,
            'adjustment_number' => 'REV-ADJ-1',
            'idempotency_key' => 'idem-rev-adj-1',
            'reason' => 'Undo cycle count error',
        ])->assertStatus(201);

        // StockTransfer + StockTransfer reversal
        $xferRes = $this->actingAs($user)->postJson('/inventory/transfers', [
            'from_warehouse_id' => $whMain->id,
            'to_warehouse_id' => $whSub->id,
            'transfer_number' => 'XFER-REV-TEST',
            'idempotency_key' => 'idem-xfer-rev-test',
            'lines' => [['product_id' => $prodFrac->id, 'unit_id' => $pcs->id, 'quantity' => '1']],
        ])->assertStatus(201);
        $xferId = (int) $xferRes->json('transfer_id');

        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'StockTransfer',
            'document_id' => $xferId,
            'adjustment_number' => 'REV-XFER-1',
            'idempotency_key' => 'idem-rev-xfer-1',
            'reason' => 'Undo wrong branch transfer',
        ])->assertStatus(201);
        $this->assertSame('0.0000', ProductWarehouse::where('product_id', $prodFrac->id)->where('warehouse_id', $whSub->id)->value('stock'));

        // Reverse GRN cleanly (all 3 units back at whMain) -> resets PO status back to 'ordered' & received_quantity=0
        $this->actingAs($user)->postJson('/inventory/reversals', [
            'document_type' => 'PurchaseReceipt',
            'document_id' => $grnFracId,
            'adjustment_number' => 'REV-GRN-FRAC',
            'idempotency_key' => 'idem-rev-grn-frac',
            'reason' => 'Vendor short shipment',
        ])->assertStatus(201);

        $poFrac->refresh();
        $piFrac->refresh();
        $this->assertSame('ordered', $poFrac->status);
        $this->assertSame('0.0000', $piFrac->received_quantity);

        // Clean depletion leaving 1 centavo residual flush verified
        $adjDepl = $this->actingAs($user)->postJson('/inventory/adjustments', [
            'warehouse_id' => $whMain->id,
            'adjustment_number' => 'ADJ-DEPL-1',
            'idempotency_key' => 'idem-adj-depl-1',
            'reason' => 'Seed 3 units @ 33.333333 (val 100.01 via 100 + 0.01)',
            'lines' => [
                ['product_id' => $prodFrac->id, 'direction' => 'in', 'quantity' => '2', 'unit_cost' => '33.330000'],
                ['product_id' => $prodFrac->id, 'direction' => 'in', 'quantity' => '1', 'unit_cost' => '33.350000'],
            ],
        ])->assertStatus(201);
        // Stock=3, Value=100.01, MAC = 33.336667 -> Issue 1 twice then 1 (leaves residual -> 0 on final issue)
        $this->actingAs($user)->postJson('/inventory/issues', [
            'warehouse_id' => $whMain->id, 'issue_number' => 'ISS-F1', 'idempotency_key' => 'iss-f1',
            'lines' => [['product_id' => $prodFrac->id, 'unit_id' => $pcs->id, 'quantity' => '1']],
        ])->assertStatus(201);
        $this->actingAs($user)->postJson('/inventory/issues', [
            'warehouse_id' => $whMain->id, 'issue_number' => 'ISS-F2', 'idempotency_key' => 'iss-f2',
            'lines' => [['product_id' => $prodFrac->id, 'unit_id' => $pcs->id, 'quantity' => '2']],
        ])->assertStatus(201);
        $pwFrac = ProductWarehouse::where('product_id', $prodFrac->id)->where('warehouse_id', $whMain->id)->firstOrFail();
        $this->assertSame('0.0000', $pwFrac->stock);
        $this->assertSame('0.0000', $pwFrac->inventory_value);
        $this->assertSame('0.000000', $pwFrac->moving_average_cost);
    }
}
