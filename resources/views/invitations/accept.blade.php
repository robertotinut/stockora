<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.workspace_invitation_badge') }} &mdash; Stockora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell">
    <div class="container py-5" style="min-height: 100vh; display: flex; align-items: center; justify-content: center;">
      <div class="card stk-auth-card w-100" style="max-width: 520px;">
        <div class="card-body p-4 p-md-5">
          <div class="d-flex justify-content-between align-items-center mg-b-20" style="gap: 10px;">
            <div class="d-flex align-items-center" style="gap: 10px;">
              <div class="stk-brand-mark" style="width: 36px; height: 36px; border-radius: 9px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <div>
                <div class="tx-18 tx-bold tx-color-01" style="line-height: 1.1;">stockora</div>
                <div class="tx-11 tx-color-03">{{ __('auth.workspace_invitation_badge') }}</div>
              </div>
            </div>
            @include('partials.language-switcher')
          </div>

          @if(session('error'))
            <div class="alert alert-danger tx-13">{{ session('error') }}</div>
          @endif

          @if($state !== 'valid')
            @php
              $stateCopy = match ($state) {
                  'accepted' => [__('auth.invite_state_accepted_title'), __('auth.invite_state_accepted_desc'), 'badge-info'],
                  'revoked' => [__('auth.invite_state_revoked_title'), __('auth.invite_state_revoked_desc'), 'badge-danger'],
                  'expired' => [__('auth.invite_state_expired_title'), __('auth.invite_state_expired_desc'), 'badge-warning'],
                  'inactive_company' => [__('auth.invite_state_inactive_title'), __('auth.invite_state_inactive_desc'), 'badge-danger'],
                  default => [__('auth.invite_state_invalid_title'), __('auth.invite_state_invalid_desc'), 'badge-danger'],
              };
            @endphp
            <span class="badge {{ $stateCopy[2] }} mg-b-10">{{ $stateCopy[0] }}</span>
            <h4 class="tx-semibold tx-color-01 mg-b-8">{{ $stateCopy[0] }}</h4>
            <p class="tx-13 tx-color-03 mg-b-20">{{ $stateCopy[1] }}</p>

            <div class="d-flex flex-wrap" style="gap: 8px;">
              @auth
                <a href="{{ route('company.select') }}" class="btn btn-brand-01">{{ __('auth.go_to_workspaces_btn') }}</a>
              @else
                <a href="{{ route('login') }}" class="btn btn-brand-01">{{ __('auth.sign_in_btn') }}</a>
              @endauth
            </div>
          @else
            <span class="badge badge-primary mg-b-10">{{ __('auth.workspace_invitation_badge') }}</span>
            <h4 class="tx-bold tx-color-01 mg-b-6">{{ __('auth.invitation_title', ['company' => $invitation->company->name]) }}</h4>
            <p class="tx-13 tx-color-03 mg-b-18">
              {{ __('auth.invitation_invited_line', ['inviter' => $invitation->invitedBy?->name ?? __('auth.fallback_owner_name'), 'email' => $invitation->email, 'company' => $invitation->company->name, 'code' => $invitation->company->code]) }}
            </p>

            <div class="bg-light rounded p-3 mg-b-18">
              <div class="d-flex justify-content-between align-items-center mg-b-6">
                <span class="tx-12 tx-color-03">{{ __('auth.assigned_role_label') }}</span>
                @include('partials.role-badge', ['role' => $invitation->role, 'label' => __('permissions.roles.' . strtolower($invitation->role) . '.label')])
              </div>
              <div class="d-flex justify-content-between align-items-center mg-b-6">
                <span class="tx-12 tx-color-03">{{ __('auth.role_scope_label') }}</span>
                <span class="tx-12 tx-medium text-right">{{ __('permissions.roles.' . strtolower($invitation->role) . '.description') }}</span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="tx-12 tx-color-03">{{ __('auth.expires_label') }}</span>
                <span class="tx-12 tx-numeric">{{ \App\Support\StockoraFormat::dateTime($invitation->expires_at) }}</span>
              </div>
              @if($invitation->personal_message)
                <hr class="my-2">
                <div class="tx-12 tx-color-02">"{{ $invitation->personal_message }}"</div>
              @endif
            </div>

            @guest
              <div class="alert alert-info tx-12 mg-b-16">
                {{ __('auth.invitation_sign_in_prompt', ['email' => $invitation->email]) }}
              </div>
              <div class="d-flex flex-column" style="gap: 10px;">
                <a href="{{ route('register') }}" class="btn btn-brand-01 btn-block">{{ __('auth.create_account_accept_btn') }}</a>
                <a href="{{ route('login') }}" class="btn btn-white btn-block">{{ __('auth.existing_account_sign_in_btn') }}</a>
              </div>
            @else
              @if($emailMismatch)
                <div class="alert alert-warning tx-13 mg-b-16">
                  {{ __('auth.invitation_mismatch', ['current_email' => auth()->user()->email, 'invited_email' => $invitation->email]) }}
                </div>
                <form action="{{ route('logout') }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-outline-primary btn-block">{{ __('auth.sign_out_switch_btn') }}</button>
                </form>
              @else
                <form action="{{ route('invitations.accept', $token) }}" method="POST">
                  @csrf
                  <button type="submit" class="btn btn-brand-01 btn-block">{{ __('auth.accept_join_btn', ['company' => $invitation->company->name]) }}</button>
                </form>
              @endif
            @endguest
          @endif
        </div>
      </div>
    </div>
  </body>
</html>
