<?php

namespace App\Services;

use App\Models\PaymentInstruction;
use App\Models\PlatformAdmin;
use App\Models\PlatformSetting;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentInstructionService
{
    public const DEFAULT_REMINDER_WINDOWS = [30, 7, 3];
    public const DEFAULT_MAX_PROOF_KB = 5120;

    public function __construct(private PlatformAuditService $audit) {}

    public function getActiveInstructions(): Collection
    {
        return PaymentInstruction::where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function getAllInstructions(): Collection
    {
        return PaymentInstruction::orderByDesc('is_active')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function createInstruction(PlatformAdmin $admin, array $attributes, ?string $ipAddress = null): PaymentInstruction
    {
        $code = strtoupper(trim((string) ($attributes['method_code'] ?? '')));
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            throw new DomainException('Method code must contain 3–50 uppercase letters, digits, underscores, or hyphens.');
        }

        if (PaymentInstruction::where('method_code', $code)->exists()) {
            throw new DomainException("Payment method code {$code} already exists.");
        }

        return DB::transaction(function () use ($admin, $attributes, $code, $ipAddress): PaymentInstruction {
            $isDefault = (bool) ($attributes['is_default'] ?? false);
            $isActive = array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true;

            if ($isDefault && $isActive) {
                PaymentInstruction::query()->where('is_default', true)->update(['is_default' => false]);
            } elseif ($isDefault && !$isActive) {
                $isDefault = false;
            }

            $instruction = PaymentInstruction::create([
                'method_code' => $code,
                'method_name' => trim((string) $attributes['method_name']),
                'channel_type' => strtoupper(trim((string) ($attributes['channel_type'] ?? PaymentInstruction::CHANNEL_BANK_TRANSFER))),
                'bank_name' => $this->cleanNullable($attributes['bank_name'] ?? null),
                'account_name' => $this->cleanNullable($attributes['account_name'] ?? null),
                'account_number' => $this->cleanNullable($attributes['account_number'] ?? null),
                'instructions' => $this->cleanNullable($attributes['instructions'] ?? null),
                'instructions_id' => $this->cleanNullable($attributes['instructions_id'] ?? null),
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'sort_order' => max(0, (int) ($attributes['sort_order'] ?? 0)),
                'created_by_platform_admin_id' => (int) $admin->id,
                'updated_by_platform_admin_id' => (int) $admin->id,
            ]);

            $this->audit->record(
                action: 'PAYMENT_INSTRUCTION_CREATED',
                platformAdminId: (int) $admin->id,
                newValues: $instruction->only([
                    'id', 'method_code', 'method_name', 'channel_type', 'bank_name', 'account_name', 'is_active', 'is_default', 'sort_order',
                ]),
                note: "Created manual payment instruction {$instruction->method_code} ({$instruction->method_name}).",
                ipAddress: $ipAddress
            );

            return $instruction;
        });
    }

    public function updateInstruction(
        PaymentInstruction $instruction,
        PlatformAdmin $admin,
        array $attributes,
        ?string $ipAddress = null
    ): PaymentInstruction {
        return DB::transaction(function () use ($instruction, $admin, $attributes, $ipAddress): PaymentInstruction {
            $locked = PaymentInstruction::where('id', $instruction->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->only([
                'method_code', 'method_name', 'channel_type', 'bank_name', 'account_name', 'account_number', 'is_active', 'is_default', 'sort_order',
            ]);

            $isActive = array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : (bool) $locked->is_active;
            $isDefault = array_key_exists('is_default', $attributes) ? (bool) $attributes['is_default'] : (bool) $locked->is_default;

            if ($isDefault && $isActive) {
                PaymentInstruction::query()
                    ->where('id', '!=', $locked->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            } elseif (!$isActive) {
                $isDefault = false;
            }

            $locked->update([
                'method_name' => trim((string) ($attributes['method_name'] ?? $locked->method_name)),
                'channel_type' => strtoupper(trim((string) ($attributes['channel_type'] ?? $locked->channel_type))),
                'bank_name' => array_key_exists('bank_name', $attributes) ? $this->cleanNullable($attributes['bank_name']) : $locked->bank_name,
                'account_name' => array_key_exists('account_name', $attributes) ? $this->cleanNullable($attributes['account_name']) : $locked->account_name,
                'account_number' => array_key_exists('account_number', $attributes) ? $this->cleanNullable($attributes['account_number']) : $locked->account_number,
                'instructions' => array_key_exists('instructions', $attributes) ? $this->cleanNullable($attributes['instructions']) : $locked->instructions,
                'instructions_id' => array_key_exists('instructions_id', $attributes) ? $this->cleanNullable($attributes['instructions_id']) : $locked->instructions_id,
                'is_active' => $isActive,
                'is_default' => $isDefault,
                'sort_order' => array_key_exists('sort_order', $attributes) ? max(0, (int) $attributes['sort_order']) : (int) $locked->sort_order,
                'updated_by_platform_admin_id' => (int) $admin->id,
            ]);

            $this->audit->record(
                action: 'PAYMENT_INSTRUCTION_UPDATED',
                platformAdminId: (int) $admin->id,
                previousValues: $previous,
                newValues: $locked->only([
                    'method_code', 'method_name', 'channel_type', 'bank_name', 'account_name', 'account_number', 'is_active', 'is_default', 'sort_order',
                ]),
                note: "Updated manual payment instruction {$locked->method_code}.",
                ipAddress: $ipAddress
            );

            return $locked->fresh();
        });
    }

    public function toggleInstruction(PaymentInstruction $instruction, PlatformAdmin $admin, ?string $ipAddress = null): PaymentInstruction
    {
        return DB::transaction(function () use ($instruction, $admin, $ipAddress): PaymentInstruction {
            $locked = PaymentInstruction::where('id', $instruction->id)->lockForUpdate()->firstOrFail();
            $nextActive = !(bool) $locked->is_active;

            $locked->update([
                'is_active' => $nextActive,
                'is_default' => $nextActive ? $locked->is_default : false,
                'updated_by_platform_admin_id' => (int) $admin->id,
            ]);

            $this->audit->record(
                action: 'PAYMENT_INSTRUCTION_TOGGLED',
                platformAdminId: (int) $admin->id,
                previousValues: ['is_active' => !$nextActive],
                newValues: ['is_active' => $nextActive, 'method_code' => $locked->method_code],
                note: ($nextActive ? 'Activated' : 'Deactivated') . " payment instruction {$locked->method_code}.",
                ipAddress: $ipAddress
            );

            return $locked->fresh();
        });
    }

    public function getBillingSettings(): array
    {
        $rows = PlatformSetting::query()->pluck('setting_value', 'setting_key');

        $rawWindows = json_decode((string) ($rows['renewal_reminder_windows'] ?? ''), true);
        $windows = is_array($rawWindows)
            ? array_values(array_unique(array_filter(array_map('intval', $rawWindows), fn ($d) => $d > 0 && $d <= 365)))
            : self::DEFAULT_REMINDER_WINDOWS;
        rsort($windows);
        if (empty($windows)) {
            $windows = self::DEFAULT_REMINDER_WINDOWS;
        }

        $maxKb = (int) ($rows['payment_proof_max_kb'] ?? self::DEFAULT_MAX_PROOF_KB);
        $maxKb = max(512, min(self::DEFAULT_MAX_PROOF_KB, $maxKb));

        return [
            'renewal_reminder_windows' => $windows,
            'allow_customer_payment_submissions' => ($rows['allow_customer_payment_submissions'] ?? '1') === '1',
            'payment_proof_max_kb' => $maxKb,
            'verification_sla_note' => (string) ($rows['verification_sla_note'] ?? 'Manual payment verification takes up to 1 business day during Stockora operating hours.'),
            'verification_sla_note_id' => (string) ($rows['verification_sla_note_id'] ?? 'Verifikasi pembayaran manual memerlukan waktu maksimal 1 hari kerja sesuai jam operasional Stockora.'),
        ];
    }

    public function updateBillingSettings(PlatformAdmin $admin, array $attributes, ?string $ipAddress = null): array
    {
        $windowsRaw = $attributes['renewal_reminder_windows'] ?? self::DEFAULT_REMINDER_WINDOWS;
        if (is_string($windowsRaw)) {
            $windowsRaw = preg_split('/[\s,;]+/', $windowsRaw) ?: [];
        }
        $windows = array_values(array_unique(array_filter(array_map('intval', (array) $windowsRaw), fn ($d) => $d > 0 && $d <= 180)));
        rsort($windows);
        if (empty($windows)) {
            throw new DomainException('At least one positive reminder window day (1–180) is required.');
        }

        $maxKb = max(512, min(self::DEFAULT_MAX_PROOF_KB, (int) ($attributes['payment_proof_max_kb'] ?? self::DEFAULT_MAX_PROOF_KB)));
        $allowSubmissions = !empty($attributes['allow_customer_payment_submissions']) ? '1' : '0';
        $slaEn = trim((string) ($attributes['verification_sla_note'] ?? ''));
        $slaId = trim((string) ($attributes['verification_sla_note_id'] ?? ''));

        $previous = $this->getBillingSettings();

        DB::transaction(function () use ($admin, $windows, $maxKb, $allowSubmissions, $slaEn, $slaId, $previous, $ipAddress): void {
            $pairs = [
                'renewal_reminder_windows' => json_encode($windows),
                'allow_customer_payment_submissions' => $allowSubmissions,
                'payment_proof_max_kb' => (string) $maxKb,
                'verification_sla_note' => $slaEn !== '' ? $slaEn : $previous['verification_sla_note'],
                'verification_sla_note_id' => $slaId !== '' ? $slaId : $previous['verification_sla_note_id'],
            ];

            foreach ($pairs as $key => $val) {
                PlatformSetting::updateOrCreate(
                    ['setting_key' => $key],
                    [
                        'setting_value' => $val,
                        'updated_by_platform_admin_id' => (int) $admin->id,
                    ]
                );
            }

            $next = $this->getBillingSettings();

            $this->audit->record(
                action: 'PLATFORM_BILLING_SETTINGS_UPDATED',
                platformAdminId: (int) $admin->id,
                previousValues: $previous,
                newValues: $next,
                note: 'Updated platform manual payment and renewal reminder settings.',
                ipAddress: $ipAddress
            );
        });

        return $this->getBillingSettings();
    }

    private function cleanNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = trim((string) $value);

        return $clean !== '' ? $clean : null;
    }
}
