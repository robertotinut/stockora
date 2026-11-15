<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Services\InvitationService;
use App\Services\PermissionRegistry;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamInvitationController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, InvitationService $invitations)
    {
        $companyId = $tenantContext->requireCompanyId();
        $activeCompany = Company::findOrFail($companyId);
        $statusFilter = trim((string) $request->query('status', ''));

        $rows = CompanyInvitation::with(['invitedBy', 'acceptedBy'])
            ->where('company_id', $companyId)
            ->when($statusFilter === 'pending', fn ($q) => $q->where('status', 'pending')->where('expires_at', '>', now()))
            ->when($statusFilter === 'expired', fn ($q) => $q->where('status', 'pending')->where('expires_at', '<=', now()))
            ->when(in_array($statusFilter, ['accepted', 'revoked'], true), fn ($q) => $q->where('status', $statusFilter))
            ->orderByRaw("CASE WHEN status = 'pending' AND expires_at > NOW() THEN 0 WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $assignableRoles = PermissionRegistry::assignableRoles();
        $rolesMeta = PermissionRegistry::ROLES;
        $hasRealMailTransport = $invitations->hasRealMailTransport();

        return view('team.invitations', compact(
            'rows',
            'activeCompany',
            'statusFilter',
            'assignableRoles',
            'rolesMeta',
            'hasRealMailTransport'
        ));
    }

    public function store(Request $request, TenantContext $tenantContext, InvitationService $invitations)
    {
        $companyId = $tenantContext->requireCompanyId();

        $request->merge([
            'email' => strtolower(trim((string) $request->input('email', ''))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(PermissionRegistry::assignableRoles())],
            'personal_message' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $invitations->createInvitation(
                $companyId,
                $request->user(),
                $validated['email'],
                $validated['role'],
                $validated['personal_message'] ?? null
            );
        } catch (AuthorizationException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 403)
                : abort(403, $e->getMessage());
        } catch (DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        if (!$result['mail_dispatched'] && !app()->environment('production')) {
            $request->session()->flash('sandbox_invitation_preview', [
                'email' => $result['invitation']->email,
                'role' => $result['invitation']->role,
                'url' => $result['accept_url'],
            ]);
        }

        $msg = $result['mail_dispatched']
            ? __('team.flash.invitation_sent', ['email' => $result['invitation']->email])
            : __('team.flash.invitation_preview', ['email' => $result['invitation']->email]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $msg,
                'invitation_id' => $result['invitation']->id,
            ], 201);
        }

        return redirect()->route('team.invitations.index')->with('success', $msg);
    }

    public function resend(int $invitation, Request $request, TenantContext $tenantContext, InvitationService $invitations)
    {
        $companyId = $tenantContext->requireCompanyId();

        try {
            $result = $invitations->resendInvitation($companyId, $invitation, $request->user());
        } catch (AuthorizationException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 403)
                : abort(403, $e->getMessage());
        } catch (DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        if (!$result['mail_dispatched'] && !app()->environment('production')) {
            $request->session()->flash('sandbox_invitation_preview', [
                'email' => $result['invitation']->email,
                'role' => $result['invitation']->role,
                'url' => $result['accept_url'],
            ]);
        }

        $msg = $result['mail_dispatched']
            ? __('team.flash.invitation_resent', ['email' => $result['invitation']->email])
            : __('team.flash.invitation_resent_preview', ['email' => $result['invitation']->email]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $msg]);
        }

        return redirect()->route('team.invitations.index')->with('success', $msg);
    }

    public function destroy(int $invitation, Request $request, TenantContext $tenantContext, InvitationService $invitations)
    {
        $companyId = $tenantContext->requireCompanyId();

        try {
            $revoked = $invitations->revokeInvitation($companyId, $invitation, $request->user());
        } catch (AuthorizationException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 403)
                : abort(403, $e->getMessage());
        } catch (DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->with('error', $e->getMessage());
        }

        $msg = __('team.flash.invitation_revoked', ['email' => $revoked->email]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $msg]);
        }

        return redirect()->route('team.invitations.index')->with('success', $msg);
    }
}
