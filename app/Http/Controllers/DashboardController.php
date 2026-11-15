<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\LegacyReconciliationAudit;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReceipt;
use App\Models\StockMovement;
use App\Models\SubscriptionPayment;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\RenewalReminderService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use App\Support\BcMathHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, SubscriptionPlanService $planService)
    {
        $companyId = $tenantContext->requireCompanyId();
        $activeCompany = Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
            ->findOrFail($companyId);
        $subscriptionUsage = $planService->getUsageSummary($activeCompany);
        $planEntitlements = $planService->getCompanyEntitlements($activeCompany);
        $billingReminder = app(RenewalReminderService::class)->evaluateCompany($activeCompany);
        $pendingPaymentCount = SubscriptionPayment::where('company_id', $companyId)
            ->where('status', SubscriptionPayment::STATUS_PENDING)
            ->count();
        $supportConfig = config('stockora.support', []);

        $totalProducts = Product::count();
        $totalSuppliers = Supplier::count();
        $totalWarehouses = Warehouse::where('is_active', 1)->count();
        $totalPurchases = Purchase::count();
        $totalSpend = Purchase::where('status', '!=', 'cancelled')->sum('total_amount');
        $lowStockCount = Product::whereColumn('stock', '<=', 'min_stock')->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')->count();
        $inStockCount = max(0, $totalProducts - $lowStockCount);
        $pendingPurchasesCount = Purchase::whereIn('status', ['pending', 'ordered', 'partial'])->count();
        $receivedCount = Purchase::where('status', 'received')->count();

        // Warehouse-level valuation breakdown (BCMath, NEVER raw products.stock * purchase_price!)
        $warehousesById = Warehouse::orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active'])
            ->keyBy('id');

        $pwRows = ProductWarehouse::query()->get();
        $trustedUnits = '0.0000';
        $trustedInventoryValue = '0.0000';
        $verifiedAcquisitionValue = '0.0000';
        $estimatedLegacyValue = '0.0000';
        $mixedValuationValue = '0.0000';
        $quarantinedWarehouseCount = 0;

        $warehouseBuckets = [];
        foreach ($warehousesById as $whId => $wh) {
            $warehouseBuckets[$whId] = [
                'warehouse_id' => $wh->id,
                'code' => $wh->code,
                'name' => $wh->name,
                'is_active' => (bool) $wh->is_active,
                'trusted_units' => '0.0000',
                'trusted_value' => '0.0000',
                'sku_count' => 0,
                'quarantined_sku_count' => 0,
                'share_pct' => 0.0,
            ];
        }

        foreach ($pwRows as $pw) {
            $basis = (string) $pw->valuation_basis;
            $qty = BcMathHelper::normalize((string) $pw->stock, 4);
            $val = BcMathHelper::normalize((string) $pw->inventory_value, 4);
            $whId = (int) $pw->warehouse_id;

            if (! isset($warehouseBuckets[$whId])) {
                $warehouseBuckets[$whId] = [
                    'warehouse_id' => $whId,
                    'code' => 'WH-' . $whId,
                    'name' => 'Gudang #' . $whId,
                    'is_active' => true,
                    'trusted_units' => '0.0000',
                    'trusted_value' => '0.0000',
                    'sku_count' => 0,
                    'quarantined_sku_count' => 0,
                    'share_pct' => 0.0,
                ];
            }

            if ($basis === 'UNVERIFIED_QUARANTINED') {
                $quarantinedWarehouseCount++;
                $warehouseBuckets[$whId]['quarantined_sku_count']++;
                continue;
            }

            $trustedUnits = bcadd($trustedUnits, $qty, 4);
            $trustedInventoryValue = bcadd($trustedInventoryValue, $val, 4);

            $warehouseBuckets[$whId]['trusted_units'] = bcadd($warehouseBuckets[$whId]['trusted_units'], $qty, 4);
            $warehouseBuckets[$whId]['trusted_value'] = bcadd($warehouseBuckets[$whId]['trusted_value'], $val, 4);
            if (bccomp($qty, '0.0000', 4) > 0) {
                $warehouseBuckets[$whId]['sku_count']++;
            }

            if ($basis === 'VERIFIED_ACQUISITION') {
                $verifiedAcquisitionValue = bcadd($verifiedAcquisitionValue, $val, 4);
            } elseif ($basis === 'ESTIMATED_LEGACY_BASELINE') {
                $estimatedLegacyValue = bcadd($estimatedLegacyValue, $val, 4);
            } elseif ($basis === 'MIXED_BASELINE_AND_ACQUISITION') {
                $mixedValuationValue = bcadd($mixedValuationValue, $val, 4);
            }
        }

        $warehouseDistribution = collect($warehouseBuckets)
            ->map(function (array $row) use ($trustedInventoryValue, $trustedUnits): array {
                if (bccomp($trustedInventoryValue, '0.0000', 4) > 0) {
                    $row['share_pct'] = round((float) $row['trusted_value'] / (float) $trustedInventoryValue * 100, 1);
                } elseif (bccomp($trustedUnits, '0.0000', 4) > 0) {
                    $row['share_pct'] = round((float) $row['trusted_units'] / (float) $trustedUnits * 100, 1);
                }
                return $row;
            })
            ->sortByDesc(fn (array $row) => [(float) $row['trusted_value'], (float) $row['trusted_units'], (int) $row['is_active']])
            ->values();

        $quarantinedAudits = LegacyReconciliationAudit::query()
            ->where('action_taken', 'BLOCKED_NEEDS_REVIEW')
            ->get();
        $quarantinedLegacyStock = '0.0000';
        foreach ($quarantinedAudits as $qa) {
            $quarantinedLegacyStock = bcadd($quarantinedLegacyStock, BcMathHelper::normalize((string) $qa->legacy_product_stock, 4), 4);
        }

        $purchaseItemsNeedingReview = PurchaseItem::query()
            ->where(function ($q) {
                $q->where('conversion_status', 'NEEDS_REVIEW')
                    ->orWhere('cost_breakdown_status', 'UNKNOWN_COMPONENTS');
            })
            ->count();

        $stockValue = $trustedInventoryValue;
        $totalUnits = $trustedUnits;

        if ($request->expectsJson()) {
            return response()->json([
                'company_id' => $companyId,
                'total_products' => $totalProducts,
                'trusted_stock_units' => $trustedUnits,
                'trusted_inventory_value' => $trustedInventoryValue,
                'valuation_breakdown' => [
                    'verified_acquisition' => $verifiedAcquisitionValue,
                    'estimated_legacy_baseline' => $estimatedLegacyValue,
                    'mixed_baseline_and_acquisition' => $mixedValuationValue,
                ],
                'quarantined_products_count' => $quarantinedAudits->count(),
                'quarantined_warehouse_rows' => $quarantinedWarehouseCount,
                'quarantined_legacy_stock_excluded' => $quarantinedLegacyStock,
                'purchase_items_needing_review' => $purchaseItemsNeedingReview,
            ]);
        }

        $healthyPct = $totalProducts ? round($inStockCount / $totalProducts * 100) : 0;
        $receivedPct = $totalPurchases ? round($receivedCount / $totalPurchases * 100) : 0;

        $days = collect(range(13, 0))->map(fn ($i) => Carbon::today()->subDays($i));
        $moves = StockMovement::where('created_at', '>=', $days->first())
            ->selectRaw('DATE(created_at) d, direction, SUM(quantity) q')
            ->groupBy('d', 'direction')->get();

        $chartIn = $chartOut = $chartTicks = [];
        foreach ($days as $i => $day) {
            $key = $day->toDateString();
            $chartIn[] = [$i, (float) $moves->where('d', $key)->where('direction', 'in')->sum('q')];
            $chartOut[] = [$i, (float) $moves->where('d', $key)->where('direction', 'out')->sum('q')];
            $chartTicks[] = [$i, $day->format('d/m')];
        }
        $movedIn = array_sum(array_column($chartIn, 1));
        $movedOut = array_sum(array_column($chartOut, 1));

        $spark = collect(range(8, 0))->map(function ($i) {
            $d = Carbon::today()->subDays($i)->toDateString();
            return (float) Purchase::whereDate('date', $d)->where('status', '!=', 'cancelled')->sum('total_amount');
        })->values();

        $sparkPo = collect(range(8, 0))->map(
            fn ($i) => Purchase::whereDate('date', Carbon::today()->subDays($i))->count()
        )->values();

        $topSuppliers = Supplier::withSum(['purchases' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total_amount')
            ->withCount('purchases')
            ->orderByDesc('purchases_sum_total_amount')
            ->take(5)->get();

        $lowStockProducts = Product::whereColumn('stock', '<=', 'min_stock')
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->with(['category', 'unit'])
            ->take(5)->get();
        $recentPurchases = Purchase::with('supplier')->latest('id')->take(5)->get();
        $recentReceipts = PurchaseReceipt::with(['purchase.supplier', 'warehouse', 'receivedBy'])->latest('id')->take(5)->get();
        $recentMovements = StockMovement::with(['product', 'warehouse', 'user'])->latest('id')->take(6)->get();
        $quarantinedCount = $quarantinedAudits->count();

        return view('dashboard', compact(
            'totalProducts', 'totalSuppliers', 'totalWarehouses', 'totalPurchases', 'totalSpend',
            'lowStockCount', 'inStockCount', 'pendingPurchasesCount', 'receivedCount',
            'stockValue', 'totalUnits', 'healthyPct', 'receivedPct',
            'chartIn', 'chartOut', 'chartTicks', 'movedIn', 'movedOut', 'spark', 'sparkPo',
            'topSuppliers', 'lowStockProducts', 'recentPurchases', 'recentReceipts', 'recentMovements',
            'verifiedAcquisitionValue', 'estimatedLegacyValue', 'mixedValuationValue',
            'quarantinedLegacyStock', 'quarantinedCount', 'purchaseItemsNeedingReview',
            'warehouseDistribution', 'subscriptionUsage', 'planEntitlements', 'supportConfig',
            'billingReminder', 'pendingPaymentCount'
        ));
    }
}
