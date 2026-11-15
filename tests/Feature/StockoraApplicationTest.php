<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockTransfer;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockoraApplicationTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function createTenantUser(Company $company, string $role = 'owner'): User
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

        $user = User::factory()->create([
            'password' => Hash::make('secret123'),
        ]);
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_phase1_login_company_selection_creation_and_switch(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $secondCompany = Company::create(['code' => 'TENANT-2-' . uniqid(), 'name' => 'Second Co', 'is_active' => 1]);
        $user = $this->createTenantUser($defaultCompany);

        DB::table('company_user')->insert([
            'company_id' => $secondCompany->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'is_default' => 0,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Login with 2 active companies -> redirects to company selector
        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect(route('company.select'));

        $this->actingAs($user)
            ->get('/company/select')
            ->assertOk()
            ->assertSee($defaultCompany->name)
            ->assertSee($secondCompany->name);

        // Switching to second company persists in session and opens HTML dashboard cleanly
        $this->actingAs($user)
            ->post('/company/switch', ['company_id' => $secondCompany->id])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->withSession(['active_company_id' => $defaultCompany->id])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($defaultCompany->name)
            ->assertSee('PRD-PLP-GEL');

        // Creating a new company creates default WH-MAIN and attaches owner
        $newCode = 'PTBARU' . substr(uniqid(), -4);
        $this->actingAs($user)
            ->withSession(['active_company_id' => $defaultCompany->id])
            ->post('/companies', ['code' => $newCode, 'name' => 'PT Baru Sejahtera'])
            ->assertRedirect(route('companies.index'));

        $createdCo = Company::where('code', strtoupper($newCode))->firstOrFail();
        app(TenantContext::class)->setCompany((int) $createdCo->id, (int) $user->id);
        $this->assertTrue(Warehouse::where('code', 'WH-MAIN')->exists());
    }

    public function test_phase2_dashboard_and_master_data_crud_with_guards(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createTenantUser($defaultCompany);
        $session = ['active_company_id' => $defaultCompany->id];

        // Dashboard shows valuation breakdown and quarantined SKU warning
        $this->actingAs($user)
            ->withSession($session)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Valuation Basis Breakdown')
            ->assertSee('CHAIN_BREAK')
            ->assertSee('PRD-PLP-GEL');

        // Categories CRUD & delete guard
        $this->actingAs($user)->withSession($session)->get('/categories')->assertOk();
        $catCode = 'CAT-' . substr(uniqid(), -5);
        $this->actingAs($user)->withSession($session)
            ->post('/categories', ['code' => $catCode, 'name' => 'Elektronik Kantor', 'description' => 'Perangkat'])
            ->assertRedirect(route('categories.index'));

        app(TenantContext::class)->setCompany((int) $defaultCompany->id, (int) $user->id);
        $category = Category::where('code', strtoupper($catCode))->firstOrFail();

        $this->actingAs($user)->withSession($session)
            ->put(route('categories.update', $category), ['code' => $catCode, 'name' => 'Elektronik Baru', 'description' => 'Updated'])
            ->assertRedirect(route('categories.index'));
        $this->assertSame('Elektronik Baru', $category->fresh()->name);

        // Units & Unit Conversions
        $this->actingAs($user)->withSession($session)->get('/units')->assertOk();
        $unitSymbol = 'BX' . substr(uniqid(), -3);
        $this->actingAs($user)->withSession($session)
            ->post('/units', ['name' => 'Box ' . $unitSymbol, 'symbol' => $unitSymbol])
            ->assertRedirect(route('units.index'));

        $boxUnit = Unit::where('symbol', $unitSymbol)->firstOrFail();
        $pcsUnit = Unit::where('symbol', 'pcs')->firstOrFail();

        // Suppliers CRUD & Show
        $supCode = 'SUP-' . substr(uniqid(), -5);
        $this->actingAs($user)->withSession($session)
            ->post('/suppliers', ['code' => $supCode, 'name' => 'PT Mitra Office', 'contact_person' => 'Budi', 'phone' => '081234', 'email' => 'mitra@example.com'])
            ->assertRedirect(route('suppliers.index'));
        $supplier = Supplier::where('code', strtoupper($supCode))->firstOrFail();
        $this->actingAs($user)->withSession($session)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('PT Mitra Office');

        // Products CRUD + filters + show + delete guard
        $this->actingAs($user)->withSession($session)->get('/products')->assertOk()->assertSee('PRD-A4-75');
        $this->actingAs($user)->withSession($session)->get('/products?stock_filter=quarantined')->assertOk()->assertSee('PRD-A4-75');

        $prdCode = 'SKU-' . substr(uniqid(), -5);
        $this->actingAs($user)->withSession($session)
            ->post('/products', [
                'code' => $prdCode,
                'name' => 'Stapler HD-50',
                'category_id' => $category->id,
                'unit_id' => $pcsUnit->id,
                'purchase_price' => '25000',
                'selling_price' => '32000',
                'min_stock' => '5',
                'description' => 'Heavy duty stapler',
            ])
            ->assertRedirect();

        $product = Product::where('code', strtoupper($prdCode))->firstOrFail();
        $this->assertSame('HISTORICALLY_VERIFIED', $product->legacy_reconciliation_status);

        // Deleting category in use is blocked
        $this->actingAs($user)->withSession($session)
            ->delete(route('categories.destroy', $category))
            ->assertSessionHas('error');
        $this->assertNotNull(Category::find($category->id));

        // Add product-specific unit conversion and show page renders it
        $this->actingAs($user)->withSession($session)
            ->post(route('unit-conversions.store'), [
                'product_id' => $product->id,
                'from_unit_id' => $boxUnit->id,
                'to_unit_id' => $pcsUnit->id,
                'factor' => '12',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)->withSession($session)
            ->get(route('products.show', $product))
            ->assertOk()
            ->assertSee(strtoupper($prdCode))
            ->assertSee($unitSymbol);

        $conv = UnitConversion::where('product_id', $product->id)->firstOrFail();
        $this->actingAs($user)->withSession($session)
            ->delete(route('unit-conversions.destroy', $conv))
            ->assertSessionHas('success');

        // Quarantined product cannot be deleted
        $quarantined = Product::where('code', 'PRD-A4-75')->firstOrFail();
        $this->actingAs($user)->withSession($session)
            ->delete(route('products.destroy', $quarantined))
            ->assertSessionHas('error');

        // Clean product without transactions can be deleted
        $this->actingAs($user)->withSession($session)
            ->delete(route('products.destroy', $product))
            ->assertRedirect(route('products.index'))
            ->assertSessionHas('success');
    }

    public function test_phase3_warehouses_stock_overview_and_reconciliation_audits(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createTenantUser($defaultCompany);
        $session = ['active_company_id' => $defaultCompany->id];

        // Warehouse index shows default WH-MAIN and prevents deleting default
        $this->actingAs($user)->withSession($session)
            ->get(route('warehouses.index'))
            ->assertOk()
            ->assertSee('WH-MAIN');

        app(TenantContext::class)->setCompany((int) $defaultCompany->id, (int) $user->id);
        $mainWh = Warehouse::where('code', 'WH-MAIN')->firstOrFail();

        $this->actingAs($user)->withSession($session)
            ->delete(route('warehouses.destroy', $mainWh))
            ->assertSessionHas('error');

        // Create secondary warehouse, update it, verify cannot unset only default, then delete unused warehouse
        $whCode = 'WH-TST-' . substr(uniqid(), -4);
        $this->actingAs($user)->withSession($session)
            ->post(route('warehouses.store'), [
                'code' => $whCode,
                'name' => 'Gudang Cabang Uji',
                'is_active' => 1,
                'is_default' => 0,
            ])
            ->assertRedirect(route('warehouses.index'));

        $wh = Warehouse::where('code', strtoupper($whCode))->firstOrFail();
        $this->actingAs($user)->withSession($session)
            ->put(route('warehouses.update', $wh), [
                'code' => $whCode,
                'name' => 'Gudang Cabang Updated',
                'is_active' => 1,
                'is_default' => 0,
            ])
            ->assertRedirect(route('warehouses.index'));
        $this->assertSame('Gudang Cabang Updated', $wh->fresh()->name);

        // Cannot unset default flag on WH-MAIN directly
        $this->actingAs($user)->withSession($session)
            ->put(route('warehouses.update', $mainWh), [
                'code' => $mainWh->code,
                'name' => $mainWh->name,
                'is_active' => 1,
                'is_default' => 0,
            ])
            ->assertSessionHas('error');

        // Stock overview per warehouse & filters
        $this->actingAs($user)->withSession($session)
            ->get(route('inventory.warehouses.index'))
            ->assertOk()
            ->assertSee('WH-MAIN')
            ->assertSee('PRD-PLP-GEL');

        $this->actingAs($user)->withSession($session)
            ->get(route('inventory.warehouses.index', ['warehouse_id' => $mainWh->id, 'stock_state' => 'quarantined']))
            ->assertOk()
            ->assertSee('PRD-A4-75');

        // Reconciliation audits view shows PRD-A4-75 CHAIN_BREAK and legacy flagged PO items
        $this->actingAs($user)->withSession($session)
            ->get(route('reconciliation-audits.index'))
            ->assertOk()
            ->assertSee('CHAIN_BREAK')
            ->assertSee('PRD-A4-75')
            ->assertSee('NEEDS_REVIEW');

        // Unused secondary warehouse can be deleted
        $this->actingAs($user)->withSession($session)
            ->delete(route('warehouses.destroy', $wh))
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('success');
    }

    public function test_phase4_purchase_order_and_web_grn_partial_full_and_reversal(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createTenantUser($defaultCompany);
        $session = ['active_company_id' => $defaultCompany->id];

        app(TenantContext::class)->setCompany((int) $defaultCompany->id, (int) $user->id);
        $warehouse = Warehouse::where('code', 'WH-MAIN')->firstOrFail();
        $supplier = Supplier::firstOrFail();
        $product = Product::where('code', 'PRD-PLP-GEL')->firstOrFail();
        $initialStock = (string) $product->stock;

        // Legacy PO audit filter works
        $this->actingAs($user)->withSession($session)
            ->get(route('purchases.index', ['needs_review' => '1']))
            ->assertOk()
            ->assertSee('Needs Review');

        // Create Draft PO (client-supplied number must be ignored in favor of server sequence)
        $poNum = 'PO-WEB-' . substr(uniqid(), -5);
        $this->actingAs($user)->withSession($session)
            ->post(route('purchases.store'), [
                'purchase_number' => $poNum,
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'payment_status' => 'unpaid',
                'submit_action' => 'draft',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'unit_id' => $product->unit_id,
                        'quantity' => '10',
                        'unit_price' => '5000',
                    ],
                ],
            ])
            ->assertRedirect();

        $po = Purchase::where('company_id', $defaultCompany->id)->latest('id')->firstOrFail();
        $this->assertNotSame(strtoupper($poNum), $po->purchase_number);
        $this->assertStringStartsWith('PO-', $po->purchase_number);
        $this->assertSame('pending', $po->status);
        $this->assertSame('50000.0000', (string) $po->total_amount);

        // Edit PO before GRN, then confirm order (number stays immutable)
        $originalPoNumber = $po->purchase_number;
        $this->actingAs($user)->withSession($session)
            ->put(route('purchases.update', $po), [
                'purchase_number' => 'PO-FORGED-EDIT',
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'payment_status' => 'partial',
                'submit_action' => 'confirm',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'unit_id' => $product->unit_id,
                        'quantity' => '12',
                        'unit_price' => '5000',
                    ],
                ],
            ])
            ->assertRedirect(route('purchases.show', $po));

        $po->refresh();
        $this->assertSame($originalPoNumber, $po->purchase_number);
        $this->assertSame('ordered', $po->status);
        $this->assertSame('60000.0000', (string) $po->total_amount);
        $poItem = $po->items()->firstOrFail();

        // GRN #1: partial 5 pcs with freight and non-recoverable tax
        $grnNum1 = 'GRN-W1-' . substr(uniqid(), -4);
        $grnIdem1 = 'idem-w1-' . uniqid();
        $this->actingAs($user)->withSession($session)
            ->post(route('grns.store'), [
                'purchase_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'receipt_number' => $grnNum1,
                'idempotency_key' => $grnIdem1,
                'notes' => 'Partial batch 1',
                'lines' => [
                    [
                        'purchase_item_id' => $poItem->id,
                        'received_unit_id' => $product->unit_id,
                        'received_quantity' => '5',
                        'unit_price' => '5000',
                        'discount_amount' => '1000',
                        'allocated_freight_amount' => '2000',
                        'non_recoverable_tax_amount' => '500',
                        'recoverable_tax_amount' => '2500',
                    ],
                ],
            ])
            ->assertRedirect();

        $po->refresh();
        $this->assertSame('partial', $po->status);
        $grn1 = PurchaseReceipt::where('company_id', $defaultCompany->id)->where('idempotency_key', $grnIdem1)->firstOrFail();
        $this->assertNotSame(strtoupper($grnNum1), $grn1->receipt_number);
        $this->assertStringStartsWith('GRN-', $grn1->receipt_number);
        $this->assertSame('26500.0000', (string) $grn1->total_capitalized_cost); // (5*5000) - 1000 + 2000 + 500
        $this->assertSame(bcadd($initialStock, '5.0000', 4), (string) $product->fresh()->stock);

        // Editing PO now blocked because GRN exists
        $this->actingAs($user)->withSession($session)
            ->get(route('purchases.edit', $po))
            ->assertRedirect(route('purchases.show', $po))
            ->assertSessionHas('error');

        // Over-receiving blocked cleanly with flash error
        $this->actingAs($user)->withSession($session)
            ->post(route('grns.store'), [
                'purchase_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'receipt_number' => 'GRN-OVR-' . substr(uniqid(), -4),
                'idempotency_key' => 'idem-ovr-' . uniqid(),
                'lines' => [
                    [
                        'purchase_item_id' => $poItem->id,
                        'received_unit_id' => $product->unit_id,
                        'received_quantity' => '10',
                    ],
                ],
            ])
            ->assertSessionHas('error');

        // GRN #2: remaining 7 pcs -> PO received
        $grnNum2 = 'GRN-W2-' . substr(uniqid(), -4);
        $this->actingAs($user)->withSession($session)
            ->post(route('grns.store'), [
                'purchase_id' => $po->id,
                'warehouse_id' => $warehouse->id,
                'receipt_number' => $grnNum2,
                'idempotency_key' => 'idem-w2-' . uniqid(),
                'lines' => [
                    [
                        'purchase_item_id' => $poItem->id,
                        'received_unit_id' => $product->unit_id,
                        'received_quantity' => '7',
                        'unit_price' => '5000',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('received', $po->fresh()->status);
        $this->assertSame(bcadd($initialStock, '12.0000', 4), (string) $product->fresh()->stock);

        // Reverse GRN #1 via web action (append-only adjustment keeps immutable receipt posted)
        $this->actingAs($user)->withSession($session)
            ->post(route('grns.reverse', $grn1), ['reason' => 'Batch 1 dikembalikan ke vendor'])
            ->assertRedirect(route('grns.show', $grn1))
            ->assertSessionHas('success');

        $this->assertSame('posted', $grn1->fresh()->status);
        $this->assertNotNull(StockAdjustment::where('reverses_type', 'PurchaseReceipt')->where('reverses_id', $grn1->id)->first());
        $this->assertSame('partial', $po->fresh()->status);
        $this->assertSame(bcadd($initialStock, '7.0000', 4), (string) $product->fresh()->stock);

        // Double reversal blocked cleanly
        $this->actingAs($user)->withSession($session)
            ->post(route('grns.reverse', $grn1), ['reason' => 'Duplicate reverse'])
            ->assertSessionHas('error');

        $this->actingAs($user)->withSession($session)->get(route('grns.index'))->assertOk()->assertSee('REVERSED');
        $this->actingAs($user)->withSession($session)->get(route('grns.show', $grn1))->assertOk()->assertSee('Batch 1 dikembalikan ke vendor');
    }

    public function test_phase5_transfers_issues_and_adjustments_web_workflows(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createTenantUser($defaultCompany);
        $session = ['active_company_id' => $defaultCompany->id];

        app(TenantContext::class)->setCompany((int) $defaultCompany->id, (int) $user->id);
        $mainWh = Warehouse::where('code', 'WH-MAIN')->firstOrFail();
        $dstWh = Warehouse::create([
            'code' => 'WH-DST-' . substr(uniqid(), -4),
            'name' => 'Gudang Cabang Transfer',
            'is_active' => 1,
            'is_default' => 0,
        ]);
        $product = Product::where('code', 'PRD-PLP-GEL')->firstOrFail();
        $startMainStock = (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock');

        // 1. Stock Transfer Web Flow + Reversal
        $trfNum = strtoupper('TRF-W-' . substr(uniqid(), -4));
        $trfIdem = 'idem-trf-' . uniqid();
        $this->actingAs($user)->withSession($session)
            ->post(route('transfers.store'), [
                'transfer_number' => $trfNum,
                'from_warehouse_id' => $mainWh->id,
                'to_warehouse_id' => $dstWh->id,
                'idempotency_key' => $trfIdem,
                'notes' => 'Transfer ke cabang',
                'lines' => [
                    ['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => '5'],
                ],
            ])
            ->assertRedirect();

        $trf = StockTransfer::where('idempotency_key', $trfIdem)->firstOrFail();
        $this->assertNotSame($trfNum, $trf->transfer_number);
        $this->assertStringStartsWith('TRF-', $trf->transfer_number);
        $this->assertSame(
            '5.0000',
            (string) ProductWarehouse::where('warehouse_id', $dstWh->id)->where('product_id', $product->id)->value('stock')
        );
        $this->assertSame(
            bcsub($startMainStock, '5.0000', 4),
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );

        $this->actingAs($user)->withSession($session)->get(route('transfers.index'))->assertOk()->assertSee($trf->transfer_number);
        $this->actingAs($user)->withSession($session)->get(route('transfers.show', $trf))->assertOk()->assertSee('Transfer ke cabang');

        // Reverse transfer restores both warehouses
        $this->actingAs($user)->withSession($session)
            ->post(route('transfers.reverse', $trf), ['reason' => 'Batal kirim cabang'])
            ->assertRedirect(route('transfers.show', $trf))
            ->assertSessionHas('success');

        $this->assertSame(
            '0.0000',
            (string) ProductWarehouse::where('warehouse_id', $dstWh->id)->where('product_id', $product->id)->value('stock')
        );
        $this->assertSame(
            $startMainStock,
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );

        // 2. Stock Issue Web Flow + Insufficient Stock Guard + Reversal
        $issNum = strtoupper('ISS-W-' . substr(uniqid(), -4));
        $issIdem = 'idem-iss-' . uniqid();
        $this->actingAs($user)->withSession($session)
            ->post(route('issues.store'), [
                'issue_number' => $issNum,
                'warehouse_id' => $mainWh->id,
                'idempotency_key' => $issIdem,
                'notes' => 'Pemakaian divisi umum',
                'lines' => [
                    ['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => '4'],
                ],
            ])
            ->assertRedirect();

        $iss = StockIssue::where('idempotency_key', $issIdem)->firstOrFail();
        $this->assertNotSame($issNum, $iss->issue_number);
        $this->assertStringStartsWith('ISS-', $iss->issue_number);
        $this->assertSame(
            bcsub($startMainStock, '4.0000', 4),
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );

        // Excessive issue blocked gracefully
        $this->actingAs($user)->withSession($session)
            ->post(route('issues.store'), [
                'issue_number' => 'ISS-OVR-' . substr(uniqid(), -4),
                'warehouse_id' => $mainWh->id,
                'idempotency_key' => 'idem-iss-ovr-' . uniqid(),
                'lines' => [
                    ['product_id' => $product->id, 'unit_id' => $product->unit_id, 'quantity' => '999999'],
                ],
            ])
            ->assertSessionHas('error');

        $this->actingAs($user)->withSession($session)->get(route('issues.show', $iss))->assertOk()->assertSee($iss->issue_number);
        $this->actingAs($user)->withSession($session)
            ->post(route('issues.reverse', $iss), ['reason' => 'Retur pemakaian internal'])
            ->assertRedirect(route('issues.show', $iss))
            ->assertSessionHas('success');
        $this->assertSame(
            $startMainStock,
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );

        // 3. Stock Adjustment Draft -> Edit Draft -> Post Draft -> Reverse
        $adjNum = strtoupper('ADJ-W-' . substr(uniqid(), -4));
        $adjIdem = 'idem-adj-' . uniqid();
        $this->actingAs($user)->withSession($session)
            ->post(route('adjustments.store'), [
                'adjustment_number' => $adjNum,
                'warehouse_id' => $mainWh->id,
                'idempotency_key' => $adjIdem,
                'reason' => 'Opname awal bulan',
                'submit_action' => 'draft',
                'lines' => [
                    ['product_id' => $product->id, 'direction' => 'in', 'quantity' => '3', 'unit_cost' => '3500'],
                ],
            ])
            ->assertRedirect();

        $adj = StockAdjustment::where('idempotency_key', $adjIdem)->firstOrFail();
        $this->assertNotSame($adjNum, $adj->adjustment_number);
        $this->assertStringStartsWith('ADJ-', $adj->adjustment_number);
        $this->assertSame('draft', $adj->status);
        $this->assertSame(
            $startMainStock,
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );

        // Edit draft qty from 3 -> 6, keep draft
        $this->actingAs($user)->withSession($session)
            ->put(route('adjustments.update', $adj), [
                'reason' => 'Opname revisi',
                'submit_action' => 'draft',
                'lines' => [
                    ['product_id' => $product->id, 'direction' => 'in', 'quantity' => '6', 'unit_cost' => '3500'],
                ],
            ])
            ->assertRedirect(route('adjustments.show', $adj));

        // Post draft
        $this->actingAs($user)->withSession($session)
            ->post(route('adjustments.post', $adj))
            ->assertRedirect(route('adjustments.show', $adj))
            ->assertSessionHas('success');

        $this->assertSame('posted', $adj->fresh()->status);
        $this->assertSame(
            bcadd($startMainStock, '6.0000', 4),
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );

        // Editing posted adjustment blocked
        $this->actingAs($user)->withSession($session)
            ->get(route('adjustments.edit', $adj))
            ->assertRedirect(route('adjustments.show', $adj))
            ->assertSessionHas('error');

        // Reverse posted adjustment restores original stock
        $this->actingAs($user)->withSession($session)
            ->post(route('adjustments.reverse', $adj), ['reason' => 'Selisih salah hitung'])
            ->assertRedirect(route('adjustments.show', $adj))
            ->assertSessionHas('success');

        $this->assertSame(
            $startMainStock,
            (string) ProductWarehouse::where('warehouse_id', $mainWh->id)->where('product_id', $product->id)->value('stock')
        );
    }

    public function test_phase6_stock_movements_reports_csv_exports_and_route_integrity(): void
    {
        $defaultCompany = Company::where('code', 'DEFAULT')->firstOrFail();
        $user = $this->createTenantUser($defaultCompany);
        $session = ['active_company_id' => $defaultCompany->id];

        app(TenantContext::class)->setCompany((int) $defaultCompany->id, (int) $user->id);
        $mainWh = Warehouse::where('code', 'WH-MAIN')->firstOrFail();
        $product = Product::where('code', 'PRD-PLP-GEL')->firstOrFail();

        // Stock movement history page + filters
        $this->actingAs($user)->withSession($session)
            ->get(route('stock-movements.index', ['warehouse_id' => $mainWh->id, 'product_id' => $product->id]))
            ->assertOk()
            ->assertSee('PRD-PLP-GEL')
            ->assertSee('WH-MAIN');

        // Reports page shows valuation summary, movements, and exception panels
        $this->actingAs($user)->withSession($session)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Inventory Valuation Summary by Warehouse')
            ->assertSee('PRD-A4-75');

        // CSV exports for all 3 report types
        foreach (['valuation', 'movements', 'exceptions'] as $reportType) {
            $resp = $this->actingAs($user)->withSession($session)
                ->get(route('reports.export', ['type' => $reportType]));
            $resp->assertOk();
            $this->assertStringContainsString('text/csv', (string) $resp->headers->get('content-type'));
            $csv = $resp->streamedContent();
            $this->assertNotEmpty($csv);
        }

        // Cross-tenant isolation on Web routes: other company cannot view DEFAULT product
        $otherCompany = Company::create(['code' => 'ISO-' . substr(uniqid(), -4), 'name' => 'Other Co', 'is_active' => 1]);
        $otherUser = $this->createTenantUser($otherCompany);
        $this->actingAs($otherUser)
            ->withSession(['active_company_id' => $otherCompany->id])
            ->get(route('products.show', $product))
            ->assertNotFound();

        // Full GET route sweep across all web modules ensures zero 500s
        $routesToSweep = [
            route('dashboard'),
            route('companies.index'),
            route('companies.create'),
            route('categories.index'),
            route('categories.create'),
            route('units.index'),
            route('units.create'),
            route('suppliers.index'),
            route('suppliers.create'),
            route('products.index'),
            route('products.create'),
            route('warehouses.index'),
            route('warehouses.create'),
            route('inventory.warehouses.index'),
            route('reconciliation-audits.index'),
            route('purchases.index'),
            route('purchases.create'),
            route('grns.index'),
            route('grns.create'),
            route('transfers.index'),
            route('transfers.create'),
            route('issues.index'),
            route('issues.create'),
            route('adjustments.index'),
            route('adjustments.create'),
            route('stock-movements.index'),
            route('reports.index'),
        ];

        foreach ($routesToSweep as $url) {
            $this->actingAs($user)->withSession($session)->get($url)->assertOk();
        }
    }
}
