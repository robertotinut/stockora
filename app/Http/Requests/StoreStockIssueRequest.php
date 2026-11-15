<?php

namespace App\Http\Requests;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $companyId = app(TenantContext::class)->id();

        return $this->user() !== null
            && $companyId !== null
            && $this->user()->canAccessModule('stock_issues', 'create', $companyId);
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->requireCompanyId();

        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)->where('is_active', 1)],
            'issue_number' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
