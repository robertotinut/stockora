<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Notifications\CompanyInvitationNotification;
use App\Support\StockoraFormat;
use App\Support\StockoraLocale;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class InvitationService
{
    public const EXPIRY_DAYS = 7;

    public function __construct(
        private TenantContext $tenantContext,
        private RbacService $rbac,
        private SubscriptionPlanService $plans
    ) {}

    public function hasRealMailTransport(): bool
    {
        return !in_array(config('mail.default'), ['log'], true);
    }

    /** @return array{invitation: CompanyInvitation, accept_url: string, mail_dispatched: bool} */
    public function createInvitation(
        int $companyId,
        User $inviter,
        string $email,
        string $role,
        ?string $personalMessage = null
    ): array {
        $email = strtolower(trim($email));
        $role = strtolower(trim($role));

        if (!$this->rbac->canAccessModule($inviter, $companyId, 'team_management', 'create')) {
            throw new AuthorizationException(__('team.errors.no_invite_perm'));
        }

        if (!in_array($role, PermissionRegistry::assignableRoles(), true)) {
            throw new DomainException(__('team.errors.invite_role_invalid'));
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $invitation = DB::transaction(function () use ($companyId, $inviter, $email, $role, $personalMessage, $tokenHash): CompanyInvitation {
            return $this->tenantContext->withoutScope(function () use ($companyId, $inviter, $email, $role, $personalMessage, $tokenHash): CompanyInvitation {
                $company = Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])
                    ->where('id', $companyId)
                    ->where('is_active', 1)
                    ->firstOrFail();

                if (!$this->plans->hasFeature($company, 'module_team_invitations')) {
                    throw new AuthorizationException(__('subscription.feature_locked_message', [
                        'feature' => __('subscription.features.module_team_invitations'),
                        'plan' => $this->plans->getCompanyPlan($company)?->name ?? ($company->access_type ?: 'Free'),
                    ]));
                }

                $this->plans->assertCanCreateQuota(
                    $company,
                    'max_users',
                    1,
                    null,
                    true
                );

                $alreadyMember = DB::table('company_user')
                    ->join('users', 'users.id', '=', 'company_user.user_id')
                    ->where('company_user.company_id', $company->id)
                    ->whereRaw('LOWER(users.email) = ?', [$email])
                    ->exists();

                if ($alreadyMember) {
                    throw new DomainException(__('team.errors.already_member'));
                }

                $duplicatePending = CompanyInvitation::where('company_id', $company->id)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->lockForUpdate()
                    ->exists();

                if ($duplicatePending) {
                    throw new DomainException(__('team.errors.duplicate_pending'));
                }

                return CompanyInvitation::create([
                    'company_id' => $company->id,
                    'invited_by_user_id' => $inviter->id,
                    'email' => $email,
                    'role' => $role,
                    'token_hash' => $tokenHash,
                    'personal_message' => $personalMessage !== null && trim($personalMessage) !== ''
                        ? trim($personalMessage)
                        : null,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(self::EXPIRY_DAYS),
                    'last_sent_at' => now(),
                ]);
            });
        });

        $acceptUrl = route('invitations.show', ['token' => $plainToken]);
        $mailDispatched = $this->dispatchMailIfConfigured($invitation, $inviter, $acceptUrl);

        return [
            'invitation' => $invitation,
            'accept_url' => $acceptUrl,
            'mail_dispatched' => $mailDispatched,
        ];
    }

    /** @return array{invitation: CompanyInvitation, accept_url: string, mail_dispatched: bool} */
    public function resendInvitation(int $companyId, int $invitationId, User $actor): array
    {
        if (!$this->rbac->canAccessModule($actor, $companyId, 'team_management', 'create')) {
            throw new AuthorizationException(__('team.errors.no_resend_perm'));
        }

        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);

        $invitation = DB::transaction(function () use ($companyId, $invitationId, $tokenHash): CompanyInvitation {
            $invitation = CompanyInvitation::where('id', $invitationId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                abort(404, __('team.errors.invite_not_found'));
            }

            if ($invitation->status !== 'pending') {
                throw new DomainException(__('team.errors.only_pending_resend'));
            }

            $invitation->update([
                'token_hash' => $tokenHash,
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
                'last_sent_at' => now(),
            ]);

            return $invitation->fresh(['company', 'invitedBy']);
        });

        $acceptUrl = route('invitations.show', ['token' => $plainToken]);
        $mailDispatched = $this->dispatchMailIfConfigured($invitation, $actor, $acceptUrl);

        return [
            'invitation' => $invitation,
            'accept_url' => $acceptUrl,
            'mail_dispatched' => $mailDispatched,
        ];
    }

    public function revokeInvitation(int $companyId, int $invitationId, User $actor): CompanyInvitation
    {
        if (!$this->rbac->canAccessModule($actor, $companyId, 'team_management', 'delete')) {
            throw new AuthorizationException(__('team.errors.no_revoke_perm'));
        }

        return DB::transaction(function () use ($companyId, $invitationId, $actor): CompanyInvitation {
            $invitation = CompanyInvitation::where('id', $invitationId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->first();

            if (!$invitation) {
                abort(404, __('team.errors.invite_not_found'));
            }

            if ($invitation->status !== 'pending') {
                throw new DomainException(__('team.errors.only_pending_revoke'));
            }

            $invitation->update([
                'status' => 'revoked',
                'revoked_by_user_id' => $actor->id,
                'revoked_at' => now(),
            ]);

            return $invitation;
        });
    }

    public function findByRawToken(string $plainToken): ?CompanyInvitation
    {
        if (trim($plainToken) === '') {
            return null;
        }

        $hash = hash('sha256', trim($plainToken));

        return $this->tenantContext->withoutScope(function () use ($hash): ?CompanyInvitation {
            return CompanyInvitation::with(['company', 'invitedBy'])
                ->where('token_hash', $hash)
                ->first();
        });
    }

    public function acceptInvitation(string $plainToken, User $user): Company
    {
        $hash = hash('sha256', trim($plainToken));

        $company = DB::transaction(function () use ($hash, $user): Company {
            return $this->tenantContext->withoutScope(function () use ($hash, $user): Company {
                $invitation = CompanyInvitation::where('token_hash', $hash)
                    ->lockForUpdate()
                    ->first();

                if (!$invitation) {
                    abort(404, __('team.errors.invalid_token'));
                }

                if ($invitation->status !== 'pending') {
                    throw new DomainException(__('team.errors.invite_no_longer_valid'));
                }

                if ($invitation->isExpired()) {
                    throw new DomainException(__('team.errors.invite_expired'));
                }

                if (strtolower(trim($user->email)) !== strtolower(trim($invitation->email))) {
                    throw new AuthorizationException(__('team.errors.wrong_email'));
                }

                $company = Company::where('id', $invitation->company_id)->first();
                if ($company) {
                    app(CompanyLifecycleService::class)->determineBlockReason($company);
                    $company->refresh();
                }
                if (!$company || !$company->isOperationallyActive()) {
                    throw new DomainException(__('team.errors.company_not_active'));
                }

                $role = strtolower((string) $invitation->role);
                if (!in_array($role, PermissionRegistry::assignableRoles(), true)) {
                    throw new DomainException(__('team.errors.invite_role_broken'));
                }

                // Security check: verify inviter still holds active invite authority in this company.
                $inviter = User::find($invitation->invited_by_user_id);
                $this->rbac->clearCache();
                if (!$inviter || !$this->rbac->canAccessModule($inviter, (int) $company->id, 'team_management', 'create')) {
                    throw new AuthorizationException(__('team.errors.issuer_no_authority'));
                }

                $existing = DB::table('company_user')
                    ->where('company_id', $company->id)
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new DomainException(__('team.errors.already_holds_membership'));
                }

                $this->plans->assertCanCreateQuota($company, 'max_users', 1);

                $hasDefault = DB::table('company_user')
                    ->where('user_id', $user->id)
                    ->where('is_default', 1)
                    ->where('is_active', 1)
                    ->exists();

                DB::table('company_user')->insert([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'role' => $role,
                    'is_default' => $hasDefault ? 0 : 1,
                    'is_active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $invitation->update([
                    'status' => 'accepted',
                    'accepted_by_user_id' => $user->id,
                    'accepted_at' => now(),
                ]);

                return $company;
            });
        });

        $this->rbac->clearCache();

        return $company;
    }

    private function dispatchMailIfConfigured(CompanyInvitation $invitation, User $actor, string $acceptUrl): bool
    {
        // Only send when testing (allows Notification::fake) or when a real transport (non-log mailer) is active.
        // Never write raw invitation tokens into log-driver files.
        if (!app()->environment('testing') && !$this->hasRealMailTransport()) {
            return false;
        }

        $companyName = $invitation->company?->name
            ?? Company::where('id', $invitation->company_id)->value('name')
            ?? 'Stockora Workspace';

        $targetUser = User::whereRaw('LOWER(email) = ?', [strtolower(trim($invitation->email))])->first();
        $mailLocale = $targetUser?->preferredLocale()
            ?? $actor->preferredLocale()
            ?? StockoraLocale::current();

        Notification::route('mail', $invitation->email)->notify(
            (new CompanyInvitationNotification(
                companyName: $companyName,
                inviterName: $actor->name,
                role: $invitation->role,
                acceptUrl: $acceptUrl,
                expiresAtFormatted: StockoraFormat::dateTime($invitation->expires_at, $mailLocale),
                personalMessage: $invitation->personal_message,
            ))->locale($mailLocale)
        );

        return true;
    }
}
