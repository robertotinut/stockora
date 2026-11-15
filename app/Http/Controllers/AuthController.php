<?php

namespace App\Http\Controllers;

use App\Support\StockoraLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = $request->user();
            if ($user) {
                StockoraLocale::syncGuestLocaleOnAuth($user, $request, false);
            }
            $request->session()->regenerate();
            $request->session()->forget(['active_company_id', 'onboarding_ready_company_id']);

            if ($pendingInviteUrl = $request->session()->pull('pending_invitation_url')) {
                return redirect()->to($pendingInviteUrl);
            }

            $activeCompanies = $request->user()
                ->companies()
                ->wherePivot('is_active', 1)
                ->where('companies.is_active', 1)
                ->where('companies.account_status', \App\Models\Company::STATUS_ACTIVE)
                ->where(function ($q): void {
                    $q->whereNull('companies.access_expires_at')
                        ->orWhere('companies.access_expires_at', '>=', now());
                })
                ->orderByDesc('company_user.is_default')
                ->orderBy('companies.id')
                ->get();

            if ($activeCompanies->count() > 1) {
                return redirect()->route('company.select');
            }

            if ($activeCompanies->count() === 1) {
                $request->session()->put('active_company_id', (int) $activeCompanies->first()->id);

                return redirect()->intended(route('dashboard'));
            }

            $blockedCompany = $request->user()
                ->companies()
                ->wherePivot('is_active', 1)
                ->orderByDesc('company_user.is_default')
                ->orderBy('companies.id')
                ->first();

            if ($blockedCompany) {
                $request->session()->put('status_company_id', (int) $blockedCompany->id);

                return redirect()->route('company.access-status');
            }

            $hasInactiveMembership = $request->user()->companies()->exists();

            return redirect()->route($hasInactiveMembership ? 'company.select' : 'onboarding.company.create');
        }

        return back()->withErrors([
            'email' => __('auth.failed'),
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('auth.signed_out'));
    }
}
