<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;

class SubscriptionPlan extends Model
{
    public const CODE_INTERNAL = 'INTERNAL';

    public const INTERVAL_MONTHLY = 'MONTHLY';
    public const INTERVAL_YEARLY = 'YEARLY';
    public const INTERVAL_CUSTOM = 'CUSTOM';

    public const BILLING_INTERVALS = [
        self::INTERVAL_MONTHLY => 'Monthly',
        self::INTERVAL_YEARLY => 'Yearly',
        self::INTERVAL_CUSTOM => 'Custom',
    ];

    protected $fillable = [
        'code',
        'tier',
        'name',
        'description',
        'price',
        'billing_interval',
        'is_active',
        'is_recommended',
        'is_internal',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_recommended' => 'boolean',
            'is_internal' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopePubliclyListed($query)
    {
        return $query->where('is_internal', 0);
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionFeature::class, 'subscription_plan_features')
            ->withPivot(['id', 'value'])
            ->withTimestamps();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'subscription_plan_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'subscription_plan_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PlatformAuditLog::class, 'subscription_plan_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'subscription_plan_id');
    }

    public function isFreePlan(): bool
    {
        if ($this->is_internal) {
            return false;
        }

        return strtoupper((string) $this->tier) === Company::ACCESS_FREE
            || bccomp((string) ($this->price ?? '0'), '0', 2) <= 0;
    }

    public function formattedPrice(): string
    {
        $amount = (float) $this->price;
        if ($amount <= 0.0) {
            return 'Rp 0';
        }

        $decimals = fmod($amount, 1.0) === 0.0 ? 0 : 2;

        return 'Rp ' . number_format($amount, $decimals, ',', '.');
    }

    public function billingIntervalLabel(): string
    {
        $key = "subscription.billing_intervals.{$this->billing_interval}";

        return Lang::has($key)
            ? __($key)
            : (self::BILLING_INTERVALS[$this->billing_interval] ?? (string) $this->billing_interval);
    }

    public function tierLabel(): string
    {
        $key = "platform.access_types.{$this->tier}";

        return Lang::has($key)
            ? __($key)
            : (Company::ACCESS_TYPES[$this->tier]['label'] ?? (string) $this->tier);
    }

    public function isCoreSystemPlan(): bool
    {
        $code = strtoupper((string) $this->code);

        return array_key_exists($code, Company::ACCESS_TYPES)
            || $code === self::CODE_INTERNAL;
    }
}
