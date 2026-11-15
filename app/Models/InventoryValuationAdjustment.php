<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValuationAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'stock_movement_id',
        'adjustment_type',
        'adjustment_delta',
        'value_before',
        'value_after',
        'reason',
        'idempotency_key',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new DomainException('APPEND_ONLY_LEDGER_IMMUTABLE: inventory_valuation_adjustments cannot be updated.'));
        static::deleting(fn () => throw new DomainException('APPEND_ONLY_LEDGER_IMMUTABLE: inventory_valuation_adjustments cannot be deleted.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
