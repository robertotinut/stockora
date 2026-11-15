<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ __('auth.error_403_title') }} &mdash; Stockora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell d-flex align-items-center justify-content-center p-4">
    <div class="card card-hover text-center p-4 p-md-5" style="max-width: 480px; width: 100%;">
      <div class="mx-auto mg-b-16" style="width: 56px; height: 56px; border-radius: 14px; background-color: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center;">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
      </div>
      <div class="tx-12 tx-uppercase tx-semibold tx-color-03 mg-b-6">{{ __('auth.error_403_eyebrow') }}</div>
      <h4 class="tx-bold mg-b-10">{{ __('auth.error_403_heading') }}</h4>
      <p class="tx-13 tx-color-03 mg-b-25">{{ $exception->getMessage() ?: __('auth.error_403_default_msg') }}</p>
      <div class="d-flex flex-wrap justify-content-center" style="gap: 10px;">
        @auth
          @if(session('active_company_id') && auth()->user()?->canAccessModule('dashboard', 'view'))
            <a href="{{ route('dashboard') }}" class="btn btn-brand-01">{{ __('auth.back_to_dashboard') }}</a>
            <a href="{{ route('company.select') }}" class="btn btn-white">{{ __('auth.switch_workspace_btn') }}</a>
          @else
            <a href="{{ route('company.select') }}" class="btn btn-brand-01">{{ __('auth.switch_workspace_btn') }}</a>
          @endif
        @else
          <a href="{{ route('login') }}" class="btn btn-brand-01">{{ __('auth.sign_in_btn') }}</a>
        @endauth
      </div>
    </div>
  </body>
</html>
