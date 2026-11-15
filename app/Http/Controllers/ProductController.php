<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockAdjustmentItem;
use App\Models\StockIssueItem;
use App\Models\StockTransferItem;
use App\Models\Unit;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use App\Support\BcMathHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = (string) $request->query('category_id', '');
        $reconStatus = (string) $request->query('recon_status', '');
        $stockFilter = (string) $request->query('stock_filter', '');

        $products = Product::query()
            ->with(['category', 'unit'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($categoryId !== '', fn ($q) => $q->where('category_id', (int) $categoryId))
            ->when($reconStatus !== '', fn ($q) => $q->where('legacy_reconciliation_status', $reconStatus))
            ->when($stockFilter === 'low', fn ($q) => $q->whereColumn('stock', '<=', 'min_stock')->where('legacy_reconciliation_status', '!=', 'CHAIN_BREAK'))
            ->when($stockFilter === 'zero', fn ($q) => $q->where('stock', '<=', 0))
            ->when($stockFilter === 'quarantined', fn ($q) => $q->where('legacy_reconciliation_status', 'CHAIN_BREAK'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('products.index', compact(
            'products',
            'categories',
            'search',
            'categoryId',
            'reconStatus',
            'stockFilter'
        ));
    }

    public function create()
    {
        $product = new Product([
            'purchase_price' => '0.000000',
            'selling_price' => '0.000000',
            'min_stock' => '0.0000',
        ]);
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();

        return view('products.form', compact('product', 'categories', 'units'));
    }

    public function store(Request $request, TenantContext $tenantContext, SubscriptionPlanService $planService)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('products', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchasePrice = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['purchase_price'], 8), 6);
        $sellingPrice = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['selling_price'], 8), 6);
        $minStock = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['min_stock'], 6), 4);

        BcMathHelper::assertFitsDecimalCapacity($purchasePrice, 20, 6, 'purchase_price');
        BcMathHelper::assertFitsDecimalCapacity($sellingPrice, 20, 6, 'selling_price');
        BcMathHelper::assertFitsDecimalCapacity($minStock, 16, 4, 'min_stock');

        try {
            $product = DB::transaction(function () use ($companyId, $planService, $validated, $purchasePrice, $sellingPrice, $minStock): Product {
                $planService->assertCanCreateQuota($companyId, 'max_products', 1);

                return Product::create([
                    'code' => strtoupper($validated['code']),
                    'name' => $validated['name'],
                    'category_id' => (int) $validated['category_id'],
                    'unit_id' => (int) $validated['unit_id'],
                    'purchase_price' => $purchasePrice,
                    'selling_price' => $sellingPrice,
                    'moving_average_cost' => '0.000000',
                    'stock' => '0.0000',
                    'inventory_value' => '0.0000',
                    'legacy_reconciliation_status' => 'HISTORICALLY_VERIFIED',
                    'min_stock' => $minStock,
                    'description' => $validated['description'] ?? null,
                ]);
            });
        } catch (QuotaExceededException $e) {
            return $request->expectsJson()
                ? response()->json($e->toArray(), 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('products.show', $product)->with('success', __('products.flash.created', ['name' => $product->name, 'code' => $product->code]));
    }

    public function show(Product $product)
    {
        $product->load([
            'category',
            'unit',
            'productWarehouses.warehouse',
            'reconciliationAudit.warehouse',
            'unitConversions.fromUnit',
            'unitConversions.toUnit',
        ]);

        $movements = $product->stockMovements()
            ->with(['warehouse', 'user'])
            ->latest('id')
            ->paginate(15);

        $units = Unit::orderBy('name')->get();

        return view('products.show', compact('product', 'movements', 'units'));
    }

    public function edit(Product $product)
    {
        $categories = Category::orderBy('name')->get();
        $units = Unit::orderBy('name')->get();
        $hasHistory = $product->stockMovements()->exists() || $product->purchaseItems()->exists();

        return view('products.form', compact('product', 'categories', 'units', 'hasHistory'));
    }

    public function update(Request $request, Product $product, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();
        $hasHistory = $product->stockMovements()->exists() || $product->purchaseItems()->exists();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('products', 'code')->where('company_id', $companyId)->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('company_id', $companyId)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($hasHistory && (int) $validated['unit_id'] !== (int) $product->unit_id) {
            return back()->withInput()->with('error', __('products.flash.unit_locked'));
        }

        $purchasePrice = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['purchase_price'], 8), 6);
        $sellingPrice = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['selling_price'], 8), 6);
        $minStock = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['min_stock'], 6), 4);

        BcMathHelper::assertFitsDecimalCapacity($purchasePrice, 20, 6, 'purchase_price');
        BcMathHelper::assertFitsDecimalCapacity($sellingPrice, 20, 6, 'selling_price');
        BcMathHelper::assertFitsDecimalCapacity($minStock, 16, 4, 'min_stock');

        $product->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'category_id' => (int) $validated['category_id'],
            'unit_id' => (int) $validated['unit_id'],
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'min_stock' => $minStock,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('products.show', $product)->with('success', __('products.flash.updated', ['name' => $product->name]));
    }

    public function destroy(Product $product)
    {
        $hasMovements = $product->stockMovements()->exists();
        $hasPo = PurchaseItem::where('product_id', $product->id)->exists();
        $hasTx = StockTransferItem::where('product_id', $product->id)->exists()
            || StockIssueItem::where('product_id', $product->id)->exists()
            || StockAdjustmentItem::where('product_id', $product->id)->exists();
        $hasPwStock = $product->productWarehouses()->where('stock', '>', 0)->exists();

        if ($hasMovements || $hasPo || $hasTx || $hasPwStock || $product->legacy_reconciliation_status === 'CHAIN_BREAK') {
            return back()->with('error', __('products.flash.delete_blocked', ['code' => $product->code]));
        }

        $product->productWarehouses()->delete();
        $product->unitConversions()->delete();
        $code = $product->code;
        $product->delete();

        return redirect()->route('products.index')->with('success', __('products.flash.deleted', ['code' => $code]));
    }
}
