<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockIssue;
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

class StockIssueController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $warehouseId = (string) $request->query('warehouse_id', '');

        $issues = StockIssue::query()
            ->with(['warehouse', 'user', 'reversalAdjustment'])
            ->withCount('items')
            ->when($search !== '', fn ($q) => $q->where('issue_number', 'like', "%{$search}%")->orWhere('notes', 'like', "%{$search}%"))
            ->when($warehouseId !== '', fn ($q) => $q->where('warehouse_id', (int) $warehouseId))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $warehouses = Warehouse::orderByDesc('is_default')->orderBy('name')->get();

        return view('issues.index', compact('issues', 'warehouses', 'search', 'warehouseId'));
    }

    public function create()
    {
        $warehouses = Warehouse::where('is_active', 1)->orderByDesc('is_default')->orderBy('name')->get();
        $products = Product::with(['unit', 'productWarehouses'])
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();
        $units = Unit::orderBy('name')->get();
        $suggestedNumber = app(\App\Services\DocumentNumberService::class)->previewNextNumber(app(\App\Services\TenantContext::class)->requireCompanyId(), \App\Services\DocumentNumberService::TYPE_ISSUE);
        $idempotencyKey = (string) Str::uuid();

        return view('issues.create', compact('warehouses', 'products', 'units', 'suggestedNumber', 'idempotencyKey'));
    }

    public function store(Request $request, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'issue_number' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $result = $inventory->postStockIssue(
                $companyId,
                (int) Auth::id(),
                (int) $validated['warehouse_id'],
                null,
                $validated['idempotency_key'],
                $validated['lines'],
                $validated['notes'] ?? ''
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $issueId = (int) ($result['issue_id'] ?? $result['issue']['id']);
        $msg = ($result['idempotent_replay'] ?? false)
            ? __('operations.flash.issue_replay')
            : __('operations.flash.issue_posted');

        return redirect()->route('issues.show', $issueId)->with('success', $msg);
    }

    public function show(StockIssue $issue)
    {
        $issue->load([
            'warehouse',
            'user',
            'items.product.unit',
            'items.unit',
            'reversalAdjustment',
        ]);

        $movements = StockMovement::query()
            ->with(['product.unit', 'warehouse'])
            ->where('reference_type', 'StockIssue')
            ->where('reference_id', $issue->id)
            ->orderBy('id')
            ->get();

        return view('issues.show', compact('issue', 'movements'));
    }

    public function print(StockIssue $issue)
    {
        $issue->load([
            'warehouse',
            'user',
            'items.product.unit',
            'items.unit',
            'reversalAdjustment',
        ]);

        $company = Company::find($issue->company_id);

        return view('issues.print', compact('issue', 'company'));
    }

    public function reverse(Request $request, StockIssue $issue, InventoryService $inventory, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = $inventory->reverseDocument(
                $companyId,
                (int) Auth::id(),
                'StockIssue',
                $issue->id,
                null,
                (string) Str::uuid(),
                $validated['reason']
            );
        } catch (DomainException | InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $revAdj = isset($result['reversal_adjustment_id']) ? StockAdjustment::find((int) $result['reversal_adjustment_id']) : null;
        $revNum = $revAdj->adjustment_number ?? ($result['adjustment_number'] ?? '');

        return redirect()->route('issues.show', $issue)
            ->with('success', __('operations.flash.issue_reversed', ['number' => $issue->issue_number, 'rev' => $revNum]));
    }
}
