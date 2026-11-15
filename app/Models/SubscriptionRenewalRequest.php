<?php

namespace App\Models;

use App\Support\StockoraFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

class SubscriptionRenewalRequest extends Model
{
    public const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    public const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_APPLIED = 'APPLIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT => 'Awaiting Payment',
        self::STATUS_PENDING_VERIFICATION => 'Pending Verification',
        self::STATUS_APPROVED => 'Payment Verified (Ready to Apply)',
        self::STATUS_APPLIED => 'Applied to Subscription',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'request_number',
        'company_id',
        'current_plan_id',
        'requested_plan_id',
        'subscription_payment_id',
        'requested_by_user_id',
        'request_type',
        'billing_interval',
        'duration_days',
        'quoted_amount',
        'status',
        'current_status_snapshot',
        'current_expires_at_snapshot',
        'approved_starts_at',
        'approved_ends_at',
        'customer_note',
        'rejection_reason',
        'admin_decision_note',
        'processed_by_platform_admin_id',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'quoted_amount' => 'decimal:2',
            'current_expires_at_snapshot' => 'datetime',
            'approved_starts_at' => 'datetime',
            'approved_ends_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'current_plan_id');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'requested_plan_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_payment_id');
    }

    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function processedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'processed_by_platform_admin_id');
    }

    public function formattedQuotedAmount(): string
    {
        return StockoraFormat::currency($this->quoted_amount ?? 0, 0);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_PAYMENT, self::STATUS_PENDING_VERIFICATION => 'badge-warning',
            self::STATUS_APPROVED => 'badge-info',
            self::STATUS_APPLIED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            self::STATUS_CANCELLED => 'badge-secondary',
            default => 'badge-light',
        };
    }

    public function statusLabel(): string
    {
        $key = "payments.renewal_statuses.{$this->status}";

        return Lang::has($key)
            ? __($key)
            : (self::STATUSES[$this->status] ?? (string) $this->status);
    }
}
