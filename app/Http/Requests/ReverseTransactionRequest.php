<?php

namespace App\Http\Requests;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReverseTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $companyId = app(TenantContext::class)->id();
        if ($this->user() === null || $companyId === null) {
            return false;
        }

        $module = match ($this->input('document_type')) {
            'PurchaseReceipt' => 'grn',
            'StockTransfer' => 'stock_transfers',
            'StockIssue' => 'stock_issues',
            'StockAdjustment' => 'stock_adjustments',
            default => null,
        };

        return $module !== null && $this->user()->canAccessModule($module, 'approve', $companyId);
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['PurchaseReceipt', 'StockIssue', 'StockTransfer', 'StockAdjustment'])],
            'document_id' => ['required', 'integer', 'min:1'],
            'adjustment_number' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
