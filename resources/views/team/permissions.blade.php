@extends('layouts.admin')

@section('title', __('team.role_permissions_btn'))
@section('page_title', __('team.permissions_heading'))
@section('page_subtitle', __('team.permissions_page_subtitle', ['company' => $activeCompany->name]))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('team.dashboard_breadcrumb') }}</a></li>
  <li class="breadcrumb-item"><a href="{{ route('team.index') }}">{{ __('team.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('team.role_permissions_btn') }}</li>
@endsection

@section('actions')
  <a href="{{ route('team.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('team.back_to_team') }}
  </a>
@endsection

@section('content')
  @php
    $selectedRoleLabel = __('permissions.roles.' . $selectedRole . '.label');
    $selectedRoleDesc = __('permissions.roles.' . $selectedRole . '.description');
  @endphp

  <div class="card card-hover mg-b-20">
    <div class="card-body py-3">
      <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap: 12px;">
        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
          @foreach($rolesMeta as $roleKey => $meta)
            <a href="{{ route('team.permissions.index', ['role' => $roleKey]) }}"
               class="btn btn-sm {{ $selectedRole === $roleKey ? 'btn-brand-01' : 'btn-white' }}">
              {{ __('permissions.roles.' . $roleKey . '.label') }}
            </a>
          @endforeach
        </div>

        <div class="tx-12 tx-color-03">
          {{ __('team.editing_matrix_for') }} @include('partials.role-badge', ['role' => $selectedRole, 'label' => $selectedRoleLabel])
          &mdash; {{ $selectedRoleDesc }}
        </div>
      </div>
    </div>
  </div>

  @if($selectedRole === 'owner')
    <div class="alert alert-info mg-b-20">
      <strong>{{ __('team.owner_lock_prefix') }}</strong> {{ __('team.owner_lock_body') }}
    </div>
  @elseif(!$canEditSelected)
    <div class="alert alert-warning mg-b-20">
      <strong>{{ __('team.privilege_protection_prefix') }}</strong> {{ __('team.privilege_protection_body', ['role' => strtoupper($selectedRoleLabel)]) }}
    </div>
  @endif

  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
      <div>
        <h6 class="card-title mg-b-0">{{ __('team.role_module_permissions', ['role' => $selectedRoleLabel]) }}</h6>
        <small class="tx-color-03">{{ __('team.changes_apply_note', ['role' => $selectedRoleLabel, 'company' => $activeCompany->name]) }}</small>
      </div>

      @if($canEditSelected)
        <div class="d-flex align-items-center" style="gap: 8px;">
          <button type="button" class="btn btn-xs btn-white" id="stkPermSelectAll">{{ __('team.check_all') }}</button>
          <button type="button" class="btn btn-xs btn-white" id="stkPermViewOnly">{{ __('team.view_only') }}</button>
          <button type="button" class="btn btn-xs btn-white" id="stkPermClearAll">{{ __('team.clear_all') }}</button>
          <form action="{{ route('team.permissions.reset', $selectedRole) }}"
                method="POST"
                class="d-inline"
                data-confirm-title="{{ __('team.reset_role_defaults') }}"
                data-confirm="{{ __('team.confirm_reset_defaults_msg', ['role' => $selectedRoleLabel, 'company' => $activeCompany->name]) }}"
                data-confirm-btn="{{ __('team.reset_role_defaults') }}"
                data-confirm-btn-class="btn-warning">
            @csrf
            <button type="submit" class="btn btn-xs btn-outline-warning">{{ __('team.reset_role_defaults') }}</button>
          </form>
        </div>
      @endif
    </div>

    <form action="{{ route('team.permissions.update', $selectedRole) }}" method="POST">
      @csrf
      @method('PUT')

      <div class="table-responsive">
        <table class="table table-sm table-hover stk-perm-matrix mg-b-0">
          <thead>
            <tr>
              <th class="px-3" style="min-width: 190px;">{{ __('team.col_module') }}</th>
              @foreach($actions as $actionKey => $actionLabel)
                @php $translatedAction = __('permissions.actions.' . $actionKey); @endphp
                <th class="px-2" title="{{ $translatedAction }}">{{ $actionKey === 'approve' ? __('team.approve_post_short') : ($actionKey === 'manage' ? __('team.manage_short') : $translatedAction) }}</th>
              @endforeach
            </tr>
          </thead>
          <tbody>
            @foreach($modules as $moduleKey => $moduleMeta)
              @php
                $moduleLabel = __('permissions.modules.' . $moduleKey);
                $groupKey = \Illuminate\Support\Str::slug((string) ($moduleMeta['group'] ?? 'overview'), '_');
                $groupLabel = __('team.groups.' . $groupKey);
              @endphp
              <tr>
                <td class="px-3">
                  <div class="tx-medium tx-color-01">{{ $moduleLabel }}</div>
                  <div class="tx-11 tx-color-03">{{ $groupLabel }}</div>
                </td>
                @foreach($actions as $actionKey => $actionLabel)
                  @php $translatedAction = __('permissions.actions.' . $actionKey); @endphp
                  <td class="text-center px-2">
                    @if(in_array($actionKey, $moduleMeta['actions'], true))
                      @php $cell = $matrix[$selectedRole][$moduleKey][$actionKey] ?? ['allowed' => false, 'overridden' => false]; @endphp
                      <div class="d-inline-flex flex-column align-items-center">
                        <input type="checkbox"
                               class="stk-perm-check"
                               data-action="{{ $actionKey }}"
                               name="permissions[{{ $moduleKey }}][{{ $actionKey }}]"
                               value="1"
                               aria-label="{{ $moduleLabel }} - {{ $translatedAction }}"
                               @checked(!empty($cell['allowed']))
                               @disabled(!$canEditSelected)>
                        @if(!empty($cell['overridden']))
                          <span class="badge badge-info mt-1" style="font-size: 9px; padding: 1px 4px;">{{ __('team.custom_badge') }}</span>
                        @endif
                      </div>
                    @else
                      <span class="tx-color-04" title="{{ __('team.not_applicable') }} ({{ $moduleLabel }})">&middot;</span>
                    @endif
                  </td>
                @endforeach
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($canEditSelected)
        <div class="card-footer d-flex justify-content-between align-items-center">
          <span class="tx-12 tx-color-03">{{ __('team.server_enforced_note') }}</span>
          <button type="submit" class="btn btn-brand-01">
            <i data-feather="save" class="svg-14"></i> {{ __('team.save_role_permissions', ['role' => $selectedRoleLabel]) }}
          </button>
        </div>
      @endif
    </form>
  </div>
@endsection

@push('scripts')
<script>
  $(function () {
    $('#stkPermSelectAll').on('click', function () {
      $('.stk-perm-check:not(:disabled)').prop('checked', true);
    });
    $('#stkPermClearAll').on('click', function () {
      $('.stk-perm-check:not(:disabled)').prop('checked', false);
    });
    $('#stkPermViewOnly').on('click', function () {
      $('.stk-perm-check:not(:disabled)').each(function () {
        $(this).prop('checked', $(this).data('action') === 'view');
      });
    });
  });
</script>
@endpush
