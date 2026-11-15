<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>{{ __('auth.error_429_title') }} &mdash; Stockora</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell d-flex align-items-center justify-content-center p-4">
    <div class="card card-hover text-center p-4 p-md-5" style="max-width: 480px; width: 100%;">
      <div class="mx-auto mg-b-16" style="width: 56px; height: 56px; border-radius: 14px; background-color: #fffbeb; color: #d97706; display: flex; align-items: center; justify-content: center;">
        <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      </div>
      <div class="tx-12 tx-uppercase tx-semibold tx-color-03 mg-b-6">{{ __('auth.error_429_eyebrow') }}</div>
      <h4 class="tx-bold mg-b-10">{{ __('auth.error_429_heading') }}</h4>
      <p class="tx-13 tx-color-02 mg-b-8">{{ $message ?? __('auth.throttle_too_many_requests', ['seconds' => $retryAfter ?? 60]) }}</p>
      <p class="tx-12 tx-color-03 mg-b-25">{{ __('auth.error_429_retry_note', ['seconds' => $retryAfter ?? 60]) }}</p>
      <div class="d-flex flex-wrap justify-content-center" style="gap: 10px;">
        @if(!empty($isPlatform))
          <a href="{{ route('platform.login') }}" class="btn btn-brand-01">{{ __('auth.try_again_later_btn') }}</a>
        @elseif(auth()->check())
          <a href="{{ route('company.select') }}" class="btn btn-brand-01">{{ __('auth.switch_workspace_btn') }}</a>
        @else
          <a href="{{ route('login') }}" class="btn btn-brand-01">{{ __('auth.try_again_later_btn') }}</a>
        @endif
      </div>
    </div>
  </body>
</html>
