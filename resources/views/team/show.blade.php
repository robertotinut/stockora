@extends('layouts.admin')

@section('title', __('team.manage_member_tab', ['name' => $memberRow->name]))
@section('page_title', $memberRow->name)
@section('page_subtitle', __('team.manage_member_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('team.dashboard_breadcrumb') }}</a></li>
  <li class="breadcrumb-item"><a href="{{ route('team.index') }}">{{ __('team.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $memberRow->name }}</li>
@endsection

@section('actions')
  <a href="{{ route('team.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('team.back_to_team') }}
  </a>
  @if(auth()->user()->canAccessModule('team_management', 'manage') && ($planFeatures['module_role_permissions']['enabled'] ?? false))
    <a href="{{ route('team.permissions.index', ['role' => $memberRow->role]) }}" class="btn btn-white">
      <i data-feather="shield" class="svg-14"></i> {{ __('team.configure_role_matrix_btn', ['role' => __('permissions.roles.' . $memberRow->role . '.label')]) }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm">
    <div class="col-xl-4 mg-b-20 mg-xl-b-0">
      <div class="card card-hover mg-b-20">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between" style="gap: 12px;">
            <div>
              <h5 class="tx-semibold tx-color-01 mg-b-2">
                {{ $memberRow->name }}
                @if($isSelf)
                  <span class="badge badge-light mg-l-4">{{ __('team.you_badge') }}</span>
                @endif
              </h5>
              <div class="tx-13 tx-color-03 mg-b-10">{{ $memberRow->email }}</div>
              <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                @include('partials.role-badge', ['role' => $memberRow->role])
                @include('partials.status-badge', ['status' => $memberRow->is_active ? 'ACTIVE' : 'INACTIVE'])
              </div>
            </div>
          </div>

          <hr class="mg-y-16">

          <dl class="row mg-b-0 tx-13">
            <dt class="col-5 tx-color-03">{{ __('team.workspace_label') }}</dt>
            <dd class="col-7 tx-medium">{{ $activeCompany->name }} ({{ $activeCompany->code }})</dd>

            <dt class="col-5 tx-color-03">{{ __('team.joined_on') }}</dt>
            <dd class="col-7">{{ $memberRow->created_at ? \App\Support\StockoraFormat::dateTime($memberRow->created_at) : '—' }}</dd>

            <dt class="col-5 tx-color-03 mb-0">{{ __('team.last_activity') }}</dt>
            <dd class="col-7 mb-0">
              @if($memberRow->last_activity_at)
                {{ \Carbon\Carbon::createFromTimestamp((int) $memberRow->last_activity_at)->locale(app()->getLocale())->diffForHumans() }}
              @else
                {{ __('team.no_active_session_recorded') }}
              @endif
            </dd>
          </dl>

          @if($isLastActiveOwner)
            <div class="alert alert-info tx-12 mg-t-16 mg-b-0">
              <strong>{{ __('team.last_owner_lock_title') }}</strong> {{ __('team.last_owner_lock_body', ['name' => $memberRow->name, 'company' => $activeCompany->name]) }}
            </div>
          @elseif(!$canManageTarget)
            <div class="alert alert-warning tx-12 mg-t-16 mg-b-0">
              {{ __('team.owner_only_modify_note') }}
            </div>
          @endif
        </div>
      </div>

      <div class="card card-hover mg-b-20">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('team.assign_role_title') }}</h6>
        </div>
        <div class="card-body">
          @php
            $canAssignRole = auth()->user()->canAccessModule('team_management', 'edit') && $canManageTarget && !$isLastActiveOwner;
          @endphp
          <form action="{{ route('team.members.role', $memberRow->id) }}" method="POST">
            @csrf
            @method('PATCH')

            @if($memberRow->role === 'owner')
              <div class="alert alert-light border tx-12 mg-b-12">
                {{ __('team.current_owner_note') }}
              </div>
            @endif

            @foreach($assignableRoles as $roleKey)
              @php
                $meta = $rolesMeta[$roleKey];
                $selected = $memberRow->role === $roleKey;
                $roleLabel = __('permissions.roles.' . $roleKey . '.label');
                $roleDesc = __('permissions.roles.' . $roleKey . '.description');
              @endphp
              <label class="stk-role-option {{ $selected ? 'is-selected' : '' }}">
                <div class="d-flex align-items-start" style="gap: 10px;">
                  <input type="radio"
                         name="role"
                         value="{{ $roleKey }}"
                         class="mg-t-4"
                         @checked($selected)
                         @disabled(!$canAssignRole)>
                  <div class="flex-fill">
                    <div class="d-flex align-items-center justify-content-between mg-b-4">
                      @include('partials.role-badge', ['role' => $roleKey, 'label' => $roleLabel])
                    </div>
                    <div class="tx-12 tx-color-03">{{ $roleDesc }}</div>
                  </div>
                </div>
              </label>
            @endforeach

            @error('role')
              <div class="text-danger tx-12 mg-b-10">{{ $message }}</div>
            @enderror

            @if($canAssignRole)
              <button type="submit" class="btn btn-brand-01 btn-block mg-t-8">{{ __('team.save_role_btn') }}</button>
            @endif
          </form>
        </div>
      </div>

      @if((auth()->user()->canAccessModule('team_management', 'edit') || auth()->user()->canAccessModule('team_management', 'delete')) && $canManageTarget && !$isLastActiveOwner)
        <div class="card card-hover">
          <div class="card-header">
            <h6 class="card-title mg-b-0">{{ __('team.membership_status_removal') }}</h6>
          </div>
          <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
            @if(auth()->user()->canAccessModule('team_management', 'edit'))
              <form action="{{ route('team.members.status', $memberRow->id) }}"
                    method="POST"
                    data-confirm-title="{{ $memberRow->is_active ? __('team.confirm_deactivate_title') : __('team.confirm_reactivate_title') }}"
                    data-confirm="{{ $memberRow->is_active ? __('team.confirm_revoke_access_msg', ['name' => $memberRow->name, 'company' => $activeCompany->name]) : __('team.confirm_reactivate_msg', ['name' => $memberRow->name]) }}"
                    data-confirm-btn="{{ $memberRow->is_active ? __('team.deactivate_short') : __('team.activate_short') }}"
                    data-confirm-btn-class="{{ $memberRow->is_active ? 'btn-warning' : 'btn-success' }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="is_active" value="{{ $memberRow->is_active ? 0 : 1 }}">
                <button type="submit" class="btn {{ $memberRow->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                  {{ $memberRow->is_active ? __('team.deactivate_member') : __('team.activate_member') }}
                </button>
              </form>
            @endif

            @if(auth()->user()->canAccessModule('team_management', 'delete'))
              <form action="{{ route('team.members.destroy', $memberRow->id) }}"
                    method="POST"
                    data-confirm-title="{{ __('team.confirm_remove_title') }}"
                    data-confirm="{{ __('team.confirm_remove_permanent_msg', ['name' => $memberRow->name, 'company' => $activeCompany->name]) }}"
                    data-confirm-btn="{{ __('team.confirm_remove_title') }}"
                    data-confirm-btn-class="btn-danger">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">{{ __('team.remove_member') }}</button>
              </form>
            @endif
          </div>
        </div>
      @endif
    </div>

    <div class="col-xl-8">
      <div class="card card-hover">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="card-title mg-b-0">{{ __('team.effective_permissions_title') }}</h6>
            <small class="tx-color-03">{{ __('team.resolved_live_for', ['role' => __('permissions.roles.' . $memberRow->role . '.label'), 'company' => $activeCompany->name]) }}</small>
          </div>
          @include('partials.role-badge', ['role' => $memberRow->role])
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover stk-perm-matrix mg-b-0">
            <thead>
              <tr>
                <th class="px-3">{{ __('team.col_module') }}</th>
                @foreach($actions as $actionKey => $actionLabel)
                  @php $translatedAction = __('permissions.actions.' . $actionKey); @endphp
                  <th class="px-2" title="{{ $translatedAction }}">{{ $actionKey === 'delete' ? __('team.del_short') : ($actionKey === 'approve' ? __('team.approve_short') : $translatedAction) }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach($modules as $moduleKey => $moduleMeta)
                <tr>
                  <td class="px-3">
                    <div class="tx-medium tx-13">{{ __('permissions.modules.' . $moduleKey) }}</div>
                  </td>
                  @foreach($actions as $actionKey => $actionLabel)
                    @php $translatedAction = __('permissions.actions.' . $actionKey); @endphp
                    <td class="text-center px-2">
                      @if(!in_array($actionKey, $moduleMeta['actions'], true))
                        <span class="tx-color-04" title="{{ __('team.not_applicable') }}">&middot;</span>
                      @elseif(!empty($effectivePermissions[$moduleKey][$actionKey]['allowed']))
                        <span class="badge badge-success" title="{{ __('team.action_allowed', ['action' => $translatedAction]) }}">{{ __('app.yes') }}</span>
                      @else
                        <span class="tx-color-04" title="{{ __('team.action_denied', ['action' => $translatedAction]) }}">&mdash;</span>
                      @endif
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
