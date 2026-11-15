<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.onboarding_ready_page_title') }} &mdash; Stockora</title>

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

          <div class="d-flex flex-wrap justify-content-between align-items-center mg-b-25" style="gap: 12px;">
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

            <div class="d-flex align-items-center" style="gap: 10px;">
              @include('partials.language-switcher')
              <div class="tx-12 tx-color-03">
                {{ __('auth.signed_in_as') }} <strong class="tx-color-01">{{ Auth::user()->name }}</strong>
              </div>
            </div>
          </div>

          <div class="card card-hover">
            <div class="card-body p-4 p-md-5">

              <div class="mg-b-28" style="max-width: 440px;">
                @include('onboarding._stepper', ['step' => 3])
              </div>

              @php
                $isActiveNow = $company->isOperationallyActive();
              @endphp

              <div class="text-center mg-b-28">
                <div class="stk-ready-check mx-auto mg-b-16" style="{{ $isActiveNow ? '' : 'background-color: #fef3c7; color: #b45309;' }}">
                  <i data-feather="{{ $isActiveNow ? 'check-circle' : 'clock' }}" class="svg-26"></i>
                </div>
                <div class="mg-b-10">
                  @include('partials.company-status-badge', ['status' => $company->effectiveStatus()])
                </div>
                <h2 class="tx-bold tx-color-01 mg-b-8" style="letter-spacing: -0.03em;">
                  {{ $isActiveNow ? __('auth.ready_active_heading') : __('auth.ready_pending_heading') }}
                </h2>
                <p class="tx-14 tx-color-03 mx-auto mb-0" style="max-width: 540px;">
                  @if($isActiveNow)
                    {{ __('auth.ready_active_body', ['company' => $company->name]) }}
                  @else
                    {{ __('auth.ready_pending_body', ['company' => $company->name]) }}
                  @endif
                </p>
              </div>

              {{-- Provisioned Company Card --}}
              <div class="p-3 p-md-4 mg-b-24" style="background-color: var(--stk-bg-canvas); border: 1px solid var(--stk-border); border-radius: 12px;">
                <div class="d-flex flex-wrap align-items-center justify-content-between mg-b-16" style="gap: 14px;">
                  <div class="d-flex align-items-center" style="gap: 14px;">
                    <div class="stk-logo-preview" style="width: 56px; height: 56px;">
                      @if($company->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="{{ $company->name }} logo">
                      @else
                        <span>{{ $company->initials() }}</span>
                      @endif
                    </div>
                    <div>
                      <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                        <h5 class="tx-bold tx-color-01 mg-b-0">{{ $company->name }}</h5>
                        <span class="stk-tenant-code">{{ $company->code }}</span>
                      </div>
                      <div class="tx-12 tx-color-03 mg-t-4">
                        @if($company->industry)
                          <span>{{ $company->industry }}</span>
                        @endif
                        @if($company->industry && $company->phone)
                          <span class="mx-1">&bull;</span>
                        @endif
                        @if($company->phone)
                          <span>{{ $company->phone }}</span>
                        @endif
                      </div>
                    </div>
                  </div>
                  <div class="d-flex align-items-center" style="gap: 6px;">
                    <span class="badge badge-primary text-uppercase">{{ __('permissions.roles.owner.label') }}</span>
                    @include('partials.company-status-badge', ['status' => $company->effectiveStatus()])
                  </div>
                </div>

                @if($company->address)
                  <div class="tx-12 tx-color-02 pd-t-12 border-top">
                    <i data-feather="map-pin" class="svg-13 tx-color-04 mg-r-4"></i> {{ $company->address }}
                  </div>
                @endif
              </div>

              {{-- Readiness Checklist --}}
              <div class="row row-sm mg-b-28">
                <div class="col-md-4 mg-b-10 mg-md-b-0">
                  <div class="p-3 h-100" style="border: 1px solid var(--stk-border); border-radius: 10px;">
                    <div class="tx-12 tx-semibold tx-color-01 mg-b-4">
                      <i data-feather="home" class="svg-14 text-primary mg-r-4"></i> {{ __('auth.checklist_warehouse_title') }}
                    </div>
                    <div class="tx-12 tx-color-03">{{ __('auth.checklist_warehouse_desc', ['code' => 'WH-MAIN']) }}</div>
                  </div>
                </div>
                <div class="col-md-4 mg-b-10 mg-md-b-0">
                  <div class="p-3 h-100" style="border: 1px solid var(--stk-border); border-radius: 10px;">
                    <div class="tx-12 tx-semibold tx-color-01 mg-b-4">
                      <i data-feather="user-check" class="svg-14 text-success mg-r-4"></i> {{ __('auth.checklist_owner_title') }}
                    </div>
                    <div class="tx-12 tx-color-03">{{ __('auth.checklist_owner_desc') }}</div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="p-3 h-100" style="border: 1px solid var(--stk-border); border-radius: 10px;">
                    <div class="tx-12 tx-semibold tx-color-01 mg-b-4">
                      <i data-feather="shield" class="svg-14 text-info mg-r-4"></i> {{ __('auth.checklist_isolation_title') }}
                    </div>
                    <div class="tx-12 tx-color-03">{{ __('auth.checklist_isolation_desc') }}</div>
                  </div>
                </div>
              </div>

              <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top" style="gap: 12px;">
                @if($isActiveNow)
                  <div class="d-flex flex-wrap" style="gap: 8px;">
                    @if(Route::has('products.create'))
                      <a href="{{ route('products.create') }}" class="btn btn-white btn-sm">
                        <i data-feather="package" class="svg-14"></i> {{ __('auth.add_first_product') }}
                      </a>
                    @endif
                    @if(Route::has('warehouses.index'))
                      <a href="{{ route('warehouses.index') }}" class="btn btn-white btn-sm">
                        <i data-feather="home" class="svg-14"></i> {{ __('auth.manage_warehouses') }}
                      </a>
                    @endif
                  </div>

                  <a href="{{ route('dashboard') }}" id="stkGoDashboard" class="btn btn-brand-01 px-4 py-2">
                    {{ __('auth.continue_dashboard') }} <i data-feather="arrow-right" class="svg-14 mg-l-4"></i>
                  </a>
                @else
                  <div class="tx-12 tx-color-03">
                    {{ __('app.support_contact') }}: <strong class="tx-color-01">{{ $support['email'] ?? 'onboarding@stockora.app' }}</strong>
                    &bull; {{ $support['phone'] ?? '+62 21 5099 8800' }}
                  </div>

                  <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <a href="{{ route('company.access-status') }}" id="stkCheckStatusBtn" class="btn btn-brand-01 btn-sm">
                      <i data-feather="clock" class="svg-14"></i> {{ __('auth.check_activation_status') }}
                    </a>
                    <form action="{{ route('logout') }}" method="POST" class="d-inline">
                      @csrf
                      <button type="submit" class="btn btn-white btn-sm text-danger">
                        <i data-feather="log-out" class="svg-14"></i> {{ __('app.logout') }}
                      </button>
                    </form>
                  </div>
                @endif
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
