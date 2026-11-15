<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Purchase;
use App\Models\PurchaseReceipt;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
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

class GrnController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $warehouseId = (string) $request->query('warehouse_id', '');
        $status = (string) $request->query('status', '');

        $receipts = PurchaseReceipt::query()
            ->with(['purchase.supplier', 'warehouse', 'receivedBy', 'reversalAdjustment'])
            ->withCount('items')
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('receipt_number', 'like', "%{$search}%")
                        ->orWhereHas('purchase', fn ($pq) => $pq->where('purchase_number', 'like', "%{$search}%"));
                });
            })
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('grns.index', compact('receipts', 'warehouses', 'search', 'warehouseId', 'status'));
    }

    public function create(Request $request)
    {
        $receivablePos = Purchase::query()
            ->with('supplier')
            ->whereIn('status', ['pending', 'ordered', 'partial'])
            ->latest('id')
            ->get();

        $selectedPoId = $request->query('purchase_id');
        $selectedPurchase = null;
        if ($selectedPoId) {
            $selectedPurchase = Purchase::query()
                ->with(['supplier', 'items.product.unit', 'items.unit'])
                ->whereIn('status', ['pending', 'ordered', 'partial'])
                ->find($selectedPoId);
        } elseif ($receivablePos->count() === 1) {
            $selectedPurchase = Purchase::query()
                ->with(['supplier', 'items.product.unit', 'items.unit'])
                ->find($receivablePos->first()->id);
        }

        $warehouses = Warehouse::where('is_active', 1)->orderByDesc('is_default')->orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $companyId = app(TenantContext::class)->requireCompanyId();
        $suggestedNumber = app(\App\Services\DocumentNumberService::class)->previewNextNumber($companyId, \App\Services\DocumentNumberService::TYPE_GRN);
        $idempotencyKey = (string) Str::uuid();

        return view('grns.create', compact(
            'receivablePos',
            'selectedPurchase',
            'warehouses',
            'units',
            'suggestedNumber',
            'idempotencyKey'
        ));
    }

    public function store(Request $request, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'purchase_id' => ['required', 'integer', Rule::exists('purchases', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_item_id' => ['required', 'integer'],
            'lines.*.received_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'lines.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.allocated_freight_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.non_recoverable_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.recoverable_tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $activeLines = [];
        foreach ($validated['lines'] as $line) {
            $qty = (string) ($line['received_quantity'] ?? '0');
            if (is_numeric($qty) && bccomp((string) $qty, '0', 4) > 0) {
                $activeLines[] = [
                    'purchase_item_id' => (int) $line['purchase_item_id'],
                    'received_unit_id' => (int) $line['received_unit_id'],
                    'received_quantity' => (string) $line['received_quantity'],
                    'unit_price' => $line['unit_price'] ?? null,
                    'discount_amount' => (string) ($line['discount_amount'] ?? '0'),
                    'allocated_freight_amount' => (string) ($line['allocated_freight_amount'] ?? '0'),
                    'non_recoverable_tax_amount' => (string) ($line['non_recoverable_tax_amount'] ?? '0'),
                    'recoverable_tax_amount' => (string) ($line['recoverable_tax_amount'] ?? '0'),
                ];
            }
        }

        if ($activeLines === []) {
            return back()->withInput()->with('error', __('purchases.flash.grn_empty_qty'));
        }

        try {
            $result = $inventory->postPurchaseReceipt(
                $companyId,
                (int) Auth::id(),
                (int) $validated['purchase_id'],
                (int) $validated['warehouse_id'],
                null,
                $validated['idempotency_key'],
                $activeLines,
                $validated['notes'] ?? ''
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $receiptId = $result['receipt']['id'];
        $msg = ($result['idempotent_replay'] ?? false)
            ? __('purchases.flash.grn_replay')
            : __('purchases.flash.grn_posted', ['number' => $result['receipt']['receipt_number']]);

        return redirect()->route('grns.show', $receiptId)->with('success', $msg);
    }

    public function show(PurchaseReceipt $grn)
    {
        $grn->load([
            'purchase.supplier',
            'warehouse',
            'receivedBy',
            'items.product.unit',
            'items.receivedUnit',
            'items.baseUnit',
        ]);

        $movements = StockMovement::query()
            ->with(['product.unit', 'warehouse'])
            ->where('reference_type', 'PurchaseReceipt')
            ->where('reference_id', $grn->id)
            ->orderBy('id')
            ->get();

        $reversalAdjustment = StockAdjustment::query()
            ->where('reverses_type', 'PurchaseReceipt')
            ->where('reverses_id', $grn->id)
            ->first();

        return view('grns.show', compact('grn', 'movements', 'reversalAdjustment'));
    }

    public function print(PurchaseReceipt $grn)
    {
        $grn->load([
            'purchase.supplier',
            'purchase.items.unit',
            'warehouse',
            'receivedBy',
            'items.product.unit',
            'items.receivedUnit',
            'items.baseUnit',
        ]);

        $reversalAdjustment = StockAdjustment::query()
            ->where('reverses_type', 'PurchaseReceipt')
            ->where('reverses_id', $grn->id)
            ->first();

        $company = Company::find($grn->company_id);

        return view('grns.print', compact('grn', 'reversalAdjustment', 'company'));
    }

    public function reverse(Request $request, PurchaseReceipt $grn, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $inventory->reverseDocument(
                $companyId,
                (int) Auth::id(),
                'PurchaseReceipt',
                $grn->id,
                null,
                (string) Str::uuid(),
                $validated['reason']
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $createdNumber = '';
        if (isset($result['reversal_adjustment_id'])) {
            $revModel = StockAdjustment::find((int) $result['reversal_adjustment_id']);
            if ($revModel) {
                $createdNumber = $revModel->adjustment_number;
            }
        } elseif (isset($result['reversal']['adjustment_number'])) {
            $createdNumber = $result['reversal']['adjustment_number'];
        }

        return redirect()->route('grns.show', $grn)
            ->with('success', __('purchases.flash.grn_reversed', ['number' => $grn->receipt_number, 'rev' => $createdNumber]));
    }
}
