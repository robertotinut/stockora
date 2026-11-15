<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\PlatformAdmin;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockTransfer;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DocumentNumberService;
use App\Services\SubscriptionPaymentService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Round26HardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        app()->setLocale('en');
        app(TenantContext::class)->clear();
        parent::tearDown();
    }

    private function createWorkspace(
        string $planCode = Company::ACCESS_ENTERPRISE,
        string $role = 'owner',
        string $locale = 'en'
    ): array {
        $tag = strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $plan = SubscriptionPlan::where('code', $planCode)->firstOrFail();

        $user = User::factory()->create([
            'name' => "R26 User {$tag}",
            'email' => strtolower("r26-{$tag}@stockora.test"),
            'locale' => $locale,
        ]);

        $company = Company::create([
            'code' => "R26{$tag}",
            'name' => "PT Hardening {$tag}",
            'industry' => 'Distributor / Supplier',
            'phone' => '021-5550126',
            'address' => 'Jl. Sudirman No. 26, Jakarta',
            'is_active' => true,
            'account_status' => Company::STATUS_ACTIVE,
            'access_type' => $plan->tier,
            'subscription_plan_id' => $plan->id,
            'access_starts_at' => now()->subDays(5),
            'access_expires_at' => $plan->is_internal ? null : now()->addDays(90),
            'activated_at' => now()->subDays(5),
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

        $seeded = app(TenantContext::class)->withoutScope(function () use ($company): array {
            $whMain = Warehouse::create([
                'company_id' => $company->id,
                'code' => 'WH-MAIN',
                'name' => 'Main Distribution Hub',
                'is_default' => true,
                'is_active' => true,
            ]);
            $whBranch = Warehouse::create([
                'company_id' => $company->id,
                'code' => 'WH-BR2',
                'name' => 'Secondary Branch',
                'is_default' => false,
                'is_active' => true,
            ]);
            $unit = Unit::create([
                'company_id' => $company->id,
                'code' => 'PCS',
                'name' => 'Pieces',
                'symbol' => 'pcs',
            ]);
            $category = Category::create([
                'company_id' => $company->id,
                'code' => 'CAT-R26',
                'name' => 'General Goods',
            ]);
            $supplier = Supplier::create([
                'company_id' => $company->id,
                'code' => 'SUP-R26',
                'name' => 'PT Vendor Utama',
                'contact_person' => 'Budi Santoso',
                'phone' => '08123456789',
                'email' => 'vendor@r26.test',
                'address' => 'Jl. Industri Raya No. 10',
            ]);
            $product = Product::create([
                'company_id' => $company->id,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'code' => 'SKU-R26-01',
                'name' => 'Galvanized Steel Bolt',
                'stock' => 0,
                'min_stock' => 5,
                'moving_average_cost' => '0.000000',
                'valuation_basis' => 'MOVING_AVERAGE',
                'is_active' => 1,
                'legacy_reconciliation_status' => 'HISTORICALLY_VERIFIED',
            ]);

            return [$whMain, $whBranch, $unit, $category, $supplier, $product];
        });

        return array_merge([$user, $company, $plan], $seeded);
    }

    public function test_automatic_document_numbering_is_server_generated_tenant_scoped_immutable_and_collision_safe(): void
    {
        [$ownerA, $companyA, , $whMainA, $whBranchA, $unitA, , $supplierA, $productA] = $this->createWorkspace();
        [, $companyB] = $this->createWorkspace();
        $sessionA = ['active_company_id' => $companyA->id];

        $docNumService = app(DocumentNumberService::class);
        $todayPrefix = now()->format('Ymd');

        // 1. Previews on create pages do NOT reserve sequences until real persistence
        $this->actingAs($ownerA)->withSession($sessionA)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee("PO-{$todayPrefix}-0001")
            ->assertSee('readonly');

        $this->actingAs($ownerA)->withSession($sessionA)
            ->get(route('transfers.create'))
            ->assertOk()
            ->assertSee("TRF-{$todayPrefix}-0001")
            ->assertSee('readonly');

        $this->assertSame(0, DocumentSequence::where('company_id', $companyA->id)->count());

        // 2. Historical PO preserved and scanned cleanly so next server number avoids collision
        app(TenantContext::class)->withoutScope(function () use ($companyA, $supplierA, $ownerA, $todayPrefix): void {
            Purchase::create([
                'company_id' => $companyA->id,
                'supplier_id' => $supplierA->id,
                'user_id' => $ownerA->id,
                'purchase_number' => "PO-{$todayPrefix}-0004",
                'date' => now()->toDateString(),
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_amount' => '1000.0000',
            ]);
            Purchase::create([
                'company_id' => $companyA->id,
                'supplier_id' => $supplierA->id,
                'user_id' => $ownerA->id,
                'purchase_number' => 'LEGACY-PO-999',
                'date' => now()->subMonths(6)->toDateString(),
                'status' => 'received',
                'payment_status' => 'paid',
                'total_amount' => '500.0000',
            ]);
        });

        $this->assertSame("PO-{$todayPrefix}-0005", $docNumService->previewNextNumber($companyA->id, DocumentNumberService::TYPE_PURCHASE));

        // Tenant B sequence stays completely independent (starts at 0001)
        $this->assertSame("PO-{$todayPrefix}-0001", $docNumService->previewNextNumber($companyB->id, DocumentNumberService::TYPE_PURCHASE));

        // 3. Forged client-supplied purchase_number on create & update is ignored; PO stays immutable
        $this->actingAs($ownerA)->withSession($sessionA)
            ->post(route('purchases.store'), [
                'purchase_number' => 'PO-HACKED-999',
                'supplier_id' => $supplierA->id,
                'date' => now()->toDateString(),
                'payment_status' => 'unpaid',
                'submit_action' => 'draft',
                'items' => [[
                    'product_id' => $productA->id,
                    'unit_id' => $unitA->id,
                    'quantity' => 20,
                    'unit_price' => 15000,
                ]],
            ])
            ->assertRedirect();

        $po = app(TenantContext::class)->withoutScope(fn () => Purchase::where('company_id', $companyA->id)->latest('id')->firstOrFail());
        $this->assertSame("PO-{$todayPrefix}-0005", $po->purchase_number);
        $this->assertDatabaseHas('purchases', ['company_id' => $companyA->id, 'purchase_number' => 'LEGACY-PO-999']);

        $this->actingAs($ownerA)->withSession($sessionA)
            ->put(route('purchases.update', $po), [
                'purchase_number' => 'PO-TAMPER-EDIT',
                'supplier_id' => $supplierA->id,
                'date' => now()->toDateString(),
                'payment_status' => 'partial',
                'submit_action' => 'confirm',
                'items' => [[
                    'product_id' => $productA->id,
                    'unit_id' => $unitA->id,
                    'quantity' => 25,
                    'unit_price' => 15000,
                ]],
            ])
            ->assertRedirect(route('purchases.show', $po));

        $po->refresh();
        $this->assertSame("PO-{$todayPrefix}-0005", $po->purchase_number);
        $this->assertSame('ordered', $po->status);
        $poItem = $po->items()->firstOrFail();

        // 4. Web & JSON API GRN, Transfer, Issue, Adjustment, Reversal ignore forged client numbers
        $this->actingAs($ownerA)->withSession($sessionA)
            ->post(route('grns.store'), [
                'purchase_id' => $po->id,
                'warehouse_id' => $whMainA->id,
                'receipt_number' => 'GRN-FORGED-01',
                'idempotency_key' => 'r26-grn-web',
                'notes' => 'Web GRN R26',
                'lines' => [[
                    'purchase_item_id' => $poItem->id,
                    'received_unit_id' => $unitA->id,
                    'received_quantity' => 15,
                    'unit_price' => 15000,
                ]],
            ])->assertRedirect();

        $grnWeb = app(TenantContext::class)->withoutScope(fn () => PurchaseReceipt::where('company_id', $companyA->id)->where('idempotency_key', 'r26-grn-web')->firstOrFail());
        $this->assertSame("GRN-{$todayPrefix}-0001", $grnWeb->receipt_number);

        $grnApiRes = $this->actingAs($ownerA)->withSession($sessionA)
            ->postJson('/inventory/grn', [
                'purchase_id' => $po->id,
                'warehouse_id' => $whMainA->id,
                'receipt_number' => 'GRN-API-FORGE',
                'idempotency_key' => 'r26-grn-api',
                'lines' => [[
                    'purchase_item_id' => $poItem->id,
                    'received_unit_id' => $unitA->id,
                    'received_quantity' => 10,
                    'unit_price' => 15000,
                ]],
            ])->assertStatus(201);
        $this->assertSame("GRN-{$todayPrefix}-0002", $grnApiRes->json('receipt.receipt_number'));

        // Idempotent replay keeps the original generated number without advancing sequence
        $this->actingAs($ownerA)->withSession($sessionA)
            ->postJson('/inventory/grn', [
                'purchase_id' => $po->id,
                'warehouse_id' => $whMainA->id,
                'receipt_number' => 'GRN-REPLAY-DIFF',
                'idempotency_key' => 'r26-grn-api',
                'lines' => [[
                    'purchase_item_id' => $poItem->id,
                    'received_unit_id' => $unitA->id,
                    'received_quantity' => 10,
                    'unit_price' => 15000,
                ]],
            ])->assertStatus(200)
            ->assertJsonPath('receipt.receipt_number', "GRN-{$todayPrefix}-0002");

        // Transfer API & Web
        $trfRes = $this->actingAs($ownerA)->withSession($sessionA)
            ->postJson('/inventory/transfers', [
                'from_warehouse_id' => $whMainA->id,
                'to_warehouse_id' => $whBranchA->id,
                'transfer_number' => 'TRF-FORGED',
                'idempotency_key' => 'r26-trf-api',
                'lines' => [['product_id' => $productA->id, 'unit_id' => $unitA->id, 'quantity' => 5]],
            ])->assertStatus(201);
        $this->assertSame("TRF-{$todayPrefix}-0001", $trfRes->json('transfer_number'));

        // Issue API
        $issRes = $this->actingAs($ownerA)->withSession($sessionA)
            ->postJson('/inventory/issues', [
                'warehouse_id' => $whMainA->id,
                'issue_number' => 'ISS-FORGED',
                'idempotency_key' => 'r26-iss-api',
                'lines' => [['product_id' => $productA->id, 'unit_id' => $unitA->id, 'quantity' => 3]],
            ])->assertStatus(201);
        $this->assertSame("ISS-{$todayPrefix}-0001", $issRes->json('issue_number'));

        // Draft Adjustment create & update preserve generated ADJ number
        $this->actingAs($ownerA)->withSession($sessionA)
            ->post(route('adjustments.store'), [
                'adjustment_number' => 'ADJ-FORGED',
                'warehouse_id' => $whMainA->id,
                'idempotency_key' => 'r26-adj-draft',
                'reason' => 'Cycle count opname',
                'submit_action' => 'draft',
                'lines' => [['product_id' => $productA->id, 'direction' => 'in', 'quantity' => 2, 'unit_cost' => 15000]],
            ])->assertRedirect();

        $adj = app(TenantContext::class)->withoutScope(fn () => StockAdjustment::where('company_id', $companyA->id)->where('idempotency_key', 'r26-adj-draft')->firstOrFail());
        $this->assertSame("ADJ-{$todayPrefix}-0001", $adj->adjustment_number);

        $this->actingAs($ownerA)->withSession($sessionA)
            ->put(route('adjustments.update', $adj), [
                'adjustment_number' => 'ADJ-TAMPER-EDIT',
                'reason' => 'Cycle count revised',
                'submit_action' => 'post',
                'lines' => [['product_id' => $productA->id, 'direction' => 'in', 'quantity' => 4, 'unit_cost' => 15000]],
            ])->assertRedirect(route('adjustments.show', $adj));

        $adj->refresh();
        $this->assertSame("ADJ-{$todayPrefix}-0001", $adj->adjustment_number);
        $this->assertSame('posted', $adj->status);

        // Reversal generates server-controlled REV-* prefix sequentially
        $revRes = $this->actingAs($ownerA)->withSession($sessionA)
            ->postJson('/inventory/reversals', [
                'document_type' => 'StockIssue',
                'document_id' => (int) $issRes->json('issue_id'),
                'adjustment_number' => 'REV-FORGED',
                'idempotency_key' => 'r26-rev-iss',
                'reason' => 'Mistyped issue batch',
            ])->assertStatus(201);
        $this->assertSame("REV-ISS-{$todayPrefix}-0001", $revRes->json('adjustment_number'));

        // Tenant B still generates 0001 independently
        $bNext = $docNumService->generateNextNumber($companyB->id, DocumentNumberService::TYPE_PURCHASE);
        $this->assertSame("PO-{$todayPrefix}-0001", $bNext);
    }

    public function test_print_views_for_po_grn_transfer_issue_and_adjustment_render_read_only_and_enforce_rbac_and_isolation(): void
    {
        [$owner, $company, , $whMain, $whBranch, $unit, , $supplier, $product] = $this->createWorkspace();
        [, $otherCompany] = $this->createWorkspace();

        //Viewer user in $company can print ONLY what viewer role allows; create a restricted user without view perm
        $noViewUser = User::factory()->create(['email' => 'noview-' . uniqid() . '@stockora.test']);
        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $noViewUser->id,
            'role' => 'staff',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deny purchase_orders view for staff inside this tenant specifically to verify 403 on print route
        DB::table('company_role_permissions')->updateOrInsert(
            [
                'company_id' => $company->id,
                'role' => 'staff',
                'module' => 'purchase_orders',
                'action' => 'view',
            ],
            [
                'is_allowed' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $session = ['active_company_id' => $company->id];

        // Create PO -> GRN -> Transfer -> Issue -> Adjustment
        $this->actingAs($owner)->withSession($session)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->id,
                'date' => now()->toDateString(),
                'payment_status' => 'unpaid',
                'submit_action' => 'confirm',
                'notes' => 'Deliver to gate B',
                'items' => [[
                    'product_id' => $product->id,
                    'unit_id' => $unit->id,
                    'quantity' => 10,
                    'unit_price' => 20000,
                ]],
            ])->assertRedirect();

        $po = app(TenantContext::class)->withoutScope(fn () => Purchase::where('company_id', $company->id)->latest('id')->firstOrFail());
        $poItem = $po->items()->firstOrFail();

        $grnRes = $this->actingAs($owner)->withSession($session)
            ->postJson('/inventory/grn', [
                'purchase_id' => $po->id,
                'warehouse_id' => $whMain->id,
                'idempotency_key' => 'r26-print-grn',
                'notes' => 'Crate checked',
                'lines' => [[
                    'purchase_item_id' => $poItem->id,
                    'received_unit_id' => $unit->id,
                    'received_quantity' => 10,
                    'unit_price' => 20000,
                    'allocated_freight_amount' => 5000,
                ]],
            ])->assertStatus(201);
        $grn = app(TenantContext::class)->withoutScope(fn () => PurchaseReceipt::findOrFail((int) $grnRes->json('receipt.id')));

        $trfRes = $this->actingAs($owner)->withSession($session)
            ->postJson('/inventory/transfers', [
                'from_warehouse_id' => $whMain->id,
                'to_warehouse_id' => $whBranch->id,
                'idempotency_key' => 'r26-print-trf',
                'notes' => 'Branch restock',
                'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => 2]],
            ])->assertStatus(201);
        $trf = app(TenantContext::class)->withoutScope(fn () => StockTransfer::findOrFail((int) $trfRes->json('transfer_id')));

        $issRes = $this->actingAs($owner)->withSession($session)
            ->postJson('/inventory/issues', [
                'warehouse_id' => $whMain->id,
                'idempotency_key' => 'r26-print-iss',
                'notes' => 'Assembly floor batch 7',
                'lines' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => 1]],
            ])->assertStatus(201);
        $iss = app(TenantContext::class)->withoutScope(fn () => StockIssue::findOrFail((int) $issRes->json('issue_id')));

        $adjRes = $this->actingAs($owner)->withSession($session)
            ->postJson('/inventory/adjustments', [
                'warehouse_id' => $whMain->id,
                'idempotency_key' => 'r26-print-adj',
                'reason' => 'Audit gain',
                'lines' => [['product_id' => $product->id, 'direction' => 'in', 'quantity' => 1, 'unit_cost' => 20500]],
            ])->assertStatus(201);
        $adj = app(TenantContext::class)->withoutScope(fn () => StockAdjustment::findOrFail((int) $adjRes->json('adjustment_id')));

        // PO show has Print button + Print PO route renders clean read-only sheet (EN)
        $this->actingAs($owner)->withSession($session)
            ->get(route('purchases.show', $po))
            ->assertOk()
            ->assertSee(route('purchases.print', $po))
            ->assertSee('Print Purchase Order');

        $this->actingAs($owner)->withSession($session)
            ->get(route('purchases.print', $po))
            ->assertOk()
            ->assertSee($company->name)
            ->assertSee($po->purchase_number)
            ->assertSee('PT Vendor Utama')
            ->assertSee('Galvanized Steel Bolt')
            ->assertSee('SKU-R26-01')
            ->assertSee('PURCHASE ORDER')
            ->assertSee('Approved By')
            ->assertDontSee('<form');

        // GRN show has Print button + Print GRN route renders Surat Penerimaan Barang in ID locale cleanly
        $this->actingAs($owner)->withSession($session)
            ->get(route('grns.show', $grn))
            ->assertOk()
            ->assertSee(route('grns.print', $grn))
            ->assertSee('Print Goods Receipt');

        $this->actingAs($owner)->withSession($session)
            ->get(route('grns.print', $grn))
            ->assertOk()
            ->assertSee($grn->receipt_number)
            ->assertSee($po->purchase_number)
            ->assertSee('Main Distribution Hub')
            ->assertSee('GOODS RECEIPT NOTE')
            ->assertSee('Delivered By')
            ->assertSee('Warehouse Supervisor')
            ->assertDontSee('<form');

        // Indonesian localization on GRN & PO Print
        $owner->update(['locale' => 'id']);
        $this->actingAs($owner->fresh())->withSession($session)
            ->get(route('grns.print', $grn))
            ->assertOk()
            ->assertSee('SURAT PENERIMAAN BARANG (GRN)');

        // Transfer, Issue, Adjustment print routes
        $this->actingAs($owner->fresh())->withSession($session)->get(route('transfers.print', $trf))->assertOk()->assertSee($trf->transfer_number);
        $this->actingAs($owner->fresh())->withSession($session)->get(route('issues.print', $iss))->assertOk()->assertSee($iss->issue_number);
        $this->actingAs($owner->fresh())->withSession($session)->get(route('adjustments.print', $adj))->assertOk()->assertSee($adj->adjustment_number);

        // RBAC enforcement (staff denied purchase_orders.view receives 403 on purchases.print)
        $this->actingAs($noViewUser)->withSession($session)
            ->get(route('purchases.print', $po))
            ->assertForbidden();

        // Cross-tenant isolation (tenant without scope ownership gets 404)
        $otherOwner = User::factory()->create(['email' => 'cross-' . uniqid() . '@stockora.test']);
        DB::table('company_user')->insert([
            'company_id' => $otherCompany->id,
            'user_id' => $otherOwner->id,
            'role' => 'owner',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($otherOwner)->withSession(['active_company_id' => $otherCompany->id])
            ->get(route('purchases.print', $po))
            ->assertNotFound();

        $this->actingAs($otherOwner)->withSession(['active_company_id' => $otherCompany->id])
            ->get(route('grns.print', $grn))
            ->assertNotFound();
    }

    public function test_internal_plan_grants_unrestricted_workspace_entitlements_while_staying_hidden_and_unbillable(): void
    {
        $internalPlan = SubscriptionPlan::where('code', SubscriptionPlan::CODE_INTERNAL)->firstOrFail();
        $this->assertTrue((bool) $internalPlan->is_internal);
        $this->assertTrue((bool) $internalPlan->is_active);
        $this->assertSame(Company::ACCESS_ENTERPRISE, $internalPlan->tier);
        $this->assertFalse($internalPlan->isFreePlan());

        [$internalOwner, $internalCompany] = $this->createWorkspace(SubscriptionPlan::CODE_INTERNAL);
        $planService = app(SubscriptionPlanService::class);
        $paymentService = app(SubscriptionPaymentService::class);

        // 1. Every active module is enabled and every limit is unlimited (null)
        $activeModules = SubscriptionFeature::where('category', 'module')->where('is_active', true)->pluck('feature_key');
        foreach ($activeModules as $moduleKey) {
            $this->assertTrue($planService->hasFeature($internalCompany, $moduleKey), "Expected INTERNAL plan to enable {$moduleKey}");
        }

        $activeLimits = SubscriptionFeature::where('category', 'limit')->where('is_active', true)->pluck('feature_key');
        foreach ($activeLimits as $limitKey) {
            $this->assertNull($planService->getLimit($internalCompany, $limitKey), "Expected INTERNAL plan {$limitKey} to be unlimited (null)");
            $this->assertTrue($planService->isUnlimited($internalCompany, $limitKey));
            $this->assertTrue($planService->canCreate($internalCompany, $limitKey, 10000));
        }

        // 2. Public plans page and customer subscription upgrade options MUST hide INTERNAL
        $this->get(route('plans.index'))
            ->assertOk()
            ->assertDontSee($internalPlan->name)
            ->assertDontSee('INTERNAL');

        $subRes = $this->actingAs($internalOwner)->withSession(['active_company_id' => $internalCompany->id])
            ->get(route('subscription.index'))
            ->assertOk()
            ->assertSee($internalPlan->name);

        // Upgrade selector must not offer internal plan ID as a selectable upgrade target
        $subRes->assertDontSee('value="' . $internalPlan->id . '"', false);

        // 3. Payment quote strictly blocks internal plans from customer billing
        try {
            $paymentService->calculateQuote($internalPlan, 'monthly');
            $this->fail('Expected DomainException when quoting an INTERNAL plan.');
        } catch (DomainException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // 4. Default tier resolution for ENTERPRISE must NEVER return INTERNAL
        $resolvedEnterprise = $planService->resolveDefaultPlanByTier(Company::ACCESS_ENTERPRISE);
        $this->assertNotNull($resolvedEnterprise);
        $this->assertFalse((bool) $resolvedEnterprise->is_internal);

        // 5. Public comparison matrix hides internal plan unless explicitly requested by Platform admin views
        $comparisonPublic = $planService->getComparisonData(true, true);
        $publicIds = collect($comparisonPublic['plans'] ?? [])->pluck('id')->all();
        $this->assertNotContains($internalPlan->id, $publicIds);

        // 6. RBAC inside an INTERNAL workspace remains strictly enforced (zero bypass)
        $viewer = User::factory()->create(['email' => 'r26-int-viewer-' . uniqid() . '@stockora.test']);
        DB::table('company_user')->insert([
            'company_id' => $internalCompany->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'is_default' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($viewer)->withSession(['active_company_id' => $internalCompany->id])
            ->get(route('products.create'))
            ->assertForbidden();

        // 7. Tenant web session CANNOT access Platform Super Admin control plane
        $this->actingAs($internalOwner)->withSession(['active_company_id' => $internalCompany->id])
            ->get(route('platform.dashboard'))
            ->assertRedirect(route('platform.login'));

        // 8. Sandbox DEFAULT company & admin@stockora.com internal assignment check
        $defaultCo = Company::where('code', 'DEFAULT')->first();
        if ($defaultCo) {
            $this->assertSame((int) $internalPlan->id, (int) $defaultCo->subscription_plan_id);
            $this->assertSame(Company::STATUS_ACTIVE, $defaultCo->account_status);
            $this->assertNull($defaultCo->access_expires_at);
        }

        // Verify a FREE company remains properly restricted (no accidental relaxation)
        [$freeOwner, $freeCompany] = $this->createWorkspace(Company::ACCESS_FREE);
        $this->assertFalse($planService->hasFeature($freeCompany, 'module_stock_transfers'));
        $this->actingAs($freeOwner)->withSession(['active_company_id' => $freeCompany->id])
            ->get(route('transfers.index'))
            ->assertForbidden();
    }

    public function test_platform_plan_index_can_display_internal_plan_for_super_admin_only(): void
    {
        $platformAdmin = PlatformAdmin::create([
            'name' => 'R26 Platform Admin',
            'email' => 'r26-plat-' . uniqid() . '@stockora.app',
            'password' => Hash::make('PlatformStrong2026!'),
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin, 'platform')
            ->get(route('platform.plans.index'))
            ->assertOk()
            ->assertSee('INTERNAL');
    }
}
