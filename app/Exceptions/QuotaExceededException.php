<?php

namespace App\Exceptions;

use DomainException;

class QuotaExceededException extends DomainException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly int $requestedDelta = 1,
        public readonly string $planName = 'Free',
        ?string $message = null
    ) {
        parent::__construct($message ?? self::buildLocalizedMessage($limitKey, $currentUsage, $limit, $requestedDelta, $planName));
    }

    public static function buildLocalizedMessage(
        string $limitKey,
        int $currentUsage,
        int $limit,
        int $requestedDelta,
        string $planName
    ): string {
        $resource = __("subscription.limits.{$limitKey}");
        if ($resource === "subscription.limits.{$limitKey}") {
            $resource = $limitKey;
        }

        $supportEmail = config('stockora.support.email', 'onboarding@stockora.app');
        $supportWhatsapp = config('stockora.support.whatsapp', '+62 811 9000 8800');

        if ($requestedDelta > 1 && $limit > 0) {
            return __('subscription.quota_exceeded_batch', [
                'resource' => $resource,
                'usage' => $currentUsage,
                'limit' => $limit,
                'requested' => $requestedDelta,
                'remaining' => max(0, $limit - $currentUsage),
                'plan' => $planName,
                'email' => $supportEmail,
                'whatsapp' => $supportWhatsapp,
            ]);
        }

        return __('subscription.quota_exceeded_single', [
            'resource' => $resource,
            'usage' => $currentUsage,
            'limit' => $limit,
            'plan' => $planName,
            'email' => $supportEmail,
            'whatsapp' => $supportWhatsapp,
        ]);
    }

    public function toArray(): array
    {
        return [
            'code' => 'QUOTA_EXCEEDED',
            'message' => $this->getMessage(),
            'error' => $this->getMessage(),
            'limit_key' => $this->limitKey,
            'usage' => $this->currentUsage,
            'limit' => $this->limit,
            'requested_delta' => $this->requestedDelta,
            'plan_name' => $this->planName,
        ];
    }
}
