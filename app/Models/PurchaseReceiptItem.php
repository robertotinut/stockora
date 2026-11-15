<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'purchase_receipt_id',
        'purchase_id',
        'purchase_item_id',
        'product_id',
        'received_unit_id',
        'base_unit_id',
        'conversion_factor',
        'received_quantity',
        'base_quantity',
        'unit_price',
        'discount_amount',
        'allocated_freight_amount',
        'non_recoverable_tax_amount',
        'recoverable_tax_amount',
        'capitalized_total_cost',
        'unit_cost_base',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function receivedUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'received_unit_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }
}
