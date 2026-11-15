<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class TransferController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $fromWarehouseId = (string) $request->query('from_warehouse_id', '');
        $toWarehouseId = (string) $request->query('to_warehouse_id', '');

        $transfers = StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'user', 'reversalAdjustment'])
            ->withCount('items')
            ->when($search !== '', fn ($q) => $q->where('transfer_number', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%"))
            ->when($fromWarehouseId !== '', fn ($q) => $q->where('from_warehouse_id', (int) $fromWarehouseId))
            ->when($toWarehouseId !== '', fn ($q) => $q->where('to_warehouse_id', (int) $toWarehouseId))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('transfers.index', compact('transfers', 'warehouses', 'search', 'fromWarehouseId', 'toWarehouseId'));
    }

    public function create()
    {
        $warehouses = Warehouse::where('is_active', 1)->orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::with(['unit', 'productWarehouses'])
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();
        $units = Unit::orderBy('name')->get();
        $suggestedNumber = app(\App\Services\DocumentNumberService::class)->previewNextNumber(app(\App\Services\TenantContext::class)->requireCompanyId(), \App\Services\DocumentNumberService::TYPE_TRANSFER);
        $idempotencyKey = (string) Str::uuid();

        return view('transfers.create', compact('warehouses', 'products', 'units', 'suggestedNumber', 'idempotencyKey'));
    }

    public function store(Request $request, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'transfer_number' => ['nullable', 'string', 'max:100'],
            'from_warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'to_warehouse_id' => ['required', 'integer', 'different:from_warehouse_id', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $result = $inventory->postStockTransfer(
                $companyId,
                (int) Auth::id(),
                (int) $validated['from_warehouse_id'],
                (int) $validated['to_warehouse_id'],
                null,
                $validated['idempotency_key'],
                $validated['lines'],
                $validated['notes'] ?? ''
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $transferId = (int) ($result['transfer_id'] ?? $result['transfer']['id']);
        $msg = ($result['idempotent_replay'] ?? false)
            ? __('operations.flash.transfer_replay')
            : __('operations.flash.transfer_posted');

        return redirect()->route('transfers.show', $transferId)->with('success', $msg);
    }

    public function show(StockTransfer $transfer)
    {
        $transfer->load([
            'fromWarehouse',
            'toWarehouse',
            'user',
            'items.product.unit',
            'items.unit',
            'reversalAdjustment',
        ]);

        $movements = StockMovement::query()
            ->with(['product.unit', 'warehouse'])
            ->where('reference_type', 'StockTransfer')
            ->where('reference_id', $transfer->id)
            ->orderBy('id')
            ->get();

        return view('transfers.show', compact('transfer', 'movements'));
    }

    public function print(StockTransfer $transfer)
    {
        $transfer->load([
            'fromWarehouse',
            'toWarehouse',
            'user',
            'items.product.unit',
            'items.unit',
            'reversalAdjustment',
        ]);

        $company = Company::find($transfer->company_id);

        return view('transfers.print', compact('transfer', 'company'));
    }

    public function reverse(Request $request, StockTransfer $transfer, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $inventory->reverseDocument(
                $companyId,
                (int) Auth::id(),
                'StockTransfer',
                $transfer->id,
                null,
                (string) Str::uuid(),
                $validated['reason']
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $revAdj = isset($result['reversal_adjustment_id']) ? StockAdjustment::find((int) $result['reversal_adjustment_id']) : null;
        $revNum = $revAdj->adjustment_number ?? ($result['adjustment_number'] ?? '');

        return redirect()->route('transfers.show', $transfer)
            ->with('success', __('operations.flash.transfer_reversed', ['number' => $transfer->transfer_number, 'rev' => $revNum]));
    }
}
