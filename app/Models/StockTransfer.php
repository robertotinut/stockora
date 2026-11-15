<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockTransfer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'transfer_number',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'user_id',
        'idempotency_key',
        'notes',
        'posted_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if (in_array($model->getOriginal('status'), ['posted', 'cancelled'], true)) {
                throw new DomainException('IMMUTABLE_TRANSFER: Finalized transfer cannot be modified.');
            }
        });
        static::deleting(fn () => throw new DomainException('TRANSFER_DELETE_FORBIDDEN: Transfers cannot be deleted.'));
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function reversalAdjustment(): HasOne
    {
        return $this->hasOne(StockAdjustment::class, 'reverses_id')->where('reverses_type', 'StockTransfer');
    }
}
