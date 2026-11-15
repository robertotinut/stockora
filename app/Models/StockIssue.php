<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockIssue extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'issue_number',
        'warehouse_id',
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
                throw new DomainException('IMMUTABLE_ISSUE: Finalized issue cannot be modified.');
            }
        });
        static::deleting(fn () => throw new DomainException('ISSUE_DELETE_FORBIDDEN: Issues cannot be deleted.'));
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
        return $this->hasMany(StockIssueItem::class);
    }

    public function reversalAdjustment(): HasOne
    {
        return $this->hasOne(StockAdjustment::class, 'reverses_id')->where('reverses_type', 'StockIssue');
    }
}
