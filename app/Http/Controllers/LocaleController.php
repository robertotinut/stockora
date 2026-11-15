<?php

namespace App\Http\Controllers;

use App\Support\StockoraLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function updateTenantOrGuest(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(StockoraLocale::SUPPORTED)],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $locale = StockoraLocale::normalize($validated['locale']) ?? StockoraLocale::defaultLocale();

        $user = Auth::guard('web')->user();
        if ($user !== null) {
            $user->forceFill(['locale' => $locale])->save();
        } else {
            $request->session()->put(StockoraLocale::TENANT_SESSION_KEY, $locale);
        }

        StockoraLocale::apply($locale);

        if ($request->expectsJson()) {
            return response()->json(['locale' => $locale]);
        }

        return redirect()->to($this->resolveSafeRedirect($request, $validated['redirect_to'] ?? null, '/'));
    }

    public function updatePlatform(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(StockoraLocale::SUPPORTED)],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ]);

        $locale = StockoraLocale::normalize($validated['locale']) ?? StockoraLocale::defaultLocale();

        $admin = Auth::guard('platform')->user();
        if ($admin !== null) {
            $admin->forceFill(['locale' => $locale])->save();
        } else {
            $request->session()->put(StockoraLocale::PLATFORM_SESSION_KEY, $locale);
        }

        StockoraLocale::apply($locale);

        if ($request->expectsJson()) {
            return response()->json(['locale' => $locale]);
        }

        return redirect()->to($this->resolveSafeRedirect($request, $validated['redirect_to'] ?? null, '/platform/login'));
    }

    private function resolveSafeRedirect(Request $request, ?string $candidate, string $fallback): string
    {
        $raw = trim((string) ($candidate ?: url()->previous()));
        if ($raw === '') {
            return $fallback;
        }

        if (str_starts_with($raw, '/') && !str_starts_with($raw, '//')) {
            return $raw;
        }

        $parsed = parse_url($raw);
        if (!is_array($parsed) || empty($parsed['path'])) {
            return $fallback;
        }

        $reqHost = strtolower((string) $request->getHost());
        $targetHost = isset($parsed['host']) ? strtolower((string) $parsed['host']) : $reqHost;

        if ($targetHost !== $reqHost) {
            return $fallback;
        }

        $path = $parsed['path'];
        $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?' . $parsed['query'] : '';

        return $path . $query;
    }
}
