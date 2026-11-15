<?php

namespace App\Services;

use App\Models\PlatformAuditLog;

class PlatformAuditService
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'token_hash',
        'remember_token',
        'setup_url',
    ];

    public function record(
        string $action,
        ?int $companyId = null,
        ?int $platformAdminId = null,
        ?int $actorUserId = null,
        ?array $previousValues = null,
        ?array $newValues = null,
        ?string $note = null,
        ?string $ipAddress = null,
        ?int $subscriptionPlanId = null,
        ?int $subscriptionFeatureId = null,
        ?int $subscriptionPaymentId = null
    ): PlatformAuditLog {
        return PlatformAuditLog::create([
            'company_id' => $companyId,
            'subscription_plan_id' => $subscriptionPlanId,
            'subscription_feature_id' => $subscriptionFeatureId,
            'subscription_payment_id' => $subscriptionPaymentId,
            'platform_admin_id' => $platformAdminId,
            'actor_user_id' => $actorUserId,
            'action' => strtoupper(trim($action)),
            'previous_values' => $previousValues !== null ? $this->sanitizePayload($previousValues) : null,
            'new_values' => $newValues !== null ? $this->sanitizePayload($newValues) : null,
            'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }

    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::REDACTED_KEYS, true)) {
                continue;
            }
            $sanitized[$key] = is_array($value) ? $this->sanitizePayload($value) : $value;
        }

        return $sanitized;
    }
}
