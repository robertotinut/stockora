<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>@yield('title', __('payments.customer.page_title')) &mdash; {{ __('app.brand_name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
    @stack('styles')
  </head>
  <body class="stk-auth-shell" style="overflow-x: clip;">
    <div class="container py-4 py-md-5">
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
            <div class="tx-11 tx-color-03">{{ __('app.tagline') }}</div>
          </div>
        </div>

        <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
          @include('partials.language-switcher')
          <a href="{{ route('company.access-status') }}" class="btn btn-white btn-xs">
            <i data-feather="shield" class="svg-13"></i> {{ __('payments.customer.back_access_status') }}
          </a>
          <span class="tx-12 tx-color-03">{{ __('auth.signed_in_as') }} <strong class="tx-color-01">{{ Auth::user()->name }}</strong></span>
          <form action="{{ route('logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-white btn-xs text-danger">
              <i data-feather="log-out" class="svg-13"></i> {{ __('app.logout') }}
            </button>
          </form>
        </div>
      </div>

      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mg-b-20" style="gap: 12px;">
        <div>
          <nav aria-label="breadcrumb" class="mg-b-6">
            <ol class="breadcrumb breadcrumb-style1 mg-b-0 tx-12">
              <li class="breadcrumb-item"><a href="{{ route('company.access-status') }}">{{ __('auth.access_status_page_title') }}</a></li>
              @yield('breadcrumb')
            </ol>
          </nav>
          <h3 class="tx-bold tx-color-01 mg-b-4" style="letter-spacing: -0.025em;">@yield('page_title')</h3>
          <p class="tx-13 tx-color-03 mb-0">@yield('page_subtitle')</p>
        </div>
        <div class="d-flex flex-wrap" style="gap: 8px;">
          @yield('actions')
        </div>
      </div>

      @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          {{ session('success') }}
          <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
        </div>
      @endif
      @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          {{ session('error') }}
          <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
        </div>
      @endif
      @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          {{ session('warning') }}
          <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
        </div>
      @endif
      @if($errors->any())
        <div class="alert alert-danger" role="alert">
          <ul class="mb-0 pd-l-18 tx-13">
            @foreach($errors->all() as $err)
              <li>{{ $err }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      @yield('content')
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
    @stack('scripts')
  </body>
</html>
