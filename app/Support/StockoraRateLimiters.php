<?php

namespace App\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class StockoraRateLimiters
{
    /**
     * Register named rate limiters protecting authentication, onboarding,
     * password recovery, invitations, and manual billing endpoints.
     */
    public static function register(): void
    {
        $onLimit = static function (Request $request, array $headers) {
            StockoraLocale::apply(StockoraLocale::resolveForRequest($request));

            $retryAfter = max(1, (int) ($headers['Retry-After'] ?? 60));
            $message = __('auth.throttle_too_many_requests', ['seconds' => $retryAfter]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'retry_after' => $retryAfter,
                ], 429, $headers);
            }

            return response()->view('errors.429', [
                'message' => $message,
                'retryAfter' => $retryAfter,
                'isPlatform' => StockoraLocale::isPlatformRequest($request),
            ], 429, $headers);
        };

        RateLimiter::for('tenant-login', static function (Request $request) use ($onLimit): array {
            $ip = (string) $request->ip();
            $email = self::normalizedEmail($request);

            return [
                Limit::perMinute(5)
                    ->by('tenant-login-acct:' . ($email !== '' ? $email : 'empty') . '|' . $ip)
                    ->response($onLimit),
                Limit::perMinute(25)
                    ->by('tenant-login-ip:' . $ip)
                    ->response($onLimit),
            ];
        });

        RateLimiter::for('platform-login', static function (Request $request) use ($onLimit): array {
            $ip = (string) $request->ip();
            $email = self::normalizedEmail($request);

            return [
                Limit::perMinute(5)
                    ->by('platform-login-acct:' . ($email !== '' ? $email : 'empty') . '|' . $ip)
                    ->response($onLimit),
                Limit::perMinute(15)
                    ->by('platform-login-ip:' . $ip)
                    ->response($onLimit),
            ];
        });

        RateLimiter::for('register', static function (Request $request) use ($onLimit): Limit {
            return Limit::perMinute(5)
                ->by('register:' . (string) $request->ip())
                ->response($onLimit);
        });

        RateLimiter::for('password-setup', static function (Request $request) use ($onLimit): array {
            $ip = (string) $request->ip();
            $email = self::normalizedEmail($request);

            return [
                Limit::perMinute(5)
                    ->by('pwsetup:' . ($email !== '' ? $email : 'empty') . '|' . $ip)
                    ->response($onLimit),
                Limit::perMinute(15)
                    ->by('pwsetup-ip:' . $ip)
                    ->response($onLimit),
            ];
        });

        RateLimiter::for('password-setup-view', static function (Request $request) use ($onLimit): Limit {
            return Limit::perMinute(20)
                ->by('pwsetup-view:' . (string) $request->ip())
                ->response($onLimit);
        });

        RateLimiter::for('password-reset-request', static function (Request $request) use ($onLimit): array {
            $ip = (string) $request->ip();
            $email = self::normalizedEmail($request);

            return [
                Limit::perMinute(3)
                    ->by('pwreq-acct:' . ($email !== '' ? $email : 'empty') . '|' . $ip)
                    ->response($onLimit),
                Limit::perMinute(10)
                    ->by('pwreq-ip:' . $ip)
                    ->response($onLimit),
            ];
        });

        RateLimiter::for('password-reset-submit', static function (Request $request) use ($onLimit): array {
            $ip = (string) $request->ip();
            $email = self::normalizedEmail($request);

            return [
                Limit::perMinute(5)
                    ->by('pwreset-acct:' . ($email !== '' ? $email : 'empty') . '|' . $ip)
                    ->response($onLimit),
                Limit::perMinute(15)
                    ->by('pwreset-ip:' . $ip)
                    ->response($onLimit),
            ];
        });

        RateLimiter::for('invitation-view', static function (Request $request) use ($onLimit): Limit {
            return Limit::perMinute(20)
                ->by('invite-view:' . (string) $request->ip())
                ->response($onLimit);
        });

        RateLimiter::for('invitation-accept', static function (Request $request) use ($onLimit): Limit {
            $actor = $request->user()?->id ? ('u' . (string) $request->user()->id) : 'guest';

            return Limit::perMinute(10)
                ->by('invite-accept:' . $actor . '|' . (string) $request->ip())
                ->response($onLimit);
        });

        RateLimiter::for('subscription-payment-submit', static function (Request $request) use ($onLimit): Limit {
            $actor = $request->user()?->id ? ('u' . (string) $request->user()->id) : 'guest';

            return Limit::perMinute(8)
                ->by('sub-pay-submit:' . $actor . '|' . (string) $request->ip())
                ->response($onLimit);
        });

        RateLimiter::for('customer-payment-action', static function (Request $request) use ($onLimit): Limit {
            $actor = $request->user()?->id ? ('u' . (string) $request->user()->id) : 'guest';

            return Limit::perMinute(10)
                ->by('cust-pay-action:' . $actor . '|' . (string) $request->ip())
                ->response($onLimit);
        });

        RateLimiter::for('platform-payment-actions', static function (Request $request) use ($onLimit): Limit {
            $actor = $request->user('platform')?->id
                ? ('pa' . (string) $request->user('platform')->id)
                : 'guest';

            return Limit::perMinute(20)
                ->by('plat-pay-action:' . $actor . '|' . (string) $request->ip())
                ->response($onLimit);
        });
    }

    private static function normalizedEmail(Request $request): string
    {
        return strtolower(trim((string) $request->input('email', '')));
    }
}
