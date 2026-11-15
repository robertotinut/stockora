<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StockoraLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'agree_terms' => ['accepted'],
        ], [
            'agree_terms.accepted' => __('auth.agree_terms_required'),
        ]);

        $user = User::create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        StockoraLocale::syncGuestLocaleOnAuth($user, $request, false);
        $request->session()->regenerate();
        $request->session()->forget(['active_company_id', 'onboarding_ready_company_id']);

        if ($pendingInviteUrl = $request->session()->pull('pending_invitation_url')) {
            return redirect()->to($pendingInviteUrl);
        }

        return redirect()
            ->route('onboarding.company.create')
            ->with('success', __('auth.register_success'));
    }
}
