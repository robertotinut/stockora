<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockIssueItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id',
        'stock_issue_id',
        'product_id',
        'unit_id',
        'conversion_factor',
        'quantity',
        'base_quantity',
        'unit_cost_snapshot',
        'total_cost_snapshot',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(StockIssue::class, 'stock_issue_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
