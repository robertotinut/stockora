<?php

namespace App\Http\Controllers;

use App\Services\InvitationService;
use App\Services\PermissionRegistry;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class InvitationAcceptController extends Controller
{
    public function show(string $token, Request $request, InvitationService $invitations)
    {
        $invitation = $invitations->findByRawToken($token);

        if (!$invitation) {
            return response()->view('invitations.accept', [
                'state' => 'invalid',
                'token' => $token,
                'invitation' => null,
                'rolesMeta' => PermissionRegistry::ROLES,
                'emailMismatch' => false,
            ], 404);
        }

        if ($invitation->company) {
            app(\App\Services\CompanyLifecycleService::class)->determineBlockReason($invitation->company);
            $invitation->company->refresh();
        }

        $effectiveStatus = $invitation->effectiveStatus();
        $state = match (true) {
            $effectiveStatus === 'revoked' => 'revoked',
            $effectiveStatus === 'accepted' => 'accepted',
            $effectiveStatus === 'expired' => 'expired',
            !$invitation->company || !$invitation->company->isOperationallyActive() => 'inactive_company',
            default => 'valid',
        };

        $user = $request->user();
        $emailMismatch = $user !== null
            && strtolower(trim($user->email)) !== strtolower(trim($invitation->email));

        if ($state === 'valid' && (!$user || $emailMismatch)) {
            $request->session()->put('pending_invitation_url', route('invitations.show', ['token' => $token]));
            $request->session()->put('pending_invitation_email', $invitation->email);
        }

        return view('invitations.accept', [
            'state' => $state,
            'token' => $token,
            'invitation' => $invitation,
            'rolesMeta' => PermissionRegistry::ROLES,
            'emailMismatch' => $emailMismatch,
        ]);
    }

    public function accept(string $token, Request $request, InvitationService $invitations)
    {
        try {
            $company = $invitations->acceptInvitation($token, $request->user());
        } catch (AuthorizationException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 403)
                : abort(403, $e->getMessage());
        } catch (DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : redirect()->route('invitations.show', ['token' => $token])->with('error', $e->getMessage());
        }

        $request->session()->forget(['pending_invitation_url', 'pending_invitation_email', 'onboarding_ready_company_id']);
        $request->session()->put('active_company_id', (int) $company->id);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('auth.invitation_joined_success', ['company' => $company->name]),
                'company_id' => (int) $company->id,
            ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', __('auth.invitation_joined_success', ['company' => $company->name]));
    }
}
