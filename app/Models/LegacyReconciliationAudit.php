<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyReconciliationAudit extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'product_id',
        'warehouse_id',
        'classification',
        'legacy_product_stock',
        'computed_opening_qty',
        'action_taken',
        'blocking_reason',
        'evidence_json',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
