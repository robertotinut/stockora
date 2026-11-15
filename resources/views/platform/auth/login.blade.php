<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.platform_page_title') }} &mdash; Stockora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell">
    <div class="container-fluid px-0">
      <div class="row no-gutters" style="min-height: 100vh;">

        <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-between p-5" style="background: linear-gradient(155deg, #0b132b 0%, #162447 55%, #1e3a8a 100%); color: #fff;">
          <div>
            <div class="d-inline-flex align-items-center" style="gap: 12px;">
              <div class="stk-brand-mark" style="width: 40px; height: 40px; border-radius: 10px; background: #2563eb;">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
              </div>
              <div>
                <div class="tx-20 tx-bold text-white" style="letter-spacing: -0.025em; line-height: 1.1;">stockora</div>
                <div class="tx-12" style="color: #93c5fd;">{{ __('auth.platform_badge_title') }}</div>
              </div>
            </div>
          </div>

          <div class="my-auto py-4" style="max-width: 500px;">
            <span class="badge mg-b-16" style="background: rgba(59, 130, 246, 0.25); color: #bfdbfe; border: 1px solid rgba(147, 197, 253, 0.3); padding: 5px 12px;">
              {{ __('auth.platform_aside_pill') }}
            </span>
            <h1 class="text-white tx-bold mg-b-14" style="font-size: 32px; line-height: 1.22; letter-spacing: -0.03em;">
              {{ __('auth.platform_aside_heading') }}
            </h1>
            <p class="tx-15 mg-b-28" style="color: #cbd5e1; line-height: 1.6;">
              {{ __('auth.platform_aside_desc') }}
            </p>

            <div class="d-flex flex-column" style="gap: 12px;">
              <div class="d-flex align-items-start p-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; gap: 12px;">
                <i data-feather="check-circle" class="svg-18 flex-shrink-0 mg-t-2" style="color: #34d399;"></i>
                <div>
                  <div class="tx-13 tx-semibold text-white">{{ __('auth.platform_feature_1_title') }}</div>
                  <div class="tx-12" style="color: #94a3b8;">{{ __('auth.platform_feature_1_desc') }}</div>
                </div>
              </div>
              <div class="d-flex align-items-start p-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; gap: 12px;">
                <i data-feather="lock" class="svg-18 flex-shrink-0 mg-t-2" style="color: #60a5fa;"></i>
                <div>
                  <div class="tx-13 tx-semibold text-white">{{ __('auth.platform_feature_2_title') }}</div>
                  <div class="tx-12" style="color: #94a3b8;">{{ __('auth.platform_feature_2_desc') }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="tx-12" style="color: #94a3b8;">
            {{ __('auth.platform_aside_footer') }}
          </div>
        </div>

        <div class="col-12 col-lg-6 d-flex flex-column justify-content-center align-items-center p-4 p-sm-5">
          <div style="width: 100%; max-width: 420px;">

            <div class="d-flex justify-content-end mg-b-14">
              @include('partials.language-switcher', ['route' => 'platform.locale.update'])
            </div>

            <div class="d-flex d-lg-none align-items-center mg-b-24" style="gap: 10px;">
              <div class="stk-brand-mark" style="background: #0f172a;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
              </div>
              <div>
                <div class="tx-18 tx-bold tx-color-01" style="line-height: 1.1;">stockora</div>
                <div class="tx-11 tx-color-03">{{ __('auth.platform_badge_title') }}</div>
              </div>
            </div>

            <div class="card card-hover">
              <div class="card-body p-4">
                <div class="mg-b-20">
                  <span class="stk-platform-badge mg-b-10"><i data-feather="shield" class="svg-12"></i> {{ __('auth.platform_admin_pill') }}</span>
                  <h4 class="tx-bold tx-color-01 mg-b-6" style="letter-spacing: -0.025em;">{{ __('auth.platform_login_title') }}</h4>
                  <p class="tx-13 tx-color-03 mb-0">{{ __('auth.platform_auth_subtitle') }}</p>
                </div>

                @if(session('success'))
                  <div class="alert alert-success tx-13">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                  <div class="alert alert-danger tx-13">{{ $errors->first() }}</div>
                @endif

                <form action="{{ route('platform.login.submit') }}" method="POST" id="platformLoginForm">
                  @csrf
                  <div class="form-group">
                    <label for="email">{{ __('auth.operator_email_label') }}</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" placeholder="operator@stockora.app" required autofocus>
                  </div>

                  <div class="form-group">
                    <label for="password">{{ __('auth.password_label') }}</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="{{ __('auth.operator_password_placeholder') }}" required>
                  </div>

                  <div class="custom-control custom-checkbox mg-b-20">
                    <input type="checkbox" name="remember" class="custom-control-input" id="remember">
                    <label class="custom-control-label tx-13 tx-color-02" for="remember">{{ __('auth.platform_remember_me') }}</label>
                  </div>

                  <button type="submit" class="btn btn-brand-01 btn-block py-2">{{ __('auth.platform_sign_in_btn') }}</button>
                </form>

                <div class="mg-t-20 pt-3 border-top tx-12 tx-color-03">
                  {{ __('auth.platform_tenant_prompt') }} <a href="{{ route('login') }}" class="tx-semibold">{{ __('auth.back_to_tenant_login') }} &rarr;</a>
                </div>
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
      $(function () { if (window.feather) feather.replace(); });
    </script>
  </body>
</html>
