<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'type',
        'direction',
        'quantity',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'stock_before',
        'stock_after',
        'running_stock_after',
        'running_value_after',
        'valuation_basis',
        'user_id',
        'notes',
        'migration_batch_id',
        'idempotency_key',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new DomainException('APPEND_ONLY_LEDGER_IMMUTABLE: stock_movements rows cannot be updated.'));
        static::deleting(fn () => throw new DomainException('APPEND_ONLY_LEDGER_IMMUTABLE: stock_movements rows cannot be deleted.'));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
