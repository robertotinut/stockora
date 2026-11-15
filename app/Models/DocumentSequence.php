<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    protected $fillable = [
        'company_id',
        'doc_type',
        'period_key',
        'last_seq',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'last_seq' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
