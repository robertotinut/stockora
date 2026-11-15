<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'category_id',
        'unit_id',
        'purchase_price',
        'selling_price',
        'moving_average_cost',
        'stock',
        'inventory_value',
        'legacy_reconciliation_status',
        'min_stock',
        'description',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function productWarehouses(): HasMany
    {
        return $this->hasMany(ProductWarehouse::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function reconciliationAudit(): HasOne
    {
        return $this->hasOne(LegacyReconciliationAudit::class);
    }

    public function unitConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class);
    }
}
