<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Support\StockoraLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PasswordSetupController extends Controller
{
    public function show(Request $request, string $token)
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $user = $email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->first() : null;
        $tokenValid = $user !== null && Password::broker('users')->tokenExists($user, $token);

        return view('auth.setup-password', [
            'token' => $token,
            'email' => $email,
            'tokenValid' => $tokenValid,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker('users')->reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$validated['email']])->firstOrFail();

        Auth::guard('web')->login($user);
        StockoraLocale::syncGuestLocaleOnAuth($user, $request, false);
        $request->session()->regenerate();
        $request->session()->forget(['active_company_id', 'onboarding_ready_company_id', 'status_company_id']);

        $provisioning = app(CompanyProvisioningService::class);
        if (!$provisioning->hasAnyWorkspace($user)) {
            return redirect()->route('onboarding.company.create');
        }

        if (!$provisioning->hasActiveCompany($user)) {
            return redirect()->route('company.access-status');
        }

        $firstActive = $user->companies()
            ->wherePivot('is_active', 1)
            ->where('companies.is_active', 1)
            ->where('companies.account_status', Company::STATUS_ACTIVE)
            ->first();

        if ($firstActive) {
            $request->session()->put('active_company_id', (int) $firstActive->id);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('auth.setup_password_success'));
    }
}
