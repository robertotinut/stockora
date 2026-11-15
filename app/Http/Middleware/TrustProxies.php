<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;

class TrustProxies extends BaseTrustProxies
{
    /**
     * Standard reverse-proxy headers evaluated ONLY for configured trusted proxies.
     *
     * @var int
     */
    protected $headers = Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_PREFIX |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    /**
     * Resolve configured trusted proxy IPs/CIDRs cleanly without blindly trusting arbitrary clients.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        $configured = config('stockora.trusted_proxies');
        if ($configured === null) {
            return [];
        }

        if (is_array($configured)) {
            return array_values(array_filter(array_map(
                static fn ($value): string => trim((string) $value),
                $configured
            ), static fn (string $value): bool => $value !== ''));
        }

        $trimmed = trim((string) $configured);
        if ($trimmed === '') {
            return [];
        }

        if ($trimmed === '*' || $trimmed === '**') {
            return $trimmed;
        }

        return array_values(array_filter(array_map(
            'trim',
            explode(',', $trimmed)
        ), static fn (string $value): bool => $value !== ''));
    }
}
