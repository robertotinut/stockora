<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockAdjustment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'adjustment_number',
        'warehouse_id',
        'status',
        'user_id',
        'idempotency_key',
        'reverses_type',
        'reverses_id',
        'reason',
        'posted_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if (in_array($model->getOriginal('status'), ['posted', 'cancelled'], true)) {
                throw new DomainException('IMMUTABLE_ADJUSTMENT: Finalized adjustment cannot be modified.');
            }
        });
        static::deleting(fn () => throw new DomainException('ADJUSTMENT_DELETE_FORBIDDEN: Adjustments cannot be deleted.'));
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function reversalAdjustment(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id')->where('reverses_type', 'StockAdjustment');
    }
}
