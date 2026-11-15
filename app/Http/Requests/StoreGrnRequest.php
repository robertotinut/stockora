<?php

namespace App\Http\Requests;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGrnRequest extends FormRequest
{
    public function authorize(): bool
    {
        $companyId = app(TenantContext::class)->id();

        return $this->user() !== null
            && $companyId !== null
            && $this->user()->canAccessModule('grn', 'create', $companyId);
    }

    public function rules(): array
    {
        $companyId = app(TenantContext::class)->requireCompanyId();

        return [
            'purchase_id' => ['required', 'integer', Rule::exists('purchases', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $companyId)->where('is_active', 1)],
            'receipt_number' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_item_id' => ['required', 'integer', Rule::exists('purchase_items', 'id')->where('company_id', $companyId)],
            'lines.*.received_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'lines.*.received_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.allocated_freight_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.non_recoverable_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.recoverable_tax_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
