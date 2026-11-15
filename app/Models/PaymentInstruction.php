<?php

namespace App\Models;

use App\Support\StockoraLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;

class PaymentInstruction extends Model
{
    public const CHANNEL_BANK_TRANSFER = 'BANK_TRANSFER';
    public const CHANNEL_VIRTUAL_ACCOUNT = 'VIRTUAL_ACCOUNT_MANUAL';
    public const CHANNEL_QRIS_MANUAL = 'QRIS_MANUAL';
    public const CHANNEL_OTHER = 'OTHER';

    public const CHANNEL_TYPES = [
        self::CHANNEL_BANK_TRANSFER => 'Bank Transfer',
        self::CHANNEL_VIRTUAL_ACCOUNT => 'Virtual Account (Manual)',
        self::CHANNEL_QRIS_MANUAL => 'QRIS Manual',
        self::CHANNEL_OTHER => 'Other Manual Channel',
    ];

    protected $fillable = [
        'method_code',
        'method_name',
        'channel_type',
        'bank_name',
        'account_name',
        'account_number',
        'instructions',
        'instructions_id',
        'is_active',
        'is_default',
        'sort_order',
        'created_by_platform_admin_id',
        'updated_by_platform_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class, 'payment_instruction_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'created_by_platform_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'updated_by_platform_admin_id');
    }

    public function localizedInstructions(?string $locale = null): ?string
    {
        $loc = $locale ?: StockoraLocale::current();
        if ($loc === 'id' && $this->instructions_id !== null && trim($this->instructions_id) !== '') {
            return $this->instructions_id;
        }

        return $this->instructions ?: $this->instructions_id;
    }

    public function channelLabel(): string
    {
        $key = "payments.channels.{$this->channel_type}";

        return Lang::has($key)
            ? __($key)
            : (self::CHANNEL_TYPES[$this->channel_type] ?? (string) $this->channel_type);
    }
}
