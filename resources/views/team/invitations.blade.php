@extends('layouts.admin')

@section('title', __('team.invitations_title'))
@section('page_title', __('team.invitations_title'))
@section('page_subtitle', __('team.invitations_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('team.dashboard_breadcrumb') }}</a></li>
  <li class="breadcrumb-item"><a href="{{ route('team.index') }}">{{ __('team.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('team.invitations_breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('team.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('team.back_to_members') }}
  </a>
  @if(auth()->user()->canAccessModule('team_management', 'manage') && ($planFeatures['module_role_permissions']['enabled'] ?? false))
    <a href="{{ route('team.permissions.index') }}" class="btn btn-white">
      <i data-feather="shield" class="svg-14"></i> {{ __('team.role_permissions_btn') }}
    </a>
  @endif
@endsection

@section('content')
  @include('partials.quota-banner', ['limitKey' => 'max_users'])

  @if(session('sandbox_invitation_preview'))
    @php $preview = session('sandbox_invitation_preview'); @endphp
    <div class="stk-invite-preview mg-b-20">
      <div class="d-flex flex-wrap justify-content-between align-items-center mg-b-8" style="gap: 8px;">
        <div class="d-flex align-items-center" style="gap: 8px;">
          <i data-feather="link" class="svg-16 text-primary"></i>
          <strong class="tx-color-01">{{ __('team.sandbox_preview_title') }}</strong>
          @include('partials.role-badge', ['role' => $preview['role']])
        </div>
        <span class="tx-12 tx-color-03">{{ __('team.invite_share_note', ['email' => $preview['email']]) }}</span>
      </div>
      <div class="input-group input-group-sm">
        <input type="text" id="stkSandboxInviteUrl" class="form-control tx-13" value="{{ $preview['url'] }}" readonly>
        <div class="input-group-append">
          <button type="button" class="btn btn-primary" id="stkCopyInviteBtn" data-copy-target="stkSandboxInviteUrl">
            <i data-feather="copy" class="svg-12"></i> {{ __('team.copy_link_btn') }}
          </button>
          <a href="{{ $preview['url'] }}" target="_blank" rel="noopener" class="btn btn-white">{{ __('team.open_btn') }}</a>
        </div>
      </div>
    </div>
  @endif

  <div class="row row-sm">
    @if(auth()->user()->canAccessModule('team_management', 'create'))
      <div class="col-12 mg-b-20">
        <div class="card card-hover">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
            <div>
              <h6 class="card-title mg-b-0">{{ __('team.invite_colleague') }}</h6>
              <small class="tx-color-03">{{ __('team.invite_expiry_note', ['company' => $activeCompany->name]) }}</small>
            </div>
            @if(!$hasRealMailTransport)
              <span class="badge badge-info">{{ __('team.sandbox_mail_badge', ['driver' => config('mail.default')]) }}</span>
            @endif
          </div>
          <div class="card-body">
            @if(!$hasRealMailTransport)
              <div class="alert alert-info tx-12 mg-b-16">
                <strong>{{ __('team.sandbox_mail_notice_prefix') }}</strong> {{ __('team.sandbox_mail_notice_body', ['driver' => config('mail.default')]) }}
              </div>
            @endif

            <form action="{{ route('team.invitations.store') }}" method="POST">
              @csrf
              <div class="row row-xs align-items-end">
                <div class="col-md-4 mg-b-12 mg-md-b-0">
                  <label class="tx-semibold" for="invite_email">{{ __('team.work_email') }} <span class="text-danger">*</span></label>
                  <input type="email" name="email" id="invite_email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('team.email_placeholder') }}" required>
                  @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-2 mg-b-12 mg-md-b-0">
                  <label class="tx-semibold" for="invite_role">{{ __('team.role_label') }} <span class="text-danger">*</span></label>
                  <select name="role" id="invite_role" class="custom-select @error('role') is-invalid @enderror" required>
                    @foreach($assignableRoles as $roleKey)
                      <option value="{{ $roleKey }}" @selected(old('role', 'staff') === $roleKey)>
                        {{ __('permissions.roles.' . $roleKey . '.label') }}
                      </option>
                    @endforeach
                  </select>
                  @error('role')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-4 mg-b-12 mg-md-b-0">
                  <label class="tx-semibold" for="personal_message">{{ __('team.welcome_note') }} <span class="tx-color-03 font-weight-normal">({{ __('app.optional') }})</span></label>
                  <input type="text" name="personal_message" id="personal_message" value="{{ old('personal_message') }}" class="form-control @error('personal_message') is-invalid @enderror" placeholder="{{ __('team.welcome_note_placeholder') }}">
                  @error('personal_message')
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>

                <div class="col-md-2">
                  <button type="submit" class="btn btn-brand-01 btn-block">
                    <i data-feather="send" class="svg-14"></i> {{ __('team.invite_btn') }}
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    @endif

    <div class="col-12">
      <div class="card card-hover">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
          <div class="d-flex align-items-center" style="gap: 8px;">
            <h6 class="card-title mg-b-0">{{ __('team.invitation_history_title') }}</h6>
            <span class="badge badge-light tx-numeric">{{ $rows->total() }}</span>
          </div>

          <form action="{{ route('team.invitations.index') }}" method="GET" class="d-flex align-items-center" style="gap: 8px;">
            <select name="status" class="custom-select custom-select-sm" style="width: auto;" onchange="this.form.submit()">
              <option value="">{{ __('team.all_statuses') }}</option>
              <option value="pending" @selected($statusFilter === 'pending')>{{ __('team.invite_statuses.pending') }}</option>
              <option value="accepted" @selected($statusFilter === 'accepted')>{{ __('team.invite_statuses.accepted') }}</option>
              <option value="expired" @selected($statusFilter === 'expired')>{{ __('team.invite_statuses.expired') }}</option>
              <option value="revoked" @selected($statusFilter === 'revoked')>{{ __('team.invite_statuses.revoked') }}</option>
            </select>
            @if($statusFilter !== '')
              <a href="{{ route('team.invitations.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
            @endif
          </form>
        </div>

        @if($rows->isEmpty())
          <div class="card-body">
            @include('partials.empty-state', [
              'title' => __('team.no_invitations_title'),
              'description' => __('team.no_invitations_desc'),
              'icon' => 'mail',
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('team.col_invitee') }}</th>
                  <th>{{ __('team.role_label') }}</th>
                  <th class="text-center">{{ __('app.status') }}</th>
                  <th>{{ __('team.col_timing') }}</th>
                  <th class="text-right">{{ __('app.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($rows as $invite)
                  @php $effectiveStatus = $invite->effectiveStatus(); @endphp
                  <tr>
                    <td>
                      <div class="tx-medium tx-color-01">{{ $invite->email }}</div>
                      <div class="tx-12 tx-color-03">
                        {{ __('team.invited_by_on', [
                          'name' => $invite->invitedBy?->name ?? __('app.system'),
                          'date' => $invite->created_at ? \App\Support\StockoraFormat::shortDate($invite->created_at) : '—',
                        ]) }}
                      </div>
                    </td>
                    <td>
                      @include('partials.role-badge', ['role' => $invite->role])
                    </td>
                    <td class="text-center">
                      @include('partials.status-badge', ['status' => strtoupper($effectiveStatus), 'label' => __('team.invite_statuses.' . $effectiveStatus)])
                    </td>
                    <td class="tx-12 tx-color-03 text-nowrap">
                      @if($effectiveStatus === 'accepted')
                        {{ __('team.accepted_on', ['date' => $invite->accepted_at ? \App\Support\StockoraFormat::dateTime($invite->accepted_at) : '—']) }}
                      @elseif($effectiveStatus === 'revoked')
                        {{ __('team.revoked_on', ['date' => $invite->revoked_at ? \App\Support\StockoraFormat::dateTime($invite->revoked_at) : '—']) }}
                      @elseif($invite->expires_at)
                        {{ __('team.expires_on', ['date' => \App\Support\StockoraFormat::dateTime($invite->expires_at)]) }}
                      @else
                        &mdash;
                      @endif
                    </td>
                    <td class="text-right text-nowrap">
                      @if($invite->status === 'pending')
                        @if(auth()->user()->canAccessModule('team_management', 'create'))
                          <form action="{{ route('team.invitations.resend', $invite) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-white">{{ __('team.resend_btn') }}</button>
                          </form>
                        @endif
                        @if(auth()->user()->canAccessModule('team_management', 'delete'))
                          <form action="{{ route('team.invitations.destroy', $invite) }}"
                                method="POST"
                                class="d-inline mg-l-4"
                                data-confirm-title="{{ __('team.confirm_revoke_invite_title') }}"
                                data-confirm="{{ __('team.confirm_revoke_invite_msg', ['email' => $invite->email]) }}"
                                data-confirm-btn="{{ __('team.revoke_invite_btn') }}"
                                data-confirm-btn-class="btn-danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('team.revoke_btn') }}</button>
                          </form>
                        @endif
                      @else
                        <span class="tx-12 tx-color-04">{{ __('team.closed_status') }}</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        @if($rows->hasPages())
          <div class="card-footer">{{ $rows->links() }}</div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  $(function () {
    $('#stkCopyInviteBtn').on('click', function () {
      var input = document.getElementById('stkSandboxInviteUrl');
      if (!input) return;
      input.select();
      input.setSelectionRange(0, 99999);
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value);
      } else {
        document.execCommand('copy');
      }
      $(this).text(@json(__('team.copied_btn')));
    });
  });
</script>
@endpush
