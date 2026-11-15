<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    public const STATUS_PENDING_ACTIVATION = 'PENDING_ACTIVATION';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const ACCOUNT_STATUSES = [
        self::STATUS_PENDING_ACTIVATION => [
            'label' => 'Pending Activation',
            'badge' => 'warning',
            'description' => 'Self-registered workspace awaiting manual verification and activation by Stockora Platform Admin.',
        ],
        self::STATUS_ACTIVE => [
            'label' => 'Active',
            'badge' => 'success',
            'description' => 'Customer workspace is activated and permitted to operate Stockora modules.',
        ],
        self::STATUS_SUSPENDED => [
            'label' => 'Suspended',
            'badge' => 'danger',
            'description' => 'Workspace operational access is paused by Stockora Platform Admin while data remains preserved.',
        ],
        self::STATUS_EXPIRED => [
            'label' => 'Expired',
            'badge' => 'secondary',
            'description' => 'Workspace access window has elapsed and requires manual renewal/extension by Platform Admin.',
        ],
        self::STATUS_CANCELLED => [
            'label' => 'Cancelled',
            'badge' => 'dark',
            'description' => 'Workspace subscription or onboarding request has been closed.',
        ],
    ];

    public const ACCESS_FREE = 'FREE';
    public const ACCESS_STARTER = 'STARTER';
    public const ACCESS_PROFESSIONAL = 'PROFESSIONAL';
    public const ACCESS_ENTERPRISE = 'ENTERPRISE';

    public const ACCESS_TYPES = [
        self::ACCESS_FREE => ['label' => 'Free', 'badge' => 'light'],
        self::ACCESS_STARTER => ['label' => 'Starter', 'badge' => 'info'],
        self::ACCESS_PROFESSIONAL => ['label' => 'Professional', 'badge' => 'primary'],
        self::ACCESS_ENTERPRISE => ['label' => 'Enterprise', 'badge' => 'dark'],
    ];

    public const INDUSTRIES = [
        'Distributor / Supplier',
        'Wholesale / Trading',
        'Manufacturing',
        'Printing',
        'Retail',
        'Building Materials',
        'Other',
    ];

    protected $fillable = [
        'code',
        'name',
        'industry',
        'phone',
        'address',
        'logo_path',
        'is_active',
        'account_status',
        'access_type',
        'subscription_plan_id',
        'access_starts_at',
        'access_expires_at',
        'activated_at',
        'activated_by_platform_admin_id',
        'suspended_at',
        'suspended_by_platform_admin_id',
        'status_reason',
        'admin_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'access_starts_at' => 'datetime',
            'access_expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function logoUrl(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function initials(): string
    {
        $words = preg_split('/[\s-]+/', trim((string) $this->name)) ?: [];
        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) {
            if ($word !== '') {
                $letters .= strtoupper(substr($word, 0, 1));
            }
        }

        return $letters !== '' ? $letters : strtoupper(substr((string) $this->code, 0, 2));
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot(['role', 'is_default', 'is_active'])
            ->withTimestamps();
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function activatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'activated_by_platform_admin_id');
    }

    public function suspendedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'suspended_by_platform_admin_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function platformAuditLogs(): HasMany
    {
        return $this->hasMany(PlatformAuditLog::class, 'company_id');
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'company_id');
    }

    public function subscriptionRenewalRequests(): HasMany
    {
        return $this->hasMany(SubscriptionRenewalRequest::class, 'company_id');
    }

    public function effectiveStatus(): string
    {
        $status = strtoupper((string) ($this->account_status ?: ($this->is_active ? self::STATUS_ACTIVE : self::STATUS_SUSPENDED)));

        if ($status === self::STATUS_ACTIVE && $this->access_expires_at !== null && $this->access_expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        return $status;
    }

    public function isOperationallyActive(): bool
    {
        return $this->effectiveStatus() === self::STATUS_ACTIVE && (bool) $this->is_active;
    }

    public function accessTypeLabel(): string
    {
        $type = strtoupper((string) ($this->access_type ?: self::ACCESS_FREE));

        return self::ACCESS_TYPES[$type]['label'] ?? $type;
    }
}
