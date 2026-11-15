<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Issue a password reset link without disclosing account existence.
     *
     * Mail transport failures are logged cleanly without secrets/tokens
     * and still return the identical generic status response to prevent enumeration.
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $user = User::where('email', $email)->first();

        if ($user !== null) {
            try {
                $status = Password::broker('users')->sendResetLink(['email' => $email]);
                if ($status === Password::RESET_THROTTLED) {
                    Log::warning('Password reset broker throttled repeat link dispatch.', [
                        'user_id' => $user->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('Password reset email dispatch failed.', [
                    'user_id' => $user->id,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('status', __('auth.password_reset_link_sent_generic'));
    }

    public function showResetForm(Request $request, string $token): View
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        $user = $email !== '' ? User::where('email', $email)->first() : null;
        $tokenValid = $user !== null && Password::broker('users')->tokenExists($user, $token);

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'tokenValid' => $tokenValid,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        $status = Password::broker('users')->reset(
            [
                'email' => $email,
                'password' => $validated['password'],
                'password_confirmation' => $validated['password'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();

                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))
                        ->where('user_id', $user->id)
                        ->delete();
                }

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('auth.reset_password_invalid_token')]);
        }

        return redirect()
            ->route('login')
            ->with('success', __('auth.password_reset_success'));
    }
}
