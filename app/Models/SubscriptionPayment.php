<?php

namespace App\Models;

use App\Support\StockoraFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

class SubscriptionPayment extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_PENDING => ['label' => 'Pending Verification', 'badge' => 'warning'],
        self::STATUS_VERIFIED => ['label' => 'Verified', 'badge' => 'success'],
        self::STATUS_REJECTED => ['label' => 'Rejected', 'badge' => 'danger'],
        self::STATUS_CANCELLED => ['label' => 'Cancelled', 'badge' => 'secondary'],
    ];

    public const SOURCE_CUSTOMER = 'CUSTOMER_SUBMISSION';
    public const SOURCE_ADMIN_MANUAL = 'ADMIN_MANUAL';

    public const SOURCES = [
        self::SOURCE_CUSTOMER => 'Customer Submission',
        self::SOURCE_ADMIN_MANUAL => 'Admin Manual Offline',
    ];

    public const TYPE_ACTIVATION = 'ACTIVATION';
    public const TYPE_RENEWAL = 'RENEWAL';
    public const TYPE_PLAN_CHANGE = 'PLAN_CHANGE';
    public const TYPE_MANUAL_OFFLINE = 'MANUAL_OFFLINE';

    public const REQUEST_TYPES = [
        self::TYPE_ACTIVATION => 'Initial Activation',
        self::TYPE_RENEWAL => 'Subscription Renewal',
        self::TYPE_PLAN_CHANGE => 'Plan Upgrade / Change',
        self::TYPE_MANUAL_OFFLINE => 'Manual Offline Payment',
    ];

    public const EXPIRY_ACTION_AUTO = 'AUTO';
    public const EXPIRY_ACTION_KEEP = 'KEEP_EXISTING';
    public const EXPIRY_ACTION_EXTEND = 'EXTEND_CURRENT';
    public const EXPIRY_ACTION_FROM_START = 'SET_FROM_START';
    public const EXPIRY_ACTION_CUSTOM = 'CUSTOM_DATE';

    protected $fillable = [
        'payment_reference',
        'company_id',
        'subscription_plan_id',
        'payment_instruction_id',
        'requested_by_user_id',
        'recorded_by_platform_admin_id',
        'source',
        'request_type',
        'status',
        'plan_code_snapshot',
        'plan_name_snapshot',
        'plan_tier_snapshot',
        'billing_interval',
        'duration_days',
        'base_plan_price',
        'amount',
        'currency',
        'payment_method_code',
        'payment_method_name',
        'customer_transfer_reference',
        'payer_name',
        'payment_date',
        'payment_received_confirmed',
        'customer_note',
        'admin_note',
        'payment_proof_disk',
        'payment_proof_path',
        'payment_proof_original_name',
        'payment_proof_mime',
        'payment_proof_size',
        'payment_proof_hash',
        'submitted_at',
        'verified_at',
        'verified_by_platform_admin_id',
        'verification_note',
        'rejected_at',
        'rejected_by_platform_admin_id',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancelled_by_platform_admin_id',
        'cancellation_reason',
        'replaced_by_payment_id',
        'applied_to_subscription_at',
        'applied_by_platform_admin_id',
        'applied_action',
        'period_starts_at',
        'period_ends_at',
        'previous_plan_id',
        'previous_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'base_plan_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'payment_received_confirmed' => 'boolean',
            'payment_proof_size' => 'integer',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'applied_to_subscription_at' => 'datetime',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'previous_expires_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'previous_plan_id');
    }

    public function paymentInstruction(): BelongsTo
    {
        return $this->belongsTo(PaymentInstruction::class, 'payment_instruction_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'recorded_by_platform_admin_id');
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'verified_by_platform_admin_id');
    }

    public function rejectedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'rejected_by_platform_admin_id');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function cancelledByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'cancelled_by_platform_admin_id');
    }

    public function appliedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'applied_by_platform_admin_id');
    }

    public function replacedByPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_payment_id');
    }

    public function renewalRequest(): HasOne
    {
        return $this->hasOne(SubscriptionRenewalRequest::class, 'subscription_payment_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isApplied(): bool
    {
        return $this->applied_to_subscription_at !== null;
    }

    public function canCustomerCancel(): bool
    {
        return $this->isPending() && $this->source === self::SOURCE_CUSTOMER;
    }

    public function hasProof(): bool
    {
        if (!$this->payment_proof_path) {
            return false;
        }

        $disk = $this->payment_proof_disk ?: 'local';

        return Storage::disk($disk)->exists($this->payment_proof_path);
    }

    public function isImageProof(): bool
    {
        return str_starts_with((string) $this->payment_proof_mime, 'image/');
    }

    public function formattedAmount(): string
    {
        $raw = (string) ($this->amount ?? '0');
        $decimals = str_contains($raw, '.') && rtrim(explode('.', $raw)[1] ?? '', '0') !== '' ? 2 : 0;

        return StockoraFormat::currency($raw, $decimals);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'badge-warning',
            self::STATUS_VERIFIED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_CANCELLED => 'badge-secondary',
            default => 'badge-light',
        };
    }

    public function statusLabel(): string
    {
        $key = "payments.statuses.{$this->status}";

        return Lang::has($key)
            ? __($key)
            : (self::STATUSES[$this->status]['label'] ?? (string) $this->status);
    }

    public function requestTypeLabel(): string
    {
        $key = "payments.request_types.{$this->request_type}";

        return Lang::has($key)
            ? __($key)
            : (self::REQUEST_TYPES[$this->request_type] ?? (string) $this->request_type);
    }

    public function sourceLabel(): string
    {
        $key = "payments.sources.{$this->source}";

        return Lang::has($key)
            ? __($key)
            : (self::SOURCES[$this->source] ?? (string) $this->source);
    }

    public function billingIntervalLabel(): string
    {
        $key = "subscription.billing_intervals.{$this->billing_interval}";

        return Lang::has($key)
            ? __($key)
            : (string) $this->billing_interval;
    }
}
