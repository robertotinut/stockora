<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWarehouse extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'stock',
        'moving_average_cost',
        'inventory_value',
        'valuation_basis',
        'last_reconciled_at',
    ];

    protected function casts(): array
    {
        return ['last_reconciled_at' => 'datetime'];
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
}
