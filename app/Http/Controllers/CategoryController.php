<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $categories = Category::query()
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->withCount('products')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', compact('categories', 'search'));
    }

    public function create()
    {
        $category = new Category();

        return view('categories.form', compact('category'));
    }

    public function store(Request $request, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('categories', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->where('company_id', $companyId)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        Category::create([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('categories.index')->with('success', __('products.flash.category_created', ['name' => $validated['name']]));
    }

    public function edit(Category $category)
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category, TenantContext $tenantContext)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100', Rule::unique('categories', 'code')->where('company_id', $companyId)->ignore($category->id)],
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->where('company_id', $companyId)->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category->update([
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('categories.index')->with('success', __('products.flash.category_updated', ['name' => $category->name]));
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', __('products.flash.category_in_use', ['name' => $category->name]));
        }

        $name = $category->name;
        $category->delete();

        return redirect()->route('categories.index')->with('success', __('products.flash.category_deleted', ['name' => $name]));
    }
}
