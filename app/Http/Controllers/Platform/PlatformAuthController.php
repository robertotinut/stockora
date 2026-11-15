<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Support\StockoraLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->route('platform.dashboard');
        }

        return view('platform.auth.login');
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = PlatformAdmin::whereRaw('LOWER(email) = ?', [$credentials['email']])->first();
        if ($admin && !$admin->is_active) {
            return back()
                ->withErrors(['email' => __('auth.platform_inactive_error')])
                ->onlyInput('email');
        }

        if (Auth::guard('platform')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => 1,
        ], $request->boolean('remember'))) {
            $loggedAdmin = Auth::guard('platform')->user();
            if ($loggedAdmin instanceof PlatformAdmin) {
                StockoraLocale::syncGuestLocaleOnAuth($loggedAdmin, $request, true);
                $loggedAdmin->forceFill(['last_login_at' => now()])->save();
            }

            $request->session()->regenerate();

            return redirect()->intended(route('platform.dashboard'));
        }

        return back()
            ->withErrors(['email' => __('auth.platform_invalid_credentials')])
            ->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->with('success', __('auth.platform_signed_out'));
    }
}
