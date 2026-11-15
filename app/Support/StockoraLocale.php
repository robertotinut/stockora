<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class StockoraLocale
{
    public const SUPPORTED = ['en', 'id'];

    public const TENANT_SESSION_KEY = 'guest_locale';

    public const PLATFORM_SESSION_KEY = 'platform_guest_locale';

    public static function supportedLocales(): array
    {
        return config('stockora.supported_locales', [
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
        ]);
    }

    public static function defaultLocale(): string
    {
        $configured = strtolower(trim((string) config('stockora.default_locale', config('app.locale', 'en'))));

        return in_array($configured, self::SUPPORTED, true) ? $configured : 'en';
    }

    public static function normalize(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $clean = strtolower(trim($locale));

        return in_array($clean, self::SUPPORTED, true) ? $clean : null;
    }

    public static function isPlatformRequest(Request $request): bool
    {
        return $request->is('platform', 'platform/*');
    }

    public static function resolveForRequest(Request $request): string
    {
        if (self::isPlatformRequest($request)) {
            $admin = Auth::guard('platform')->user();
            if ($admin !== null) {
                return self::normalize($admin->locale) ?? self::defaultLocale();
            }

            $guest = $request->hasSession()
                ? self::normalize($request->session()->get(self::PLATFORM_SESSION_KEY))
                : null;

            return $guest ?? self::defaultLocale();
        }

        $user = Auth::guard('web')->user();
        if ($user !== null) {
            return self::normalize($user->locale) ?? self::defaultLocale();
        }

        $guest = $request->hasSession()
            ? self::normalize($request->session()->get(self::TENANT_SESSION_KEY))
            : null;

        return $guest ?? self::defaultLocale();
    }

    public static function apply(string $locale): void
    {
        $normalized = self::normalize($locale) ?? self::defaultLocale();
        App::setLocale($normalized);
        Carbon::setLocale($normalized);
    }

    public static function current(): string
    {
        return self::normalize(App::getLocale()) ?? self::defaultLocale();
    }

    /**
     * Preserve an explicit pre-login guest locale ONLY if the authenticated account has no saved locale yet.
     */
    public static function syncGuestLocaleOnAuth(object $authenticatable, Request $request, bool $platform = false): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $sessionKey = $platform ? self::PLATFORM_SESSION_KEY : self::TENANT_SESSION_KEY;
        $guestLocale = self::normalize($request->session()->get($sessionKey));
        $savedLocale = self::normalize($authenticatable->locale ?? null);

        if ($savedLocale === null && $guestLocale !== null) {
            $authenticatable->forceFill(['locale' => $guestLocale])->save();
            $savedLocale = $guestLocale;
        }

        $request->session()->forget($sessionKey);

        self::apply($savedLocale ?? self::defaultLocale());
    }
}
