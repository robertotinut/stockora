<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\CompanyLifecycleService;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCompany
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyLifecycleService $lifecycle
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            abort(401, 'Authentication required.');
        }

        $requestedCompanyId = $request->header('X-Company-Id')
            ?? ($request->hasSession() ? $request->session()->get('active_company_id') : null);

        if ($requestedCompanyId !== null && $requestedCompanyId !== '') {
            $companyId = (int) $requestedCompanyId;
            if ($companyId <= 0) {
                abort(403, 'Invalid tenant context.');
            }

            $membership = DB::selectOne(
                'SELECT cu.company_id
                 FROM company_user cu
                 WHERE cu.company_id = ? AND cu.user_id = ? AND cu.is_active = 1
                 LIMIT 1',
                [$companyId, (int) $user->id]
            );

            if (!$membership) {
                abort(403, 'Unauthorized company access.');
            }
        } else {
            // Prefer an operationally active company first; if none exist, fall back to user's pending/blocked workspace.
            $membership = DB::selectOne(
                "SELECT cu.company_id
                 FROM company_user cu
                 JOIN companies c ON c.id = cu.company_id
                 WHERE cu.user_id = ? AND cu.is_active = 1
                 ORDER BY CASE
                     WHEN c.is_active = 1
                      AND c.account_status = 'ACTIVE'
                      AND (c.access_expires_at IS NULL OR c.access_expires_at >= NOW())
                     THEN 0 ELSE 1
                 END ASC,
                 cu.is_default DESC,
                 cu.company_id ASC
                 LIMIT 1",
                [(int) $user->id]
            );

            if (!$membership) {
                if ($request->expectsJson()) {
                    abort(403, 'User has no active company membership.');
                }

                $hasAnyMembership = DB::selectOne(
                    'SELECT 1 AS found FROM company_user WHERE user_id = ? LIMIT 1',
                    [(int) $user->id]
                );

                if ($hasAnyMembership) {
                    abort(403, 'Your company membership is inactive or access has been revoked.');
                }

                return redirect()
                    ->route('onboarding.company.create')
                    ->with('warning', 'Complete your company workspace setup before accessing Stockora operations.');
            }

            $companyId = (int) $membership->company_id;
        }

        $company = Company::find($companyId);
        if (!$company) {
            abort(403, 'Unauthorized company access.');
        }

        $blockReason = $this->lifecycle->determineBlockReason($company);
        if ($blockReason !== null) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $this->blockMessage($blockReason),
                    'status' => $company->effectiveStatus(),
                    'reason' => $blockReason,
                ], 403);
            }

            if ($request->hasSession()) {
                $request->session()->put('status_company_id', (int) $company->id);
            }

            return redirect()
                ->route('company.access-status')
                ->with('warning', $this->blockMessage($blockReason));
        }

        $this->tenantContext->setCompany($companyId, (int) $user->id);

        return $next($request);
    }

    private function blockMessage(string $reason): string
    {
        return match ($reason) {
            'pending_activation' => 'Your company workspace is pending activation by Stockora Platform Admin.',
            'suspended' => 'Your company workspace has been temporarily suspended. Your inventory data remains safely preserved.',
            'expired' => 'Your company workspace access period has expired. Please contact Stockora support for renewal.',
            'cancelled' => 'Your company workspace has been cancelled.',
            default => 'Your company workspace is currently inactive.',
        };
    }
}
