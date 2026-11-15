<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.select_company_page_title') }} &mdash; Stockora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">

          <div class="d-flex justify-content-end mg-b-12">
            @include('partials.language-switcher')
          </div>

          <div class="text-center mg-b-30">
            <div class="d-inline-flex align-items-center justify-content-center mg-b-14" style="gap: 10px;">
              <div class="stk-brand-mark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <span class="tx-20 tx-bold tx-color-01" style="letter-spacing: -0.025em;">stockora</span>
            </div>
            <h3 class="tx-bold tx-color-01 mg-b-6" style="letter-spacing: -0.025em;">{{ __('auth.select_company_page_title') }}</h3>
            <p class="tx-color-03 tx-13 mb-0">
              {{ __('auth.signed_in_as') }} <strong class="tx-color-01">{{ Auth::user()->name }}</strong> ({{ Auth::user()->email }}) &bull;
              {{ __('auth.select_company_isolation_note') }}
            </p>
          </div>

          @if(session('success'))
            <div class="alert alert-success tx-13">{{ session('success') }}</div>
          @endif
          @if(session('error'))
            <div class="alert alert-danger tx-13">{{ session('error') }}</div>
          @endif

          @if($companies->isEmpty())
            <div class="card card-hover">
              <div class="card-body">
                <div class="stk-empty-state">
                  <div class="stk-empty-icon"><i data-feather="briefcase" class="svg-22"></i></div>
                  <div class="stk-empty-title">{{ __('auth.no_active_companies_title') }}</div>
                  <div class="stk-empty-desc">
                    {{ __('auth.no_active_companies_desc') }}
                  </div>
                  <div class="d-flex flex-wrap justify-content-center" style="gap: 10px;">
                    @if(isset($blockedCompanies) && $blockedCompanies->isNotEmpty())
                      <a href="{{ route('company.access-status') }}" class="btn btn-brand-01 btn-sm">
                        <i data-feather="clock" class="svg-14"></i> {{ __('auth.view_activation_status_btn') }}
                      </a>
                    @else
                      <a href="{{ route('onboarding.company.create') }}" class="btn btn-brand-01 btn-sm">
                        <i data-feather="plus" class="svg-14"></i> {{ __('auth.setup_company_btn') }}
                      </a>
                    @endif
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
          @else
            <div class="row row-sm">
              @foreach($companies as $company)
                @php $isCurrent = $activeCompanyId === (int) $company->id; @endphp
                <div class="col-md-6 mg-b-15">
                  <div class="card stk-tenant-card h-100 {{ $isCurrent ? 'is-active' : '' }}">
                    <div class="card-body d-flex flex-column justify-content-between p-4">
                      <div>
                        <div class="d-flex justify-content-between align-items-center mg-b-12">
                          <span class="stk-tenant-code">{{ $company->code }}</span>
                          <div class="d-flex align-items-center" style="gap: 6px;">
                            @if($company->pivot->is_default)
                              <span class="badge badge-primary">{{ __('auth.badge_default') }}</span>
                            @endif
                            @if($isCurrent)
                              <span class="badge badge-success">{{ __('auth.badge_active') }}</span>
                            @endif
                          </div>
                        </div>
                        <h5 class="tx-16 tx-semibold tx-color-01 mg-b-6">{{ $company->name }}</h5>
                        <p class="tx-12 tx-color-03 mg-b-20">
                          {{ __('auth.workspace_role_label') }} <span class="badge badge-light text-uppercase">{{ __('permissions.roles.' . strtolower($company->pivot->role) . '.label') }}</span>
                        </p>
                      </div>

                      <form action="{{ route('company.switch') }}" method="POST">
                        @csrf
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
                        <button type="submit" class="btn btn-block {{ $isCurrent ? 'btn-brand-01' : 'btn-white' }}">
                          <span>{{ $isCurrent ? __('auth.continue_workspace_btn') : __('auth.enter_workspace_btn') }}</span>
                          <i data-feather="arrow-right" class="svg-14"></i>
                        </button>
                      </form>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center mg-t-20 pt-3 border-top" style="gap: 10px;">
              @if($activeCompanyId > 0)
                <a href="{{ route('dashboard') }}" class="btn btn-white btn-sm">
                  <i data-feather="arrow-left" class="svg-14"></i> {{ __('auth.back_to_dashboard') }}
                </a>
              @else
                <span class="tx-12 tx-color-03">{{ __('auth.choose_tenant_hint') }}</span>
              @endif
              <form action="{{ route('logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-white btn-sm text-danger">
                  <i data-feather="log-out" class="svg-14"></i> {{ __('app.sign_out') }}
                </button>
              </form>
            </div>
          @endif

          @if(isset($blockedCompanies) && $blockedCompanies->isNotEmpty())
            <div class="card mg-t-20">
              <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('auth.blocked_workspaces_title') }}</h6>
                <span class="badge badge-light">{{ \App\Support\StockoraFormat::number($blockedCompanies->count()) }}</span>
              </div>
              <div class="card-body p-0">
                <div class="list-group list-group-flush">
                  @foreach($blockedCompanies as $blocked)
                    <div class="list-group-item d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
                      <div>
                        <span class="stk-tenant-code mg-r-8">{{ $blocked->code }}</span>
                        <strong class="tx-color-01">{{ $blocked->name }}</strong>
                        @if($blocked->status_reason)
                          <div class="tx-12 tx-color-03 mg-t-4">{{ $blocked->status_reason }}</div>
                        @endif
                      </div>
                      <div class="d-flex align-items-center" style="gap: 8px;">
                        @include('partials.company-status-badge', ['status' => $blocked->effectiveStatus()])
                        <a href="{{ route('company.access-status') }}" class="btn btn-xs btn-white">{{ __('auth.status_info_btn') }}</a>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          @endif

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
