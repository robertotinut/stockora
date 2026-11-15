@extends('layouts.admin')

@section('title', __('team.page_title'))
@section('page_title', __('team.heading'))
@section('page_subtitle', __('team.index_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('team.dashboard_breadcrumb') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('team.page_title') }}</li>
@endsection

@section('actions')
  @if(auth()->user()->canAccessModule('team_management', 'manage') && ($planFeatures['module_role_permissions']['enabled'] ?? false))
    <a href="{{ route('team.permissions.index') }}" class="btn btn-white">
      <i data-feather="shield" class="svg-14"></i> {{ __('team.role_permissions_btn') }}
    </a>
  @endif
  @if($isCurrentUserOwner && $transferCandidates->isNotEmpty())
    <button type="button" class="btn btn-white" data-toggle="modal" data-target="#stkTransferOwnershipModal">
      <i data-feather="repeat" class="svg-14"></i> {{ __('team.transfer_ownership_btn') }}
    </button>
  @endif
  @if(auth()->user()->canAccessModule('team_management', 'create') && ($planFeatures['module_team_invitations']['enabled'] ?? false))
    <a href="{{ route('team.invitations.index') }}" class="btn btn-brand-01">
      <i data-feather="user-plus" class="svg-14"></i> {{ __('team.invite_member_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-xs mg-b-20">
    <div class="col-6 col-lg-3 mg-b-12 mg-lg-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <span class="stk-kpi-label mg-b-0">{{ __('team.total_members') }}</span>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="users" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ $totalMembers }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('team.joined_accounts_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3 mg-b-12 mg-lg-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <span class="stk-kpi-label mg-b-0">{{ __('team.active_members') }}</span>
            <span class="stk-kpi-icon stk-kpi-icon-emerald"><i data-feather="user-check" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ $activeMembers }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">
            {{ $activeOwnersCount === 1 ? __('team.active_owners_single', ['count' => $activeOwnersCount]) : __('team.active_owners_plural', ['count' => $activeOwnersCount]) }}
          </div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <span class="stk-kpi-label mg-b-0">{{ __('team.pending_invites') }}</span>
            <span class="stk-kpi-icon stk-kpi-icon-blue"><i data-feather="mail" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ $pendingInvitations }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('team.awaiting_acceptance') }}</div>
        </div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <span class="stk-kpi-label mg-b-0">{{ __('team.your_role') }}</span>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="key" class="svg-16"></i></span>
          </div>
          <div class="mg-t-6 mg-b-4">
            @include('partials.role-badge', [
              'role' => $currentUserRole,
              'label' => $currentUserRole ? strtoupper(__('permissions.roles.' . strtolower($currentUserRole) . '.label')) : __('team.role_none'),
            ])
          </div>
          <div class="tx-12 tx-color-03">
            {{ match ($currentUserRole) {
                'owner' => __('team.role_summary.owner'),
                'admin' => __('team.role_summary.admin'),
                'manager' => __('team.role_summary.manager'),
                'staff' => __('team.role_summary.staff'),
                default => __('team.role_summary.viewer'),
            } }}
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('team.company_members_title', ['company' => $activeCompany->name]) }}</h6>
        <span class="badge badge-light tx-numeric">{{ $members->total() }}</span>
      </div>

      <form action="{{ route('team.index') }}" method="GET" class="d-flex flex-wrap align-items-center" style="gap: 8px;">
        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('team.search_member_placeholder') }}" style="width: 195px; flex: 0 0 auto;">
        <select name="role" class="custom-select custom-select-sm" style="width: auto;">
          <option value="">{{ __('team.all_roles') }}</option>
          @foreach($rolesMeta as $roleKey => $meta)
            <option value="{{ $roleKey }}" @selected($roleFilter === $roleKey)>{{ __('permissions.roles.' . $roleKey . '.label') }}</option>
          @endforeach
        </select>
        <select name="status" class="custom-select custom-select-sm" style="width: auto;">
          <option value="">{{ __('team.all_statuses') }}</option>
          <option value="active" @selected($statusFilter === 'active')>{{ __('app.active') }}</option>
          <option value="inactive" @selected($statusFilter === 'inactive')>{{ __('app.inactive') }}</option>
        </select>
        <button type="submit" class="btn btn-sm btn-white">
          <i data-feather="filter" class="svg-14"></i> {{ __('app.filter') }}
        </button>
        @if($search !== '' || $roleFilter !== '' || $statusFilter !== '')
          <a href="{{ route('team.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
        @endif
      </form>
    </div>

    @if($members->isEmpty())
      <div class="card-body">
        @php
          $canInviteMember = auth()->user()->canAccessModule('team_management', 'create') && ($planFeatures['module_team_invitations']['enabled'] ?? false);
        @endphp
        @include('partials.empty-state', [
          'title' => __('team.no_members_title'),
          'description' => __('team.no_members_desc'),
          'icon' => 'users',
          'actionUrl' => $canInviteMember ? route('team.invitations.index') : null,
          'actionLabel' => $canInviteMember ? __('team.invite_member_btn') : null,
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('team.col_member') }}</th>
              <th>{{ __('team.role_label') }}</th>
              <th class="text-center">{{ __('app.status') }}</th>
              <th>{{ __('team.col_joined') }}</th>
              <th>{{ __('team.last_activity') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($members as $member)
              @php
                $isSelf = (int) $member->user_id === (int) auth()->id();
                $isTargetOwner = strtolower((string) $member->role) === 'owner';
                $isLastActiveOwner = $isTargetOwner && (int) $member->is_active === 1 && $activeOwnersCount <= 1;
                $canManageMember = $isCurrentUserOwner || !$isTargetOwner;
                $initials = collect(explode(' ', trim((string) $member->name)))
                    ->filter()
                    ->take(2)
                    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                    ->implode('') ?: 'U';
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center" style="gap: 10px;">
                    <div class="stk-avatar-initial">{{ $initials }}</div>
                    <div>
                      <div class="tx-medium tx-color-01">
                        {{ $member->name }}
                        @if($isSelf)
                          <span class="badge badge-light mg-l-4">{{ __('team.you_badge') }}</span>
                        @endif
                      </div>
                      <div class="tx-12 tx-color-03">{{ $member->email }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  @include('partials.role-badge', ['role' => $member->role])
                </td>
                <td class="text-center">
                  @include('partials.status-badge', ['status' => $member->is_active ? 'ACTIVE' : 'INACTIVE'])
                </td>
                <td class="tx-13 tx-color-03 text-nowrap">
                  {{ $member->created_at ? \App\Support\StockoraFormat::shortDate($member->created_at) : '—' }}
                </td>
                <td class="tx-13 tx-color-03 text-nowrap">
                  @if($member->last_activity_at)
                    {{ \Carbon\Carbon::createFromTimestamp((int) $member->last_activity_at)->locale(app()->getLocale())->diffForHumans() }}
                  @else
                    <span class="tx-color-04">{{ __('team.no_active_session') }}</span>
                  @endif
                </td>
                <td class="text-right text-nowrap">
                  <a href="{{ route('team.show', $member->id) }}" class="btn btn-xs btn-white">
                    <i data-feather="sliders" class="svg-12"></i> {{ __('team.manage_btn') }}
                  </a>

                  @if(auth()->user()->canAccessModule('team_management', 'edit') && $canManageMember && !$isLastActiveOwner)
                    <form action="{{ route('team.members.status', $member->id) }}"
                          method="POST"
                          class="d-inline mg-l-4"
                          data-confirm-title="{{ $member->is_active ? __('team.confirm_deactivate_title') : __('team.confirm_reactivate_title') }}"
                          data-confirm="{{ $member->is_active ? __('team.confirm_deactivate_msg', ['name' => $member->name, 'company' => $activeCompany->name]) : __('team.confirm_reactivate_msg', ['name' => $member->name]) }}"
                          data-confirm-btn="{{ $member->is_active ? __('team.deactivate_short') : __('team.activate_short') }}"
                          data-confirm-btn-class="{{ $member->is_active ? 'btn-warning' : 'btn-success' }}">
                      @csrf
                      @method('PATCH')
                      <input type="hidden" name="is_active" value="{{ $member->is_active ? 0 : 1 }}">
                      <button type="submit" class="btn btn-xs {{ $member->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                        {{ $member->is_active ? __('team.deactivate_short') : __('team.activate_short') }}
                      </button>
                    </form>
                  @endif

                  @if(auth()->user()->canAccessModule('team_management', 'delete') && $canManageMember && !$isLastActiveOwner)
                    <form action="{{ route('team.members.destroy', $member->id) }}"
                          method="POST"
                          class="d-inline mg-l-4"
                          data-confirm-title="{{ __('team.confirm_remove_title') }}"
                          data-confirm="{{ __('team.confirm_remove_msg', ['name' => $member->name, 'email' => $member->email, 'company' => $activeCompany->name]) }}"
                          data-confirm-btn="{{ __('team.confirm_remove_title') }}"
                          data-confirm-btn-class="btn-danger">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('app.remove') }}</button>
                    </form>
                  @endif

                  @if($isLastActiveOwner)
                    <span class="badge badge-light mg-l-4" title="{{ __('team.protected_owner_title') }}">{{ __('team.protected_owner_badge') }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($members->hasPages())
      <div class="card-footer">{{ $members->links() }}</div>
    @endif
  </div>

  @if($isCurrentUserOwner && $transferCandidates->isNotEmpty())
    <div class="modal fade" id="stkTransferOwnershipModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title d-flex align-items-center" style="gap: 8px;">
              <i data-feather="repeat" class="svg-16 text-warning"></i>
              {{ __('team.ownership_transfer_title') }}
            </h6>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('app.close') }}">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form action="{{ route('team.transfer-ownership') }}" method="POST">
            @csrf
            <div class="modal-body">
              <div class="alert alert-warning tx-13 mg-b-16">
                {{ __('team.transfer_modal_warning', ['company' => $activeCompany->name]) }}
              </div>

              <div class="form-group">
                <label class="tx-semibold" for="target_member_id">{{ __('team.new_company_owner') }} <span class="text-danger">*</span></label>
                <select name="target_member_id" id="target_member_id" class="form-control" required>
                  <option value="">{{ __('team.select_active_member') }}</option>
                  @foreach($transferCandidates as $candidate)
                    <option value="{{ $candidate->id }}">
                      {{ $candidate->name }} ({{ $candidate->email }}) &mdash; {{ strtoupper(__('permissions.roles.' . strtolower($candidate->role) . '.label')) }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="form-group">
                <label class="tx-semibold" for="confirm_company_code">{{ __('team.type_code_label') }} <code class="mg-l-4">{{ $activeCompany->code }}</code> {{ __('team.to_confirm_suffix') }} <span class="text-danger">*</span></label>
                <input type="text" name="confirm_company_code" id="confirm_company_code" class="form-control" placeholder="{{ $activeCompany->code }}" required autocomplete="off">
              </div>

              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="confirm_transfer" name="confirm_transfer" value="1" required>
                <label class="custom-control-label tx-13" for="confirm_transfer">
                  {{ __('team.confirm_transfer_check') }}
                </label>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-white" data-dismiss="modal">{{ __('app.cancel') }}</button>
              <button type="submit" class="btn btn-danger">{{ __('team.confirm_transfer_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif
@endsection
