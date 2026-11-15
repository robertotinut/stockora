<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class PlatformAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'platform_audit_logs';

    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'subscription_feature_id',
        'subscription_payment_id',
        'platform_admin_id',
        'actor_user_id',
        'action',
        'previous_values',
        'new_values',
        'note',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Platform audit log records are append-only and cannot be modified.');
        });

        static::deleting(function (): void {
            throw new LogicException('Platform audit log records are append-only and cannot be deleted.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function subscriptionFeature(): BelongsTo
    {
        return $this->belongsTo(SubscriptionFeature::class, 'subscription_feature_id');
    }

    public function subscriptionPayment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
