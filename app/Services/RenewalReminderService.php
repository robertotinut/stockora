<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class RenewalReminderService
{
    public const STAGE_NONE = 'NONE';
    public const STAGE_HEALTHY = 'HEALTHY';
    public const STAGE_REMINDER_30 = 'REMINDER_30';
    public const STAGE_REMINDER_7 = 'REMINDER_7';
    public const STAGE_REMINDER_3 = 'REMINDER_3';
    public const STAGE_EXPIRED = 'EXPIRED';
    public const STAGE_PENDING_ACTIVATION = 'PENDING_ACTIVATION';
    public const STAGE_SUSPENDED = 'SUSPENDED';
    public const STAGE_CANCELLED = 'CANCELLED';

    public function __construct(private PaymentInstructionService $settingsService) {}

    public function evaluateCompany(Company $company, ?Carbon $referenceTime = null): array
    {
        $now = ($referenceTime ?? now())->copy();
        $effectiveStatus = $company->effectiveStatus();
        $expiresAt = $company->access_expires_at?->copy();

        if ($effectiveStatus === Company::STATUS_PENDING_ACTIVATION) {
            return $this->payload(
                stage: self::STAGE_PENDING_ACTIVATION,
                severity: 'warning',
                daysRemaining: null,
                requiresAttention: true,
                isExpiringSoon: false,
                isExpired: false,
                canSubmitPayment: true,
                expiresAt: $expiresAt
            );
        }

        if ($effectiveStatus === Company::STATUS_SUSPENDED) {
            return $this->payload(
                stage: self::STAGE_SUSPENDED,
                severity: 'danger',
                daysRemaining: $expiresAt ? (int) $now->copy()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : null,
                requiresAttention: true,
                isExpiringSoon: false,
                isExpired: false,
                canSubmitPayment: false,
                expiresAt: $expiresAt
            );
        }

        if ($effectiveStatus === Company::STATUS_CANCELLED) {
            return $this->payload(
                stage: self::STAGE_CANCELLED,
                severity: 'secondary',
                daysRemaining: null,
                requiresAttention: true,
                isExpiringSoon: false,
                isExpired: false,
                canSubmitPayment: false,
                expiresAt: $expiresAt
            );
        }

        if ($effectiveStatus === Company::STATUS_EXPIRED || ($expiresAt !== null && $expiresAt->lte($now))) {
            $daysPast = $expiresAt ? (int) $now->copy()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : 0;

            return $this->payload(
                stage: self::STAGE_EXPIRED,
                severity: 'danger',
                daysRemaining: min(0, $daysPast),
                requiresAttention: true,
                isExpiringSoon: false,
                isExpired: true,
                canSubmitPayment: true,
                expiresAt: $expiresAt
            );
        }

        if ($expiresAt === null) {
            return $this->payload(
                stage: self::STAGE_NONE,
                severity: 'success',
                daysRemaining: null,
                requiresAttention: false,
                isExpiringSoon: false,
                isExpired: false,
                canSubmitPayment: true,
                expiresAt: null
            );
        }

        $secondsDiff = max(0, $now->diffInSeconds($expiresAt, false));
        $daysRemaining = (int) ceil($secondsDiff / 86400);

        $windows = $this->settingsService->getBillingSettings()['renewal_reminder_windows'] ?? [30, 7, 3];
        $outerWindow = max($windows ?: [30]);
        $midWindow = collect($windows)->filter(fn ($d) => $d <= 14 && $d > 3)->max() ?? 7;
        $urgentWindow = collect($windows)->filter(fn ($d) => $d <= 3)->max() ?? 3;

        if ($daysRemaining <= $urgentWindow) {
            return $this->payload(
                stage: self::STAGE_REMINDER_3,
                severity: 'danger',
                daysRemaining: $daysRemaining,
                requiresAttention: true,
                isExpiringSoon: true,
                isExpired: false,
                canSubmitPayment: true,
                expiresAt: $expiresAt
            );
        }

        if ($daysRemaining <= $midWindow) {
            return $this->payload(
                stage: self::STAGE_REMINDER_7,
                severity: 'warning',
                daysRemaining: $daysRemaining,
                requiresAttention: true,
                isExpiringSoon: true,
                isExpired: false,
                canSubmitPayment: true,
                expiresAt: $expiresAt
            );
        }

        if ($daysRemaining <= $outerWindow) {
            return $this->payload(
                stage: self::STAGE_REMINDER_30,
                severity: 'info',
                daysRemaining: $daysRemaining,
                requiresAttention: true,
                isExpiringSoon: true,
                isExpired: false,
                canSubmitPayment: true,
                expiresAt: $expiresAt
            );
        }

        return $this->payload(
            stage: self::STAGE_HEALTHY,
            severity: 'success',
            daysRemaining: $daysRemaining,
            requiresAttention: false,
            isExpiringSoon: false,
            isExpired: false,
            canSubmitPayment: true,
            expiresAt: $expiresAt
        );
    }

    /**
     * Logs a non-blocking renewal reminder event when MAIL_MAILER is sandbox/log or triggers notification cleanly.
     */
    public function logSandboxReminder(Company $company, array $evaluation): void
    {
        if (!($evaluation['requires_attention'] ?? false)) {
            return;
        }

        Log::info('Stockora billing reminder evaluated (sandbox log mode, no external email sent).', [
            'company_id' => $company->id,
            'company_code' => $company->code,
            'stage' => $evaluation['stage'],
            'days_remaining' => $evaluation['days_remaining'],
            'expires_at' => $evaluation['expires_at']?->toIso8601String(),
            'mail_mailer' => config('mail.default'),
        ]);
    }

    private function payload(
        string $stage,
        string $severity,
        ?int $daysRemaining,
        bool $requiresAttention,
        bool $isExpiringSoon,
        bool $isExpired,
        bool $canSubmitPayment,
        ?\Carbon\CarbonInterface $expiresAt
    ): array {
        return [
            'stage' => $stage,
            'severity' => $severity,
            'days_remaining' => $daysRemaining,
            'requires_attention' => $requiresAttention,
            'is_expiring_soon' => $isExpiringSoon,
            'is_expired' => $isExpired,
            'can_submit_payment' => $canSubmitPayment,
            'expires_at' => $expiresAt,
        ];
    }
}
