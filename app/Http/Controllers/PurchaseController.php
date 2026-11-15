<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Models\Company;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use App\Support\BcMathHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $paymentStatus = (string) $request->query('payment_status', '');
        $needsReview = (string) $request->query('needs_review', '');

        $purchases = Purchase::query()
            ->with(['supplier', 'user'])
            ->withCount(['items', 'receipts'])
            ->withCount(['items as review_items_count' => fn ($q) => $q->where(fn ($sub) => $sub->where('conversion_status', 'NEEDS_REVIEW')->orWhere('cost_breakdown_status', 'UNKNOWN_COMPONENTS'))])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('purchase_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($paymentStatus !== '', fn ($q) => $q->where('payment_status', $paymentStatus))
            ->when($needsReview === '1', fn ($q) => $q->whereHas('items', fn ($iq) => $iq->where('conversion_status', 'NEEDS_REVIEW')->orWhere('cost_breakdown_status', 'UNKNOWN_COMPONENTS')))
            ->latest('date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchases.index', compact('purchases', 'search', 'status', 'paymentStatus', 'needsReview'));
    }

    public function create(Request $request)
    {
        $suggestedNumber = app(\App\Services\DocumentNumberService::class)->previewNextNumber((int) app(\App\Services\TenantContext::class)->requireCompanyId(), \App\Services\DocumentNumberService::TYPE_PURCHASE);
        $purchase = new Purchase([
            'purchase_number' => $suggestedNumber,
            'date' => now()->toDateString(),
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);

        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::with('unit')
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();
        $units = Unit::orderBy('name')->get();
        $preselectedProductId = $request->query('product_id');

        return view('purchases.form', compact('purchase', 'suppliers', 'products', 'units', 'preselectedProductId'));
    }

    public function store(Request $request, TenantContext $tenantContext, SubscriptionPlanService $planService)
    {
        $companyId = $tenantContext->requireCompanyId();
        $validated = $this->validatePoRequest($request, $companyId);

        $prepared = $this->prepareValidatedLines($companyId, $validated['items']);
        if ($prepared['error'] !== null) {
            return back()->withInput()->with('error', $prepared['error']);
        }

        $status = $request->input('submit_action') === 'confirm' ? 'ordered' : 'pending';

        try {
            $purchase = DB::transaction(function () use ($companyId, $planService, $validated, $prepared, $status): Purchase {
                $planService->assertCanCreateQuota($companyId, 'max_monthly_purchases', 1);

                $po = Purchase::create([
                    'purchase_number' => app(\App\Services\DocumentNumberService::class)->generateNextNumber($companyId, \App\Services\DocumentNumberService::TYPE_PURCHASE),
                    'supplier_id' => (int) $validated['supplier_id'],
                    'user_id' => (int) Auth::id(),
                    'date' => $validated['date'],
                    'total_amount' => $prepared['total_amount'],
                    'status' => $status,
                    'payment_status' => $validated['payment_status'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($prepared['lines'] as $line) {
                    PurchaseItem::create(array_merge($line, ['purchase_id' => $po->id]));
                }

                return $po;
            });
        } catch (QuotaExceededException $e) {
            return $request->expectsJson()
                ? response()->json($e->toArray(), 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('purchases.flash.created', ['number' => $purchase->purchase_number, 'status' => $purchase->status]));
    }

    public function show(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'user',
            'items.product.unit',
            'items.unit',
            'receipts' => fn ($q) => $q->with(['warehouse', 'receivedBy', 'items', 'reversalAdjustment'])->latest('id'),
        ]);

        return view('purchases.show', compact('purchase'));
    }

    public function print(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'user',
            'items.product.unit',
            'items.unit',
            'receipts' => fn ($q) => $q->with(['warehouse', 'receivedBy'])->latest('id'),
        ]);
        $company = Company::find($purchase->company_id);

        return view('purchases.print', compact('purchase', 'company'));
    }

    public function edit(Purchase $purchase)
    {
        if (!$this->isEditable($purchase)) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', __('purchases.flash.not_editable'));
        }

        $purchase->load('items');
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::with('unit')
            ->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK')
            ->orderBy('name')
            ->get();
        $units = Unit::orderBy('name')->get();
        $preselectedProductId = null;

        return view('purchases.form', compact('purchase', 'suppliers', 'products', 'units', 'preselectedProductId'));
    }

    public function update(Request $request, Purchase $purchase, TenantContext $tenantContext)
    {
        if (!$this->isEditable($purchase)) {
            return redirect()->route('purchases.show', $purchase)
                ->with('error', __('purchases.flash.not_editable'));
        }

        $companyId = $tenantContext->requireCompanyId();
        $validated = $this->validatePoRequest($request, $companyId, $purchase->id);

        $prepared = $this->prepareValidatedLines($companyId, $validated['items']);
        if ($prepared['error'] !== null) {
            return back()->withInput()->with('error', $prepared['error']);
        }

        DB::transaction(function () use ($purchase, $validated, $prepared, $request): void {
            $newStatus = $purchase->status === 'pending' && $request->input('submit_action') === 'confirm'
                ? 'ordered'
                : $purchase->status;

            $purchase->update([
                'supplier_id' => (int) $validated['supplier_id'],
                'date' => $validated['date'],
                'total_amount' => $prepared['total_amount'],
                'status' => $newStatus,
                'payment_status' => $validated['payment_status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $purchase->items()->delete();
            foreach ($prepared['lines'] as $line) {
                PurchaseItem::create(array_merge($line, ['purchase_id' => $purchase->id]));
            }
        });

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('purchases.flash.updated', ['number' => $purchase->purchase_number]));
    }

    public function confirm(Purchase $purchase)
    {
        if ($purchase->status !== 'pending') {
            return back()->with('error', __('purchases.flash.confirm_only_pending'));
        }

        $purchase->update(['status' => 'ordered']);

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('purchases.flash.confirmed', ['number' => $purchase->purchase_number]));
    }

    public function cancel(Purchase $purchase)
    {
        if (!in_array($purchase->status, ['pending', 'ordered'], true)) {
            return back()->with('error', __('purchases.flash.not_editable'));
        }

        $hasUnreversedGrn = $purchase->receipts()
            ->where('status', 'posted')
            ->whereDoesntHave('reversalAdjustment')
            ->exists();
        if ($hasUnreversedGrn) {
            return back()->with('error', __('purchases.flash.cancel_blocked_grn'));
        }

        $purchase->update(['status' => 'cancelled']);

        return redirect()->route('purchases.show', $purchase)
            ->with('success', __('purchases.flash.cancelled', ['number' => $purchase->purchase_number]));
    }

    public function updatePayment(Request $request, Purchase $purchase)
    {
        if ($purchase->status === 'cancelled') {
            return back()->with('error', __('purchases.flash.payment_cancelled'));
        }

        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid'])],
        ]);

        $purchase->update(['payment_status' => $validated['payment_status']]);

        return back()->with('success', __('purchases.flash.payment_updated', ['number' => $purchase->purchase_number, 'status' => $validated['payment_status']]));
    }

    public function destroy(Purchase $purchase)
    {
        if ($purchase->status !== 'pending' || $purchase->receipts()->exists() || $purchase->items()->where('received_quantity', '>', 0)->exists()) {
            return back()->with('error', __('purchases.flash.delete_blocked'));
        }

        $poNumber = $purchase->purchase_number;
        DB::transaction(function () use ($purchase): void {
            $purchase->items()->delete();
            $purchase->delete();
        });

        return redirect()->route('purchases.index')->with('success', __('purchases.flash.deleted', ['number' => $poNumber]));
    }

    private function isEditable(Purchase $purchase): bool
    {
        return in_array($purchase->status, ['pending', 'ordered'], true) && !$purchase->receipts()->exists();
    }

    private function validatePoRequest(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $uniqueRule = Rule::unique('purchases', 'purchase_number')->where('company_id', $companyId);
        if ($ignoreId !== null) {
            $uniqueRule->ignore($ignoreId);
        }

        return $request->validate([
            'purchase_number' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date'],
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'items.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ]);
    }

    private function prepareValidatedLines(int $companyId, array $rawItems): array
    {
        $lines = [];
        $totalAmount = '0.0000';

        foreach ($rawItems as $row) {
            $product = Product::find((int) $row['product_id']);
            if (!$product) {
                return ['error' => __('purchases.flash.line_product_missing'), 'lines' => [], 'total_amount' => '0.0000'];
            }
            if ($product->legacy_reconciliation_status === 'CHAIN_BREAK') {
                return ['error' => __('purchases.flash.line_product_quarantined', ['code' => $product->code]), 'lines' => [], 'total_amount' => '0.0000'];
            }

            $unitId = (int) $row['unit_id'];
            if ($unitId !== (int) $product->unit_id) {
                $hasConv = UnitConversion::query()
                    ->where('company_id', $companyId)
                    ->where('from_unit_id', $unitId)
                    ->where('to_unit_id', (int) $product->unit_id)
                    ->where('is_active', 1)
                    ->where(fn ($q) => $q->where('product_id', $product->id)->orWhereNull('product_id'))
                    ->exists();

                if (!$hasConv) {
                    return ['error' => __('purchases.flash.line_conversion_missing', ['unit' => $unitId, 'code' => $product->code]), 'lines' => [], 'total_amount' => '0.0000'];
                }
            }

            $qty = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $row['quantity'], 6), 4);
            $unitPrice = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $row['unit_price'], 8), 6);
            $subtotal = BcMathHelper::roundHalfUp(bcmul($qty, $unitPrice, 10), 4);

            BcMathHelper::assertFitsDecimalCapacity($qty, 16, 4, 'quantity');
            BcMathHelper::assertFitsDecimalCapacity($unitPrice, 20, 6, 'unit_price');
            BcMathHelper::assertFitsDecimalCapacity($subtotal, 28, 4, 'subtotal');

            $totalAmount = bcadd($totalAmount, $subtotal, 4);
            BcMathHelper::assertFitsDecimalCapacity($totalAmount, 28, 4, 'total_amount');

            $lines[] = [
                'product_id' => $product->id,
                'unit_id' => $unitId,
                'quantity' => $qty,
                'received_quantity' => '0.0000',
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'conversion_status' => 'VERIFIED',
                'cost_breakdown_status' => 'VERIFIED',
            ];
        }

        return ['error' => null, 'lines' => $lines, 'total_amount' => $totalAmount];
    }
}
