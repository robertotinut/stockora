<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.access_status_page_title') }} &mdash; Stockora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell">
    <div class="container py-4 py-md-5">
      <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">

          <div class="d-flex flex-wrap justify-content-between align-items-center mg-b-24" style="gap: 12px;">
            <div class="d-inline-flex align-items-center" style="gap: 10px;">
              <div class="stk-brand-mark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <div>
                <div class="tx-18 tx-bold tx-color-01" style="letter-spacing: -0.025em; line-height: 1.1;">stockora</div>
                <div class="tx-11 tx-color-03">{{ __('auth.tagline') }}</div>
              </div>
            </div>

            <div class="d-flex align-items-center flex-wrap" style="gap: 10px;">
              @include('partials.language-switcher')
              <span class="tx-12 tx-color-03">{{ __('auth.signed_in_as') }} <strong class="tx-color-01">{{ Auth::user()->name }}</strong></span>
              <form action="{{ route('logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-white btn-xs text-danger">
                  <i data-feather="log-out" class="svg-13"></i> {{ __('app.logout') }}
                </button>
              </form>
            </div>
          </div>

          @if(session('warning'))
            <div class="alert alert-warning tx-13">{{ session('warning') }}</div>
          @endif
          @if(session('info'))
            <div class="alert alert-info tx-13">{{ session('info') }}</div>
          @endif

          <div class="card card-hover">
            <div class="card-body p-4 p-md-5">
              @php
                $isPending = $effectiveStatus === \App\Models\Company::STATUS_PENDING_ACTIVATION;
                $isSuspended = $effectiveStatus === \App\Models\Company::STATUS_SUSPENDED;
                $isExpired = $effectiveStatus === \App\Models\Company::STATUS_EXPIRED;
                $iconName = $isPending ? 'clock' : ($isSuspended ? 'pause-circle' : 'alert-octagon');
                $accentBg = $isPending ? '#fef3c7' : ($isSuspended ? '#fee2e2' : '#f1f5f9');
                $accentColor = $isPending ? '#b45309' : ($isSuspended ? '#b91c1c' : '#475569');
              @endphp

              <div class="text-center mg-b-28">
                <div class="mx-auto mg-b-16" style="width: 60px; height: 60px; border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; background-color: {{ $accentBg }}; color: {{ $accentColor }};">
                  <i data-feather="{{ $iconName }}" class="svg-26"></i>
                </div>
                <div class="mg-b-10">
                  @include('partials.company-status-badge', ['status' => $effectiveStatus])
                </div>
                <h2 class="tx-bold tx-color-01 mg-b-8" style="letter-spacing: -0.03em;">
                  @if($isPending)
                    {{ __('auth.pending_activation_heading') }}
                  @elseif($isSuspended)
                    {{ __('auth.suspended_heading') }}
                  @elseif($isExpired)
                    {{ __('auth.expired_heading') }}
                  @else
                    {{ __('auth.cancelled_heading') }}
                  @endif
                </h2>
                <p class="tx-14 tx-color-03 mx-auto mb-0" style="max-width: 560px;">
                  @if($isPending)
                    {{ __('auth.access_pending_body_detail', ['company' => $company->name]) }}
                  @elseif($isSuspended)
                    {{ __('auth.access_suspended_body_detail', ['company' => $company->name]) }}
                  @elseif($isExpired)
                    {{ __('auth.access_expired_body_detail', ['company' => $company->name]) }}
                  @else
                    {{ __('auth.access_closed_body_detail', ['company' => $company->name]) }}
                  @endif
                </p>
              </div>

              <div class="p-3 p-md-4 mg-b-24" style="background-color: var(--stk-bg-canvas); border: 1px solid var(--stk-border); border-radius: 12px;">
                <div class="d-flex flex-wrap justify-content-between align-items-center mg-b-12" style="gap: 10px;">
                  <div>
                    <div class="tx-11 text-uppercase tx-color-03 tx-semibold">{{ __('auth.registered_workspace_label') }}</div>
                    <div class="d-flex flex-wrap align-items-center mg-t-4" style="gap: 8px;">
                      <h5 class="tx-bold tx-color-01 mb-0">{{ $company->name }}</h5>
                      <span class="stk-tenant-code">{{ $company->code }}</span>
                    </div>
                  </div>
                  <div class="text-md-right">
                    <div class="tx-11 text-uppercase tx-color-03 tx-semibold">{{ __('auth.status_details.registered_at') }}</div>
                    <div class="tx-13 tx-color-01 mg-t-2">{{ \App\Support\StockoraFormat::dateTime($company->created_at) }}</div>
                  </div>
                </div>

                @if($company->status_reason && !$isPending)
                  <div class="tx-13 tx-color-02 pt-3 border-top">
                    <strong>{{ __('auth.status_note_label') }}</strong> {{ $company->status_reason }}
                  </div>
                @endif

                @if($company->access_expires_at)
                  <div class="tx-12 tx-color-03 mg-t-8">
                    {{ __('auth.status_details.expires_at') }}: <strong class="tx-color-01">{{ \App\Support\StockoraFormat::dateTime($company->access_expires_at) }}</strong>
                  </div>
                @endif
                @if(isset($resolvedPlan) && $resolvedPlan)
                  <div class="tx-12 tx-color-03 mg-t-6">
                    {{ __('payments.customer.current_plan') }}: <strong class="tx-color-01">{{ $resolvedPlan->name }} ({{ $resolvedPlan->formattedPrice() }})</strong>
                  </div>
                @endif
              </div>

              @if(!empty($latestPayment))
                <div class="alert {{ $latestPayment->isRejected() ? 'alert-danger' : ($latestPayment->isVerified() ? 'alert-success' : 'alert-info') }} mg-b-24">
                  <div class="d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
                    <div>
                      <strong class="tx-13">{{ $latestPayment->payment_reference }}</strong>
                      <span class="badge {{ $latestPayment->statusBadgeClass() }} ml-1">{{ $latestPayment->statusLabel() }}</span>
                      <div class="tx-12 mg-t-4">
                        {{ $latestPayment->plan_name_snapshot }} &bull; {{ $latestPayment->formattedAmount() }}
                        @if($latestPayment->isRejected() && $latestPayment->rejection_reason)
                          &mdash; <strong>{{ $latestPayment->rejection_reason }}</strong>
                        @endif
                      </div>
                    </div>
                    <a href="{{ route('subscription.payments.show', $latestPayment->id) }}" class="btn btn-xs btn-white">
                      {{ __('payments.customer.view_payment_btn') }}
                    </a>
                  </div>
                </div>
              @endif

              @if($isPending)
                <div class="alert alert-light border mg-b-24">
                  <div class="tx-13 tx-semibold tx-color-01 mg-b-6">{{ __('auth.pending_next_steps_title') }}</div>
                  <ul class="tx-12 tx-color-03 mb-0 pd-l-18">
                    @foreach((array) __('auth.pending_next_steps') as $stepLine)
                      <li>{{ $stepLine }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              <div class="p-3 p-md-4 mg-b-24" style="border: 1px solid var(--stk-border); border-radius: 12px;">
                <div class="tx-12 text-uppercase tx-semibold tx-color-03 mg-b-8">{{ __('auth.support_card_title') }}</div>
                <p class="tx-13 tx-color-02 mg-b-12">{{ $support['sla_message'] ?? __('auth.support_default_sla') }}</p>
                <div class="row row-sm tx-12">
                  <div class="col-md-4 mg-b-8 mg-md-b-0">
                    <div class="tx-color-03">{{ __('auth.support_email_label') }}</div>
                    <div class="tx-semibold tx-color-01">{{ $support['email'] ?? 'onboarding@stockora.app' }}</div>
                  </div>
                  <div class="col-md-4 mg-b-8 mg-md-b-0">
                    <div class="tx-color-03">{{ __('auth.support_phone_label') }}</div>
                    <div class="tx-semibold tx-color-01">{{ $support['phone'] ?? '+62 21 5099 8800' }}</div>
                  </div>
                  <div class="col-md-4">
                    <div class="tx-color-03">{{ __('auth.support_hours_label') }}</div>
                    <div class="tx-semibold tx-color-01">{{ $support['hours'] ?? 'Mon - Fri, 09:00 - 18:00 WIB' }}</div>
                  </div>
                </div>
              </div>

              @if($activeWorkspaces->isNotEmpty())
                <div class="alert alert-info mg-b-24">
                  <div class="tx-13 tx-semibold mg-b-6">{{ __('auth.active_workspaces_available') }}</div>
                  <div class="d-flex flex-wrap" style="gap: 8px;">
                    @foreach($activeWorkspaces as $activeCo)
                      <form action="{{ route('company.switch') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $activeCo->id }}">
                        <button type="submit" class="btn btn-brand-01 btn-sm">
                          {{ __('auth.switch_to_btn', ['name' => $activeCo->name, 'code' => $activeCo->code]) }}
                        </button>
                      </form>
                    @endforeach
                  </div>
                </div>
              @endif

              <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top" style="gap: 10px;">
                <div class="d-flex flex-wrap" style="gap: 8px;">
                  <a href="{{ route('company.access-status') }}" class="btn btn-brand-01 btn-sm">
                    <i data-feather="refresh-cw" class="svg-14"></i> {{ __('auth.recheck_activation_btn') }}
                  </a>
                  @if(!empty($canManageBilling) && in_array($effectiveStatus, [\App\Models\Company::STATUS_PENDING_ACTIVATION, \App\Models\Company::STATUS_EXPIRED], true))
                    <a href="{{ route('subscription.index') }}" class="btn btn-white btn-sm">
                      <i data-feather="credit-card" class="svg-14"></i> {{ __('payments.reminders.cta_manage') }}
                    </a>
                    <a href="{{ route('subscription.payments.create') }}" class="btn btn-outline-primary btn-sm">
                      <i data-feather="upload" class="svg-14"></i> {{ __('payments.reminders.cta_submit') }}
                    </a>
                  @endif
                </div>

                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                  @csrf
                  <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i data-feather="log-out" class="svg-14"></i> {{ __('app.sign_out') }}
                  </button>
                </form>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <script src="{{ asset('lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('lib/feather-icons/feather.min.js') }}"></script>
    <script>
      $(function () {
        if (window.feather) {
          feather.replace();
        }
      });
    </script>
  </body>
</html>
