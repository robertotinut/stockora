<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanFeature extends Model
{
    protected $table = 'subscription_plan_features';

    protected $fillable = [
        'subscription_plan_id',
        'subscription_feature_id',
        'value',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(SubscriptionFeature::class, 'subscription_feature_id');
    }
}
