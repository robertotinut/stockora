<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Support\BcMathHelper;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $warehouseId = (string) $request->query('warehouse_id', '');
        $productId = (string) $request->query('product_id', '');
        $type = (string) $request->query('type', '');
        $direction = (string) $request->query('direction', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $baseQuery = StockMovement::query()
            ->with(['product.unit', 'warehouse', 'user'])
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->when($productId !== '', fn ($q) => $q->where('product_id', (int) $productId))
            ->when($type !== '', fn ($q) => $q->where('type', $type))
            ->when($direction !== '', fn ($q) => $q->where('direction', $direction))
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('notes', 'like', "%{$search}%")
                        ->orWhere('reference_type', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($pq) => $pq->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            });

        $inQty = '0.0000';
        $outQty = '0.0000';
        $inVal = '0.0000';
        $outVal = '0.0000';
        foreach ((clone $baseQuery)->select(['direction', 'quantity', 'total_cost'])->get() as $row) {
            $qStr = BcMathHelper::normalize((string) $row->quantity, 4);
            $vStr = BcMathHelper::normalize((string) $row->total_cost, 4);
            if ($row->direction === 'in') {
                $inQty = bcadd($inQty, $qStr, 4);
                $inVal = bcadd($inVal, $vStr, 4);
            } else {
                $outQty = bcadd($outQty, $qStr, 4);
                $outVal = bcadd($outVal, $vStr, 4);
            }
        }

        $movements = $baseQuery
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::orderBy('name')->get(['id', 'code', 'name']);

        return view('stock-movements.index', compact(
            'movements',
            'warehouses',
            'products',
            'search',
            'warehouseId',
            'productId',
            'type',
            'direction',
            'dateFrom',
            'dateTo',
            'inQty',
            'outQty',
            'inVal',
            'outVal'
        ));
    }
}
