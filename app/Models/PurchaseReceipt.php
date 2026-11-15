<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseReceipt extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'purchase_id',
        'warehouse_id',
        'receipt_number',
        'status',
        'received_by_user_id',
        'total_gross_amount',
        'total_discount_amount',
        'total_freight_amount',
        'total_non_recoverable_tax',
        'total_recoverable_tax',
        'total_capitalized_cost',
        'posted_at',
        'cancelled_at',
        'idempotency_key',
        'notes',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === 'posted') {
                throw new DomainException('POSTED_GRN_IMMUTABLE: Cannot modify a posted PurchaseReceipt.');
            }
        });
        static::deleting(fn () => throw new DomainException('GRN_DELETE_FORBIDDEN: PurchaseReceipts cannot be deleted.'));
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    public function reversalAdjustment(): HasOne
    {
        return $this->hasOne(StockAdjustment::class, 'reverses_id')->where('reverses_type', 'PurchaseReceipt');
    }
}
