<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $warehouseId = (string) $request->query('warehouse_id', '');
        $status = (string) $request->query('status', '');
        $docKind = (string) $request->query('doc_kind', '');

        $adjustments = StockAdjustment::query()
            ->with(['warehouse', 'user', 'reversalAdjustment'])
            ->withCount('items')
            ->when($search !== '', fn ($q) => $q->where('adjustment_number', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%"))
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($docKind === 'manual', fn ($q) => $q->whereNull('reverses_type'))
            ->when($docKind === 'reversal', fn ($q) => $q->whereNotNull('reverses_type'))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('adjustments.index', compact('adjustments', 'warehouses', 'search', 'warehouseId', 'status', 'docKind'));
    }

    public function create(TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();
        $adjustment = new StockAdjustment([
            'adjustment_number' => app(\App\Services\DocumentNumberService::class)->previewNextNumber($companyId, \App\Services\DocumentNumberService::TYPE_ADJUSTMENT),
            'status' => 'draft',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $warehouses = Warehouse::where('is_active', 1)->orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::with(['unit', 'productWarehouses'])
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();

        return view('adjustments.form', compact('adjustment', 'warehouses', 'products'));
    }

    public function store(Request $request, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'adjustment_number' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'reason' => ['required', 'string', 'max:500'],
            'submit_action' => ['nullable', Rule::in(['draft', 'post'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.direction' => ['required', Rule::in(['in', 'out'])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $submitAction = $validated['submit_action'] ?? 'draft';

        if ($submitAction === 'post' && !$request->user()->canAccessModule('stock_adjustments', 'approve', $companyId)) {
            abort(403, __('operations.flash.adj_no_direct_post_perm'));
        }

        try {
            if ($submitAction === 'post') {
                $result = $inventory->postStockAdjustment(
                    $companyId,
                    (int) Auth::id(),
                    (int) $validated['warehouse_id'],
                    null,
                    $validated['idempotency_key'],
                    $validated['reason'],
                    $validated['lines']
                );
                $adjId = (int) ($result['adjustment_id'] ?? $result['adjustment']['id']);
                $msg = ($result['idempotent_replay'] ?? false)
                    ? __('operations.flash.adj_replay')
                    : __('operations.flash.adj_direct_posted');
            } else {
                $result = $inventory->saveDraftAdjustment(
                    $companyId,
                    (int) Auth::id(),
                    (int) $validated['warehouse_id'],
                    null,
                    $validated['idempotency_key'],
                    $validated['reason'],
                    $validated['lines']
                );
                $adjId = (int) $result['adjustment_id'];
                $msg = __('operations.flash.adj_draft_saved');
            }
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('adjustments.show', $adjId)->with('success', $msg);
    }

    public function show(StockAdjustment $adjustment)
    {
        $adjustment->load([
            'warehouse',
            'user',
            'items.product.unit',
            'reversalAdjustment',
        ]);

        $movements = StockMovement::query()
            ->with(['product.unit', 'warehouse'])
            ->where('reference_type', 'StockAdjustment')
            ->where('reference_id', $adjustment->id)
            ->orderBy('id')
            ->get();

        return view('adjustments.show', compact('adjustment', 'movements'));
    }

    public function print(StockAdjustment $adjustment)
    {
        $adjustment->load([
            'warehouse',
            'user',
            'items.product.unit',
            'reversalAdjustment',
        ]);

        $company = Company::find($adjustment->company_id);

        return view('adjustments.print', compact('adjustment', 'company'));
    }

    public function edit(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'draft') {
            return redirect()->route('adjustments.show', $adjustment)
                ->with('error', __('operations.flash.adj_only_draft_edit'));
        }

        $adjustment->load('items');
        $warehouses = Warehouse::where('is_active', 1)->orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::with(['unit', 'productWarehouses'])
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();

        return view('adjustments.form', compact('adjustment', 'warehouses', 'products'));
    }

    public function update(Request $request, StockAdjustment $adjustment, InventoryService $inventory, TenantContext $tenantContext)
    {
        if ($adjustment->status !== 'draft') {
            return redirect()->route('adjustments.show', $adjustment)
                ->with('error', __('operations.flash.adj_posted_immutable'));
        }

        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'submit_action' => ['nullable', Rule::in(['draft', 'post'])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.direction' => ['required', Rule::in(['in', 'out'])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'gte:0'],
        ]);

        if (($validated['submit_action'] ?? 'draft') === 'post' && !$request->user()->canAccessModule('stock_adjustments', 'approve', $companyId)) {
            abort(403, __('operations.flash.adj_no_direct_post_perm'));
        }

        try {
            $inventory->saveDraftAdjustment(
                $companyId,
                (int) Auth::id(),
                (int) $adjustment->warehouse_id,
                $adjustment->adjustment_number,
                $adjustment->idempotency_key,
                $validated['reason'],
                $validated['lines'],
                $adjustment->id
            );

            if (($validated['submit_action'] ?? 'draft') === 'post') {
                $inventory->postDraftAdjustment($companyId, (int) Auth::id(), $adjustment->id);
                $msg = __('operations.flash.adj_updated_posted', ['number' => $adjustment->adjustment_number]);
            } else {
                $msg = __('operations.flash.adj_updated_draft', ['number' => $adjustment->adjustment_number]);
            }
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('adjustments.show', $adjustment)->with('success', $msg);
    }

    public function postDraft(StockAdjustment $adjustment, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        try {
            $inventory->postDraftAdjustment($companyId, (int) Auth::id(), $adjustment->id);
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('adjustments.show', $adjustment)
            ->with('success', __('operations.flash.adj_posted', ['number' => $adjustment->adjustment_number]));
    }

    public function reverse(Request $request, StockAdjustment $adjustment, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $inventory->reverseDocument(
                $companyId,
                (int) Auth::id(),
                'StockAdjustment',
                $adjustment->id,
                null,
                (string) Str::uuid(),
                $validated['reason']
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $revAdj = isset($result['reversal_adjustment_id']) ? StockAdjustment::find((int) $result['reversal_adjustment_id']) : null;
        $revNum = $revAdj->adjustment_number ?? ($result['adjustment_number'] ?? '');

        return redirect()->route('adjustments.show', $adjustment)
            ->with('success', __('operations.flash.adj_reversed', ['number' => $adjustment->adjustment_number, 'rev' => $revNum]));
    }
}
