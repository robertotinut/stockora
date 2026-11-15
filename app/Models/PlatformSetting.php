<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value',
        'description',
        'updated_by_platform_admin_id',
    ];

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'updated_by_platform_admin_id');
    }
}
