<?php

namespace App\Http\Requests;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $companyId = app(TenantContext::class)->id();

        return $this->user() !== null
            && $companyId !== null
            && $this->user()->canAccessModule('stock_adjustments', 'approve', $companyId);
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->requireCompanyId();

        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)->where('is_active', 1)],
            'adjustment_number' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'reason' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.direction' => ['required', Rule::in(['in', 'out'])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
