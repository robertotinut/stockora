<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionFeature extends Model
{
    public const TYPE_BOOLEAN = 'BOOLEAN';
    public const TYPE_LIMIT = 'LIMIT';
    public const TYPE_TEXT = 'TEXT';

    public const TYPES = [
        self::TYPE_BOOLEAN => 'Boolean (Enabled / Disabled)',
        self::TYPE_LIMIT => 'Numeric Limit (Null = Unlimited)',
        self::TYPE_TEXT => 'Text Descriptor',
    ];

    public const CATEGORIES = [
        'Inventory Management',
        'Purchasing',
        'Reporting',
        'Team & Access Management',
        'Resource Limits',
        'Advanced Capabilities',
    ];

    protected $fillable = [
        'feature_key',
        'name',
        'description',
        'category',
        'feature_type',
        'default_value',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'subscription_plan_features')
            ->withPivot(['id', 'value'])
            ->withTimestamps();
    }

    public function planFeatures(): HasMany
    {
        return $this->hasMany(SubscriptionPlanFeature::class, 'subscription_feature_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PlatformAuditLog::class, 'subscription_feature_id');
    }

    public function isLimit(): bool
    {
        return $this->feature_type === self::TYPE_LIMIT;
    }

    public function isBoolean(): bool
    {
        return $this->feature_type === self::TYPE_BOOLEAN;
    }

    public function isText(): bool
    {
        return $this->feature_type === self::TYPE_TEXT;
    }

    public function formatDisplayValue(?string $rawValue): string
    {
        if ($this->feature_type === self::TYPE_BOOLEAN) {
            return in_array(strtolower((string) $rawValue), ['1', 'true', 'yes', 'on'], true)
                ? __('subscription.included')
                : __('subscription.not_included');
        }

        if ($this->feature_type === self::TYPE_LIMIT) {
            if ($rawValue === null || trim($rawValue) === '') {
                return __('subscription.unlimited');
            }

            return number_format((int) $rawValue, 0, ',', '.');
        }

        return ($rawValue !== null && trim($rawValue) !== '') ? trim($rawValue) : '—';
    }
}
