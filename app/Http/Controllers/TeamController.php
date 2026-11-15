<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Services\PermissionRegistry;
use App\Services\RbacService;
use App\Services\TeamManagementService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, RbacService $rbac)
    {
        $companyId = $tenantContext->requireCompanyId();
        $activeCompany = Company::findOrFail($companyId);

        $search = trim((string) $request->query('search', ''));
        $roleFilter = strtolower(trim((string) $request->query('role', '')));
        $statusFilter = trim((string) $request->query('status', ''));

        $sessionSubquery = DB::table('sessions')
            ->selectRaw('user_id, MAX(last_activity) AS last_activity_at')
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        $members = DB::table('company_user AS cu')
            ->join('users AS u', 'u.id', '=', 'cu.user_id')
            ->leftJoinSub($sessionSubquery, 's', fn ($join) => $join->on('s.user_id', '=', 'u.id'))
            ->where('cu.company_id', $companyId)
            ->select([
                'cu.id',
                'cu.company_id',
                'cu.user_id',
                DB::raw('LOWER(cu.role) AS role'),
                'cu.is_default',
                'cu.is_active',
                'cu.created_at',
                'u.name',
                'u.email',
                's.last_activity_at',
            ])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('u.name', 'like', "%{$search}%")
                        ->orWhere('u.email', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter !== '' && PermissionRegistry::isValidRole($roleFilter), function ($q) use ($roleFilter): void {
                $q->whereRaw('LOWER(cu.role) = ?', [$roleFilter]);
            })
            ->when($statusFilter === 'active', fn ($q) => $q->where('cu.is_active', 1))
            ->when($statusFilter === 'inactive', fn ($q) => $q->where('cu.is_active', 0))
            ->orderByRaw("FIELD(LOWER(cu.role), 'owner', 'admin', 'manager', 'staff', 'viewer')")
            ->orderByDesc('cu.is_active')
            ->orderBy('u.name')
            ->paginate(15)
            ->withQueryString();

        $totalMembers = DB::table('company_user')->where('company_id', $companyId)->count();
        $activeMembers = DB::table('company_user')->where('company_id', $companyId)->where('is_active', 1)->count();
        $activeOwnersCount = DB::table('company_user')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(role) = ?', [PermissionRegistry::ROLE_OWNER])
            ->where('is_active', 1)
            ->count();

        $pendingInvitations = CompanyInvitation::where('company_id', $companyId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        $currentUserRole = $rbac->roleFor($request->user(), $companyId) ?? PermissionRegistry::ROLE_VIEWER;
        $isCurrentUserOwner = $currentUserRole === PermissionRegistry::ROLE_OWNER;
        $rolesMeta = PermissionRegistry::ROLES;

        $transferCandidates = $isCurrentUserOwner
            ? DB::table('company_user AS cu')
                ->join('users AS u', 'u.id', '=', 'cu.user_id')
                ->where('cu.company_id', $companyId)
                ->where('cu.is_active', 1)
                ->where('cu.user_id', '!=', $request->user()->id)
                ->select(['cu.id', 'u.name', 'u.email', DB::raw('LOWER(cu.role) AS role')])
                ->orderBy('u.name')
                ->get()
            : collect();

        return view('team.index', compact(
            'members',
            'activeCompany',
            'search',
            'roleFilter',
            'statusFilter',
            'totalMembers',
            'activeMembers',
            'activeOwnersCount',
            'pendingInvitations',
            'currentUserRole',
            'isCurrentUserOwner',
            'rolesMeta',
            'transferCandidates'
        ));
    }

    public function show(int $member, Request $request, TenantContext $tenantContext, RbacService $rbac)
    {
        $companyId = $tenantContext->requireCompanyId();
        $activeCompany = Company::findOrFail($companyId);

        $sessionSubquery = DB::table('sessions')
            ->selectRaw('user_id, MAX(last_activity) AS last_activity_at')
            ->whereNotNull('user_id')
            ->groupBy('user_id');

        $memberRow = DB::table('company_user AS cu')
            ->join('users AS u', 'u.id', '=', 'cu.user_id')
            ->leftJoinSub($sessionSubquery, 's', fn ($join) => $join->on('s.user_id', '=', 'u.id'))
            ->where('cu.id', $member)
            ->where('cu.company_id', $companyId)
            ->select([
                'cu.id',
                'cu.company_id',
                'cu.user_id',
                DB::raw('LOWER(cu.role) AS role'),
                'cu.is_default',
                'cu.is_active',
                'cu.created_at',
                'cu.updated_at',
                'u.name',
                'u.email',
                's.last_activity_at',
            ])
            ->first();

        if (!$memberRow) {
            abort(404, 'Team member not found in this workspace.');
        }

        $activeOwnersCount = DB::table('company_user')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(role) = ?', [PermissionRegistry::ROLE_OWNER])
            ->where('is_active', 1)
            ->count();

        $isLastActiveOwner = $memberRow->role === PermissionRegistry::ROLE_OWNER
            && (int) $memberRow->is_active === 1
            && $activeOwnersCount <= 1;

        $isCurrentUserOwner = $rbac->isOwner($request->user(), $companyId);
        $canManageTarget = $memberRow->role !== PermissionRegistry::ROLE_OWNER || ($isCurrentUserOwner && !$isLastActiveOwner);
        $isSelf = (int) $memberRow->user_id === (int) $request->user()->id;

        $rolesMeta = PermissionRegistry::ROLES;
        $assignableRoles = PermissionRegistry::assignableRoles();
        $modules = PermissionRegistry::MODULES;
        $actions = PermissionRegistry::ACTIONS;
        $effectivePermissions = $rbac->effectivePermissionsForRole($companyId, $memberRow->role);

        return view('team.show', compact(
            'memberRow',
            'activeCompany',
            'isLastActiveOwner',
            'isCurrentUserOwner',
            'canManageTarget',
            'isSelf',
            'rolesMeta',
            'assignableRoles',
            'modules',
            'actions',
            'effectivePermissions'
        ));
    }

    public function updateRole(int $member, Request $request, TenantContext $tenantContext, TeamManagementService $service)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(PermissionRegistry::assignableRoles())],
        ]);

        try {
            $service->updateMemberRole($companyId, $member, $validated['role'], $request->user());
        } catch (AuthorizationException $e) {
            return $this->forbiddenResponse($request, $e->getMessage());
        } catch (DomainException $e) {
            return $this->domainErrorResponse($request, $e->getMessage());
        }

        $roleLabel = PermissionRegistry::ROLES[$validated['role']]['label'] ?? strtoupper($validated['role']);

        return $this->successRedirect($request, 'team.show', $member, __('team.flash.role_updated', ['role' => $roleLabel]));
    }

    public function updateStatus(int $member, Request $request, TenantContext $tenantContext, TeamManagementService $service)
    {
        $companyId = $tenantContext->requireCompanyId();

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $activate = (bool) $validated['is_active'];

        try {
            $service->updateMemberStatus($companyId, $member, $activate, $request->user());
        } catch (AuthorizationException $e) {
            return $this->forbiddenResponse($request, $e->getMessage());
        } catch (DomainException $e) {
            return $this->domainErrorResponse($request, $e->getMessage());
        }

        $msg = $activate ? __('team.flash.activated') : __('team.flash.deactivated');

        if ($request->expectsJson()) {
            return response()->json(['message' => $msg]);
        }

        return back()->with('success', $msg);
    }

    public function destroy(int $member, Request $request, TenantContext $tenantContext, TeamManagementService $service)
    {
        $companyId = $tenantContext->requireCompanyId();

        try {
            $service->removeMember($companyId, $member, $request->user());
        } catch (AuthorizationException $e) {
            return $this->forbiddenResponse($request, $e->getMessage());
        } catch (DomainException $e) {
            return $this->domainErrorResponse($request, $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('team.flash.removed')]);
        }

        return redirect()->route('team.index')->with('success', __('team.flash.removed'));
    }

    public function transferOwnership(Request $request, TenantContext $tenantContext, TeamManagementService $service)
    {
        $companyId = $tenantContext->requireCompanyId();
        $activeCompany = Company::findOrFail($companyId);

        if (!app(RbacService::class)->isOwner($request->user(), $companyId)) {
            return $this->forbiddenResponse($request, __('team.flash.owner_only_transfer'));
        }

        $validated = $request->validate([
            'target_member_id' => ['required', 'integer', 'min:1'],
            'confirm_transfer' => ['accepted'],
            'confirm_company_code' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($activeCompany): void {
                    if (strtoupper(trim((string) $value)) !== strtoupper($activeCompany->code)) {
                        $fail(__('team.flash.confirm_code_mismatch', ['code' => $activeCompany->code]));
                    }
                },
            ],
        ]);

        try {
            $service->transferOwnership($companyId, (int) $validated['target_member_id'], $request->user());
        } catch (AuthorizationException $e) {
            return $this->forbiddenResponse($request, $e->getMessage());
        } catch (DomainException $e) {
            return $this->domainErrorResponse($request, $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('team.flash.ownership_transferred')]);
        }

        return redirect()->route('team.index')->with('success', __('team.flash.ownership_transferred'));
    }

    private function forbiddenResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        abort(403, $message);
    }

    private function domainErrorResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->with('error', $message);
    }

    private function successRedirect(Request $request, string $route, int $memberId, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route($route, $memberId)->with('success', $message);
    }
}
