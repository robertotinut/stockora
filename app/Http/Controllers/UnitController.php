<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Services\TenantContext;
use App\Support\BcMathHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $units = Unit::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('symbol', 'like', "%{$search}%");
                });
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $conversions = UnitConversion::with(['product', 'fromUnit', 'toUnit'])
            ->orderBy('product_id')
            ->orderByDesc('id')
            ->get();

        $allUnits = Unit::orderBy('name')->get();
        $products = Product::orderBy('name')->get(['id', 'code', 'name', 'unit_id']);

        return view('units.index', compact('units', 'conversions', 'allUnits', 'products', 'search'));
    }

    public function create()
    {
        $unit = new Unit();

        return view('units.form', compact('unit'));
    }

    public function store(Request $request, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->where('company_id', $companyId)],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('units', 'symbol')->where('company_id', $companyId)],
        ]);

        Unit::create($validated);

        return redirect()->route('units.index')->with('success', __('products.flash.unit_created', ['name' => "{$validated['name']} ({$validated['symbol']})"]));
    }

    public function edit(Unit $unit)
    {
        return view('units.form', compact('unit'));
    }

    public function update(Request $request, Unit $unit, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')->where('company_id', $companyId)->ignore($unit->id)],
            'symbol' => ['required', 'string', 'max:20', Rule::unique('units', 'symbol')->where('company_id', $companyId)->ignore($unit->id)],
        ]);

        $unit->update($validated);

        return redirect()->route('units.index')->with('success', __('products.flash.unit_updated', ['name' => $unit->name]));
    }

    public function destroy(Unit $unit)
    {
        $inProducts = $unit->products()->exists();
        $inConversions = UnitConversion::where('from_unit_id', $unit->id)->orWhere('to_unit_id', $unit->id)->exists();

        if ($inProducts || $inConversions) {
            return back()->with('error', __('products.flash.unit_in_use', ['name' => $unit->name]));
        }

        $name = $unit->name;
        $unit->delete();

        return redirect()->route('units.index')->with('success', __('products.flash.unit_deleted', ['name' => $name]));
    }

    public function storeConversion(Request $request, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'from_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'to_unit_id' => ['required', 'integer', 'different:from_unit_id', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'factor' => ['required', 'numeric', 'gt:0'],
        ]);

        $productId = isset($validated['product_id']) && $validated['product_id'] !== '' ? (int) $validated['product_id'] : null;

        $exists = UnitConversion::query()
            ->where('from_unit_id', (int) $validated['from_unit_id'])
            ->where('to_unit_id', (int) $validated['to_unit_id'])
            ->when($productId === null, fn ($q) => $q->whereNull('product_id'), fn ($q) => $q->where('product_id', $productId))
            ->exists();

        if ($exists) {
            return back()->withInput()->with('error', __('products.flash.conversion_duplicate'));
        }

        $factor = BcMathHelper::roundHalfUp(BcMathHelper::normalize((string) $validated['factor'], 10), 8);
        BcMathHelper::assertFitsDecimalCapacity($factor, 20, 8, 'factor');

        UnitConversion::create([
            'product_id' => $productId,
            'from_unit_id' => (int) $validated['from_unit_id'],
            'to_unit_id' => (int) $validated['to_unit_id'],
            'factor' => $factor,
            'is_active' => true,
        ]);

        return back()->with('success', __('products.flash.conversion_created'));
    }

    public function destroyConversion(UnitConversion $unitConversion)
    {
        $unitConversion->delete();

        return back()->with('success', __('products.flash.conversion_deleted'));
    }
}
