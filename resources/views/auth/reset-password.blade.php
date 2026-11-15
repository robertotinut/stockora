<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.reset_password_page_title') }} &mdash; Stockora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell">
    <div class="container py-5">
      <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">

          <div class="d-flex justify-content-end mg-b-12">
            @include('partials.language-switcher')
          </div>

          <div class="text-center mg-b-25">
            <div class="d-inline-flex align-items-center justify-content-center mg-b-12" style="gap: 10px;">
              <div class="stk-brand-mark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <span class="tx-20 tx-bold tx-color-01" style="letter-spacing: -0.025em;">stockora</span>
            </div>
            <h4 class="tx-bold tx-color-01 mg-b-6">{{ __('auth.reset_password_title') }}</h4>
            <p class="tx-13 tx-color-03 mb-0">{{ __('auth.reset_password_subtitle') }}</p>
          </div>

          <div class="card card-hover">
            <div class="card-body p-4">
              @if(!$tokenValid)
                <div class="alert alert-danger tx-13 mb-3">
                  {{ __('auth.reset_password_invalid_token') }}
                </div>
                <a href="{{ route('password.request') }}" class="btn btn-brand-01 btn-block mg-b-10">{{ __('auth.send_reset_link_btn') }}</a>
                <a href="{{ route('login') }}" class="btn btn-white btn-block">{{ __('auth.back_to_signin') }}</a>
              @else
                @if($errors->any())
                  <div class="alert alert-danger tx-13">{{ $errors->first() }}</div>
                @endif

                <form action="{{ route('password.update') }}" method="POST">
                  @csrf
                  <input type="hidden" name="token" value="{{ $token }}">

                  <div class="form-group">
                    <label for="email">{{ __('auth.account_email_label') }}</label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $email) }}" readonly required>
                  </div>

                  <div class="form-group">
                    <label for="password">{{ __('auth.new_password_label') }}</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('auth.password_placeholder_min') }}" required autofocus>
                  </div>

                  <div class="form-group">
                    <label for="password_confirmation">{{ __('auth.confirm_password_label') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="{{ __('auth.confirm_password_placeholder') }}" required>
                  </div>

                  <button type="submit" class="btn btn-brand-01 btn-block py-2">{{ __('auth.reset_password_submit_btn') }}</button>
                </form>

                <div class="text-center mg-t-20 pt-3 border-top">
                  <a href="{{ route('login') }}" class="tx-13 tx-semibold">{{ __('auth.back_to_signin') }}</a>
                </div>
              @endif
            </div>
          </div>

        </div>
      </div>
    </div>

    <script src="{{ asset('lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
  </body>
</html>
