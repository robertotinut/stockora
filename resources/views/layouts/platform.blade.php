<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>@yield('title', __('navigation.platform_overview')) &mdash; Stockora Control Plane</title>

    <link href="{{ asset('lib/@fortawesome/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('lib/ionicons/css/ionicons.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
    @stack('styles')
  </head>
  <body>

    <div class="stk-sidebar-backdrop" id="stkSidebarBackdrop"></div>

    @php
      $platformAdmin = Auth::guard('platform')->user();
      $pendingActivationNavCount = \App\Models\Company::where('account_status', \App\Models\Company::STATUS_PENDING_ACTIVATION)->count();
      $pendingPaymentsNavCount = \App\Models\SubscriptionPayment::where('status', \App\Models\SubscriptionPayment::STATUS_PENDING)->count();
    @endphp

    <aside class="sidebar stk-platform-sidebar">
      <div class="sidebar-header">
        <a href="{{ route('platform.dashboard') }}" class="stk-brand-lockup">
          <span class="stk-brand-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </span>
          <span>
            <span class="stk-brand-wordmark d-block">stockora</span>
            <small class="stk-brand-tagline d-block">{{ __('navigation.platform_tagline') }}</small>
          </span>
        </a>
      </div>

      <div id="dpSidebarBody" class="sidebar-body">
        <ul class="nav nav-sidebar">
          <li class="nav-label"><label class="content-label">{{ __('navigation.platform_control_plane') }}</label></li>
          <li class="nav-item">
            <a href="{{ route('platform.dashboard') }}" class="nav-link {{ request()->routeIs('platform.dashboard') ? 'active' : '' }}">
              <i data-feather="grid"></i> {{ __('navigation.platform_overview') }}
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('platform.customers.index') }}" class="nav-link {{ request()->routeIs('platform.customers.*') ? 'active' : '' }}">
              <i data-feather="briefcase"></i> {{ __('navigation.platform_customers') }}
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('platform.pending.index') }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('platform.pending.*') ? 'active' : '' }}">
              <span><i data-feather="clock"></i> {{ __('navigation.platform_pending') }}</span>
              @if($pendingActivationNavCount > 0)
                <span class="badge badge-warning">{{ $pendingActivationNavCount }}</span>
              @endif
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('platform.access.index') }}" class="nav-link {{ request()->routeIs('platform.access.*') ? 'active' : '' }}">
              <i data-feather="key"></i> {{ __('navigation.platform_access') }}
            </a>
          </li>

          <li class="nav-label"><label class="content-label">{{ __('navigation.platform_plans_catalog') }}</label></li>
          <li class="nav-item">
            <a href="{{ route('platform.payments.index') }}" class="nav-link d-flex justify-content-between align-items-center {{ request()->routeIs('platform.payments.*') ? 'active' : '' }}">
              <span><i data-feather="credit-card"></i> {{ __('navigation.platform_payments') }}</span>
              @if($pendingPaymentsNavCount > 0)
                <span class="badge badge-warning">{{ $pendingPaymentsNavCount }}</span>
              @endif
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('platform.plans.index') }}" class="nav-link {{ request()->routeIs('platform.plans.*') ? 'active' : '' }}">
              <i data-feather="layers"></i> {{ __('navigation.platform_plans') }}
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('platform.features.index') }}" class="nav-link {{ request()->routeIs('platform.features.*') ? 'active' : '' }}">
              <i data-feather="sliders"></i> {{ __('navigation.platform_features') }}
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('plans.index') }}" class="nav-link {{ request()->routeIs('plans.index') ? 'active' : '' }}">
              <i data-feather="columns"></i> {{ __('navigation.platform_comparison') }}
            </a>
          </li>

          <li class="nav-label"><label class="content-label">{{ __('navigation.platform_governance') }}</label></li>
          <li class="nav-item">
            <a href="{{ route('platform.logs.index') }}" class="nav-link {{ request()->routeIs('platform.logs.*') ? 'active' : '' }}">
              <i data-feather="activity"></i> {{ __('navigation.platform_logs') }}
            </a>
          </li>
          <li class="nav-item">
            <a href="{{ route('platform.settings.index') }}" class="nav-link {{ request()->routeIs('platform.settings.*') ? 'active' : '' }}">
              <i data-feather="sliders"></i> {{ __('navigation.platform_settings') }}
            </a>
          </li>

          <li class="nav-label"><label class="content-label">{{ __('navigation.platform_session') }}</label></li>
          <li class="nav-item">
            <form action="{{ route('platform.logout') }}" method="POST">
              @csrf
              <button type="submit" class="nav-link btn btn-link text-left w-100 border-0" style="color: #fca5a5 !important;">
                <i data-feather="log-out"></i> {{ __('app.logout') }}
              </button>
            </form>
          </li>
        </ul>
      </div>
    </aside>

    <div class="content content-page">
      <header class="header">
        <div class="header-left">
          <a href="#" class="burger-menu" id="stkBurgerBtn" aria-label="{{ __('navigation.toggle_navigation') }}"><i data-feather="menu"></i></a>
          <form action="{{ route('platform.customers.index') }}" method="GET" class="header-search">
            <i data-feather="search"></i>
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('navigation.platform_search_placeholder') }}">
          </form>
        </div>

        <div class="header-right">
          @include('partials.language-switcher', ['route' => 'platform.locale.update'])

          <span class="stk-platform-badge d-none d-md-inline-flex mg-r-10">
            <i data-feather="shield" class="svg-12"></i> {{ __('navigation.platform_badge') }}
          </span>

          <a href="{{ route('platform.customers.create') }}" class="btn btn-brand-01 btn-xs d-none d-sm-inline-flex align-items-center mg-r-10">
            <i data-feather="plus" class="svg-13 mg-r-4"></i> {{ __('navigation.platform_new_customer') }}
          </a>

          <div class="dropdown dropdown-loggeduser">
            <a href="#" class="dropdown-link" data-toggle="dropdown" data-display="static" aria-label="Platform Admin Profile">
              <div class="avatar avatar-sm">
                <span class="avatar-initial rounded-circle" style="background-color: #0f172a; color: #fff;">{{ strtoupper(substr($platformAdmin?->name ?? 'P', 0, 1)) }}</span>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-right" style="min-width: 240px;">
              <div class="dropdown-menu-header">
                <div class="media align-items-center">
                  <div class="avatar">
                    <span class="avatar-initial rounded-circle" style="background-color: #0f172a; color: #fff;">{{ strtoupper(substr($platformAdmin?->name ?? 'P', 0, 1)) }}</span>
                  </div>
                  <div class="media-body mg-l-10">
                    <h6>{{ $platformAdmin?->name ?? 'Platform Admin' }}</h6>
                    <span class="d-block text-truncate" style="max-width: 155px;">{{ $platformAdmin?->email }}</span>
                    <div class="mg-t-4">
                      <span class="badge badge-dark text-uppercase">Super Admin</span>
                    </div>
                  </div>
                </div>
              </div>
              <div class="dropdown-menu-body">
                <a href="{{ route('platform.pending.index') }}" class="dropdown-item"><i data-feather="clock" class="svg-14"></i> {{ __('navigation.platform_pending_queue', ['count' => $pendingActivationNavCount]) }}</a>
                <a href="{{ route('platform.logs.index') }}" class="dropdown-item"><i data-feather="activity" class="svg-14"></i> {{ __('navigation.platform_audit_log') }}</a>
                <a href="{{ route('platform.settings.index') }}" class="dropdown-item"><i data-feather="sliders" class="svg-14"></i> {{ __('navigation.platform_control_settings') }}</a>
                <div class="dropdown-divider"></div>
                <form action="{{ route('platform.logout') }}" method="POST">
                  @csrf
                  <button type="submit" class="dropdown-item text-danger"><i data-feather="log-out" class="svg-14"></i> {{ __('app.logout') }}</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div class="content-header flex-wrap justify-content-between align-items-start align-items-sm-center">
        <div class="mg-r-10">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('platform.dashboard') }}">{{ __('navigation.platform_control_plane') }}</a></li>
              @yield('breadcrumb')
            </ol>
          </nav>
          <h4 class="content-title content-title-xs">@yield('page_title', __('platform.dashboard_title'))</h4>
          @hasSection('page_subtitle')
            <p class="stk-page-subtitle">@yield('page_subtitle')</p>
          @endif
        </div>
        @hasSection('actions')
          <div class="content-actions d-flex flex-wrap align-items-center mg-t-10 mg-sm-t-0">@yield('actions')</div>
        @endif
      </div>

      <main class="content-body">
        @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i data-feather="check-circle" class="svg-14 mg-r-5"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i data-feather="alert-circle" class="svg-14 mg-r-5"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
        @endif
        @if($errors->any())
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>{{ __('app.validation_error_heading') }}</strong>
            <ul class="mg-b-0 mg-t-5 pd-l-20">
              @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
              @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
        @endif

        @yield('content')
      </main>
    </div>

    <div class="modal fade" id="platformConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title font-weight-bold" id="platformConfirmTitle">{{ __('app.confirm_platform_action') }}</h6>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body" id="platformConfirmMessage">{{ __('app.confirm_platform_message') }}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-white" data-dismiss="modal">{{ __('app.cancel') }}</button>
            <button type="button" class="btn btn-danger" id="platformConfirmSubmitBtn">{{ __('app.confirm') }}</button>
          </div>
        </div>
      </div>
    </div>

    <script src="{{ asset('lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('lib/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('lib/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('lib/js-cookie/js.cookie.js') }}"></script>
    @stack('vendor-scripts')
    <script src="{{ asset('assets/js/cassie.js') }}"></script>
    <script>
      $(function () {
        var pendingConfirmForm = null;

        $('#stkSidebarBackdrop').on('click', function () {
          $('body').removeClass('toggle-sidebar');
        });

        $(document).on('click', '.dropdown-link, #stkBurgerBtn', function (e) {
          if ($(this).attr('href') === '#') {
            e.preventDefault();
          }
        });

        $(document).on('submit', 'form[data-confirm]', function (e) {
          if ($(this).data('confirmed')) {
            return true;
          }
          e.preventDefault();
          pendingConfirmForm = this;
          $('#platformConfirmTitle').text($(this).attr('data-confirm-title') || @json(__('app.confirm_platform_action')));
          $('#platformConfirmMessage').text($(this).attr('data-confirm'));
          $('#platformConfirmSubmitBtn').text($(this).attr('data-confirm-btn') || @json(__('app.confirm')))
            .removeClass('btn-danger btn-primary btn-brand-01 btn-warning btn-success')
            .addClass($(this).attr('data-confirm-btn-class') || 'btn-danger');
          $('#platformConfirmModal').modal('show');
        });

        $('#platformConfirmSubmitBtn').on('click', function () {
          if (pendingConfirmForm) {
            $(pendingConfirmForm).data('confirmed', true).trigger('submit');
            $('#platformConfirmModal').modal('hide');
          }
        });

        $(document).on('submit', 'form[method="POST"], form[method="post"]', function (e) {
          var $form = $(this);
          if ($form.is('[data-confirm]') && !$form.data('confirmed')) {
            return;
          }
          if ($form.data('stkSubmitting')) {
            e.preventDefault();
            return false;
          }
          $form.data('stkSubmitting', true);
          var $btn = $form.find('button[type="submit"]:focus, button[type="submit"].btn-brand-01, button[type="submit"].btn-primary').first();
          if ($btn.length && !$btn.data('no-loading')) {
            $btn.addClass('stk-btn-loading');
          }
          setTimeout(function () {
            $form.find('button[type="submit"]:not([data-no-disable]), input[type="submit"]:not([data-no-disable])').prop('disabled', true);
          }, 0);
        });

        $(window).on('pageshow', function () {
          $('form').each(function () {
            $(this).removeData('stkSubmitting');
            $(this).find('button[type="submit"], input[type="submit"]').prop('disabled', false).removeClass('stk-btn-loading');
          });
        });
      });
    </script>
    @stack('scripts')
  </body>
</html>
