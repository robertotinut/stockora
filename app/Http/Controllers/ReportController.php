<?php

namespace App\Http\Controllers;

use App\Models\LegacyReconciliationAudit;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\TenantContext;
use App\Support\BcMathHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = (string) $request->query('date_from', Carbon::today()->subDays(30)->toDateString());
        $dateTo = (string) $request->query('date_to', Carbon::today()->toDateString());
        $warehouseId = (string) $request->query('warehouse_id', '');

        $data = $this->buildReportData($dateFrom, $dateTo, $warehouseId);
        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('reports.index', array_merge($data, compact('dateFrom', 'dateTo', 'warehouseId', 'warehouses')));
    }

    public function export(Request $request, TenantContext $tenantContext): StreamedResponse
    {
        $type = (string) $request->query('type', 'valuation');
        $dateFrom = (string) $request->query('date_from', Carbon::today()->subDays(30)->toDateString());
        $dateTo = (string) $request->query('date_to', Carbon::today()->toDateString());
        $warehouseId = (string) $request->query('warehouse_id', '');
        $companyId = $tenantContext->requireCompanyId();

        $data = $this->buildReportData($dateFrom, $dateTo, $warehouseId);
        $filename = "stockora-{$type}-c{$companyId}-" . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($type, $data): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            if ($type === 'movements') {
                fputcsv($out, __('reports.csv.movements_headers'));
                foreach ($data['periodMovements'] as $mv) {
                    fputcsv($out, [
                        $mv->created_at ? Carbon::parse($mv->created_at)->format('Y-m-d H:i:s') : '',
                        $mv->warehouse->code ?? '',
                        $mv->product->code ?? '',
                        $mv->product->name ?? '',
                        $mv->type,
                        $mv->direction,
                        $mv->quantity,
                        $mv->product->unit->symbol ?? '',
                        $mv->unit_cost,
                        $mv->total_cost,
                        $mv->running_stock_after,
                        $mv->valuation_basis,
                        trim(($mv->reference_type ?? '') . ' #' . ($mv->reference_id ?? '')),
                        $mv->notes ?? '',
                    ]);
                }
            } elseif ($type === 'exceptions') {
                fputcsv($out, __('reports.csv.exceptions_headers'));
                foreach ($data['quarantinedAudits'] as $audit) {
                    fputcsv($out, [
                        'LEGACY_QUARANTINE',
                        $audit->product->code ?? '',
                        $audit->product->name ?? '',
                        $audit->blocking_reason ?? 'Chain break detected',
                        $audit->classification . ' / ' . $audit->action_taken,
                    ]);
                }
                foreach ($data['lowStockProducts'] as $lp) {
                    fputcsv($out, [
                        'LOW_STOCK',
                        $lp->code,
                        $lp->name,
                        __('reports.csv.low_stock_detail', [
                            'stock' => $lp->stock,
                            'min' => $lp->min_stock,
                            'unit' => $lp->unit->symbol ?? '',
                        ]),
                        'REORDER_RECOMMENDED',
                    ]);
                }
                foreach ($data['flaggedPurchaseItems'] as $pi) {
                    fputcsv($out, [
                        'LEGACY_PO_REVIEW',
                        $pi->purchase->purchase_number ?? '',
                        $pi->product->code ?? '',
                        'Qty ' . $pi->quantity . ' @ ' . $pi->unit_price,
                        $pi->conversion_status . ' / ' . $pi->cost_breakdown_status,
                    ]);
                }
            } else {
                fputcsv($out, __('reports.csv.valuation_headers'));
                foreach ($data['valuationRows'] as $pw) {
                    fputcsv($out, [
                        $pw->warehouse->code ?? '',
                        $pw->product->code ?? '',
                        $pw->product->name ?? '',
                        $pw->product->category->name ?? '',
                        $pw->stock,
                        $pw->product->unit->symbol ?? '',
                        $pw->moving_average_cost,
                        $pw->inventory_value,
                        $pw->valuation_basis,
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildReportData(string $dateFrom, string $dateTo, string $warehouseId): array
    {
        $valuationRows = ProductWarehouse::query()
            ->with(['warehouse', 'product.category', 'product.unit'])
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->where('valuation_basis', '!=', 'UNVERIFIED_QUARANTINED')
            ->orderBy('warehouse_id')
            ->orderByDesc('inventory_value')
            ->get();

        $warehouseSummary = [];
        $grandTrustedQty = '0.0000';
        $grandTrustedVal = '0.0000';

        foreach ($valuationRows as $pw) {
            $wid = $pw->warehouse_id;
            if (!isset($warehouseSummary[$wid])) {
                $warehouseSummary[$wid] = [
                    'code' => $pw->warehouse->code ?? '-',
                    'name' => $pw->warehouse->name ?? '-',
                    'sku_count' => 0,
                    'total_qty' => '0.0000',
                    'total_value' => '0.0000',
                    'verified_val' => '0.0000',
                    'legacy_val' => '0.0000',
                    'mixed_val' => '0.0000',
                ];
            }

            $qty = BcMathHelper::normalize((string) $pw->stock, 4);
            $val = BcMathHelper::normalize((string) $pw->inventory_value, 4);

            if (bccomp($qty, '0.0000', 4) > 0) {
                $warehouseSummary[$wid]['sku_count']++;
            }
            $warehouseSummary[$wid]['total_qty'] = bcadd($warehouseSummary[$wid]['total_qty'], $qty, 4);
            $warehouseSummary[$wid]['total_value'] = bcadd($warehouseSummary[$wid]['total_value'], $val, 4);

            if ($pw->valuation_basis === 'VERIFIED_ACQUISITION') {
                $warehouseSummary[$wid]['verified_val'] = bcadd($warehouseSummary[$wid]['verified_val'], $val, 4);
            } elseif ($pw->valuation_basis === 'ESTIMATED_LEGACY_BASELINE') {
                $warehouseSummary[$wid]['legacy_val'] = bcadd($warehouseSummary[$wid]['legacy_val'], $val, 4);
            } elseif ($pw->valuation_basis === 'MIXED_BASELINE_AND_ACQUISITION') {
                $warehouseSummary[$wid]['mixed_val'] = bcadd($warehouseSummary[$wid]['mixed_val'], $val, 4);
            }

            $grandTrustedQty = bcadd($grandTrustedQty, $qty, 4);
            $grandTrustedVal = bcadd($grandTrustedVal, $val, 4);
        }

        $periodMovements = StockMovement::query()
            ->with(['product.unit', 'warehouse', 'user'])
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest('id')
            ->get();

        $movementSummary = [];
        foreach ($periodMovements as $mv) {
            $key = $mv->type . '|' . $mv->direction;
            if (!isset($movementSummary[$key])) {
                $movementSummary[$key] = [
                    'type' => $mv->type,
                    'direction' => $mv->direction,
                    'tx_count' => 0,
                    'total_qty' => '0.0000',
                    'total_cost' => '0.0000',
                ];
            }
            $movementSummary[$key]['tx_count']++;
            $movementSummary[$key]['total_qty'] = bcadd($movementSummary[$key]['total_qty'], BcMathHelper::normalize((string) $mv->quantity, 4), 4);
            $movementSummary[$key]['total_cost'] = bcadd($movementSummary[$key]['total_cost'], BcMathHelper::normalize((string) $mv->total_cost, 4), 4);
        }

        $lowStockProducts = Product::query()
            ->with(['category', 'unit'])
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();

        $quarantinedAudits = LegacyReconciliationAudit::query()
            ->with(['product.unit', 'warehouse'])
            ->where('action_taken', 'BLOCKED_NEEDS_REVIEW')
            ->orderBy('id')
            ->get();

        $flaggedPurchaseItems = PurchaseItem::query()
            ->with(['purchase.supplier', 'product.unit', 'unit'])
            ->where(function ($q): void {
                $q->where('conversion_status', 'NEEDS_REVIEW')
                    ->orWhere('cost_breakdown_status', 'UNKNOWN_COMPONENTS');
            })
            ->orderBy('id')
            ->get();

        return compact(
            'valuationRows',
            'warehouseSummary',
            'grandTrustedQty',
            'grandTrustedVal',
            'periodMovements',
            'movementSummary',
            'lowStockProducts',
            'quarantinedAudits',
            'flaggedPurchaseItems'
        );
    }
}
