<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Models\LegacyReconciliationAudit;
use App\Models\ProductWarehouse;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use App\Support\BcMathHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $warehouses = Warehouse::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->withCount(['productWarehouses as active_sku_count' => fn ($q) => $q->where('stock', '>', 0)])
            ->withSum(['productWarehouses as trusted_stock_sum' => fn ($q) => $q->where('valuation_basis', '!=', 'UNVERIFIED_QUARANTINED')], 'stock')
            ->withSum(['productWarehouses as trusted_value_sum' => fn ($q) => $q->where('valuation_basis', '!=', 'UNVERIFIED_QUARANTINED')], 'inventory_value')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'search'));
    }

    public function create()
    {
        $warehouse = new Warehouse([
            'is_active' => true,
            'is_default' => false,
        ]);

        return view('warehouses.form', compact('warehouse'));
    }

    public function store(Request $request, TenantContext $tenantContext, SubscriptionPlanService $planService)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $isDefault = (bool) ($validated['is_default'] ?? false);
        $isActive = $isDefault ? true : (bool) ($validated['is_active'] ?? false);

        try {
            DB::transaction(function () use ($companyId, $planService, $validated, $isDefault, $isActive): void {
                if ($isActive) {
                    $planService->assertCanCreateQuota($companyId, 'max_warehouses', 1);
                }

                if ($isDefault) {
                    Warehouse::query()->update(['is_default' => false]);
                }

                Warehouse::create([
                    'code' => strtoupper($validated['code']),
                    'name' => $validated['name'],
                    'is_active' => $isActive,
                    'is_default' => $isDefault,
                ]);
            });
        } catch (QuotaExceededException $e) {
            return $request->expectsJson()
                ? response()->json($e->toArray(), 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouses.index')->with('success', __('warehouses.flash.created', ['name' => $validated['name']]));
    }

    public function show(Warehouse $warehouse)
    {
        return redirect()->route('inventory.warehouses.index', ['warehouse_id' => $warehouse->id]);
    }

    public function edit(Warehouse $warehouse)
    {
        return view('warehouses.form', compact('warehouse'));
    }

    public function update(Request $request, Warehouse $warehouse, TenantContext $tenantContext, SubscriptionPlanService $planService)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'code')->where('company_id', $companyId)->ignore($warehouse->id)],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $wantsDefault = (bool) ($validated['is_default'] ?? false);
        $wantsActive = (bool) ($validated['is_active'] ?? false);

        if ($warehouse->is_default && !$wantsDefault) {
            return back()->withInput()->with('error', __('warehouses.flash.cannot_unset_default'));
        }

        if ($warehouse->is_default && !$wantsActive) {
            return back()->withInput()->with('error', __('warehouses.flash.default_must_stay_active'));
        }

        if ($wantsDefault) {
            $wantsActive = true;
        }

        $isReactivating = !$warehouse->is_active && $wantsActive;

        try {
            DB::transaction(function () use ($companyId, $planService, $warehouse, $validated, $wantsDefault, $wantsActive, $isReactivating): void {
                if ($isReactivating) {
                    $planService->assertCanCreateQuota($companyId, 'max_warehouses', 1);
                }

                if ($wantsDefault) {
                    Warehouse::where('id', '!=', $warehouse->id)->update(['is_default' => false]);
                }

                $warehouse->update([
                    'code' => strtoupper($validated['code']),
                    'name' => $validated['name'],
                    'is_active' => $wantsActive,
                    'is_default' => $wantsDefault,
                ]);
            });
        } catch (QuotaExceededException $e) {
            return $request->expectsJson()
                ? response()->json($e->toArray(), 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('warehouses.index')->with('success', __('warehouses.flash.updated', ['name' => $warehouse->name]));
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->is_default) {
            return back()->with('error', __('warehouses.flash.cannot_delete_default', ['name' => $warehouse->name]));
        }

        $isReferenced = $warehouse->productWarehouses()->exists()
            || StockMovement::where('warehouse_id', $warehouse->id)->exists()
            || PurchaseReceipt::where('warehouse_id', $warehouse->id)->exists()
            || StockTransfer::where('from_warehouse_id', $warehouse->id)->orWhere('to_warehouse_id', $warehouse->id)->exists()
            || StockIssue::where('warehouse_id', $warehouse->id)->exists()
            || StockAdjustment::where('warehouse_id', $warehouse->id)->exists()
            || LegacyReconciliationAudit::where('warehouse_id', $warehouse->id)->exists();

        if ($isReferenced) {
            return back()->with('error', __('warehouses.flash.has_history', ['name' => $warehouse->name]));
        }

        $name = $warehouse->name;
        $warehouse->delete();

        return redirect()->route('warehouses.index')->with('success', __('warehouses.flash.deleted', ['name' => $name]));
    }

    public function stockOverview(Request $request)
    {
        $warehouseId = (string) $request->query('warehouse_id', '');
        $valuationBasis = (string) $request->query('valuation_basis', '');
        $search = trim((string) $request->query('search', ''));
        $stockState = (string) $request->query('stock_state', '');

        $baseQuery = ProductWarehouse::query()
            ->with(['product.unit', 'product.category', 'warehouse'])
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->when($valuationBasis !== '', fn ($q) => $q->where('valuation_basis', $valuationBasis))
            ->when($stockState === 'positive', fn ($q) => $q->where('stock', '>', 0))
            ->when($stockState === 'zero', fn ($q) => $q->where('stock', '<=', 0))
            ->when($stockState === 'quarantined', fn ($q) => $q->where('valuation_basis', 'UNVERIFIED_QUARANTINED'))
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('product', function ($pq) use ($search): void {
                    $pq->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });

        $allMatching = (clone $baseQuery)->get();
        $trustedUnits = '0.0000';
        $trustedValue = '0.0000';
        $quarantinedRows = 0;

        foreach ($allMatching as $row) {
            if ($row->valuation_basis === 'UNVERIFIED_QUARANTINED') {
                $quarantinedRows++;
                continue;
            }
            $trustedUnits = bcadd($trustedUnits, BcMathHelper::normalize((string) $row->stock, 4), 4);
            $trustedValue = bcadd($trustedValue, BcMathHelper::normalize((string) $row->inventory_value, 4), 4);
        }

        $rows = $baseQuery
            ->orderBy('warehouse_id')
            ->orderByDesc('inventory_value')
            ->paginate(20)
            ->withQueryString();

        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('inventory.warehouse-stocks', compact(
            'rows',
            'warehouses',
            'warehouseId',
            'valuationBasis',
            'search',
            'stockState',
            'trustedUnits',
            'trustedValue',
            'quarantinedRows'
        ));
    }

    public function reconciliationAudits(Request $request)
    {
        $classification = (string) $request->query('classification', '');
        $actionTaken = (string) $request->query('action_taken', '');
        $search = trim((string) $request->query('search', ''));

        $audits = LegacyReconciliationAudit::query()
            ->with(['product.unit', 'warehouse'])
            ->when($classification !== '', fn ($q) => $q->where('classification', $classification))
            ->when($actionTaken !== '', fn ($q) => $q->where('action_taken', $actionTaken))
            ->when($search !== '', function ($q) use ($search): void {
                $q->whereHas('product', function ($pq) use ($search): void {
                    $pq->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderByRaw("CASE WHEN action_taken = 'BLOCKED_NEEDS_REVIEW' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $allAudits = LegacyReconciliationAudit::all();
        $summaryByClass = [
            'HISTORICALLY_VERIFIED' => 0,
            'CHAIN_CONSISTENT' => 0,
            'BASELINE_INITIALIZATION' => 0,
            'CHAIN_BREAK' => 0,
        ];
        $quarantinedLegacyStock = '0.0000';

        foreach ($allAudits as $audit) {
            if (array_key_exists($audit->classification, $summaryByClass)) {
                $summaryByClass[$audit->classification]++;
            }
            if ($audit->action_taken === 'BLOCKED_NEEDS_REVIEW') {
                $quarantinedLegacyStock = bcadd(
                    $quarantinedLegacyStock,
                    BcMathHelper::normalize((string) $audit->legacy_product_stock, 4),
                    4
                );
            }
        }

        $flaggedPurchaseItems = PurchaseItem::query()
            ->with(['purchase.supplier', 'product.unit', 'unit'])
            ->where(function ($q): void {
                $q->where('conversion_status', 'NEEDS_REVIEW')
                    ->orWhere('cost_breakdown_status', 'UNKNOWN_COMPONENTS');
            })
            ->orderBy('id')
            ->get();

        return view('inventory.reconciliation-audits', compact(
            'audits',
            'classification',
            'actionTaken',
            'search',
            'summaryByClass',
            'quarantinedLegacyStock',
            'flaggedPurchaseItems'
        ));
    }
}
