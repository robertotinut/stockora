<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Models\Supplier;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $suppliers = Supplier::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->withCount('purchases')
            ->withSum(['purchases as active_purchases_sum' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total_amount')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search'));
    }

    public function create()
    {
        $supplier = new Supplier();

        return view('suppliers.form', compact('supplier'));
    }

    public function store(Request $request, TenantContext $tenantContext, SubscriptionPlanService $planService)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('suppliers', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($companyId, $planService, $validated): void {
                $planService->assertCanCreateQuota($companyId, 'max_suppliers', 1);

                Supplier::create([
                    'code' => strtoupper($validated['code']),
                    'name' => $validated['name'],
                    'contact_person' => $validated['contact_person'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);
            });
        } catch (QuotaExceededException $e) {
            return $request->expectsJson()
                ? response()->json($e->toArray(), 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('suppliers.index')->with('success', __('suppliers.flash.created', ['name' => $validated['name']]));
    }

    public function show(Supplier $supplier)
    {
        $purchases = $supplier->purchases()
            ->latest('date')
            ->latest('id')
            ->paginate(10);

        return view('suppliers.show', compact('supplier', 'purchases'));
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('suppliers', 'code')->where('company_id', $companyId)->ignore($supplier->id)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $supplier->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        return redirect()->route('suppliers.index')->with('success', __('suppliers.flash.updated', ['name' => $supplier->name]));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', __('suppliers.flash.has_purchases', ['name' => $supplier->name]));
        }

        $name = $supplier->name;
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('suppliers.flash.deleted', ['name' => $name]));
    }
}
