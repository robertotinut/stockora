<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.register_page_title') }} &mdash; Stockora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body class="stk-auth-shell">
    <div class="container-fluid px-0">
      <div class="row no-gutters" style="min-height: 100vh;">

        {{-- Brand & Onboarding Highlights Panel --}}
        <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-between p-5 stk-auth-aside">
          <div>
            <div class="d-inline-flex align-items-center" style="gap: 12px;">
              <div class="stk-brand-mark" style="width: 40px; height: 40px; border-radius: 10px;">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <div>
                <div class="tx-20 tx-bold text-white" style="letter-spacing: -0.025em; line-height: 1.1;">stockora</div>
                <div class="tx-12" style="color: #94a3b8;">{{ __('auth.hybrid_platform') }}</div>
              </div>
            </div>
          </div>

          <div class="my-auto py-4" style="max-width: 490px;">
            <span class="badge mg-b-16" style="background: rgba(37, 99, 235, 0.25); color: #bfdbfe; border: 1px solid rgba(147, 197, 253, 0.25); padding: 5px 12px;">
              {{ __('auth.tagline') }}
            </span>
            <h1 class="text-white tx-bold mg-b-14" style="font-size: 32px; line-height: 1.22; letter-spacing: -0.03em;">
              {{ __('auth.register_aside_heading') }}
            </h1>
            <p class="tx-15 mg-b-28" style="color: #cbd5e1; line-height: 1.6;">
              {{ __('auth.register_aside_desc') }}
            </p>

            <div class="d-flex flex-column" style="gap: 14px;">
              <div class="d-flex align-items-start p-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.09); border-radius: 10px; gap: 12px;">
                <i data-feather="user-check" class="svg-18 flex-shrink-0 mg-t-2" style="color: #60a5fa;"></i>
                <div>
                  <div class="tx-13 tx-semibold text-white">{{ __('auth.register_feature_1_title') }}</div>
                  <div class="tx-12" style="color: #94a3b8;">{{ __('auth.register_feature_1_desc') }}</div>
                </div>
              </div>
              <div class="d-flex align-items-start p-3" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.09); border-radius: 10px; gap: 12px;">
                <i data-feather="lock" class="svg-18 flex-shrink-0 mg-t-2" style="color: #34d399;"></i>
                <div>
                  <div class="tx-13 tx-semibold text-white">{{ __('auth.register_feature_2_title') }}</div>
                  <div class="tx-12" style="color: #94a3b8;">{{ __('auth.register_feature_2_desc') }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="tx-12" style="color: #64748b;">
            {{ __('auth.aside_footer', ['year' => date('Y')]) }}
          </div>
        </div>

        {{-- Registration Form Panel --}}
        <div class="col-12 col-lg-6 d-flex flex-column justify-content-center align-items-center p-4 p-sm-5">
          <div style="width: 100%; max-width: 450px;">

            <div class="d-flex justify-content-end mg-b-14">
              @include('partials.language-switcher')
            </div>

            <div class="d-flex d-lg-none align-items-center mg-b-20" style="gap: 10px;">
              <div class="stk-brand-mark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                  <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                  <line x1="12" y1="22.08" x2="12" y2="12"/>
                </svg>
              </div>
              <div>
                <div class="tx-18 tx-bold tx-color-01" style="line-height: 1.1;">stockora</div>
                <div class="tx-11 tx-color-03">{{ __('auth.tagline') }}</div>
              </div>
            </div>

            <div class="card card-hover">
              <div class="card-body p-4">
                <div class="mg-b-20">
                  @include('onboarding._stepper', ['step' => 1])
                </div>

                <div class="mg-b-20">
                  <h4 class="tx-bold tx-color-01 mg-b-6" style="letter-spacing: -0.025em;">{{ __('auth.register_title') }}</h4>
                  <p class="tx-13 tx-color-03 mb-0">{{ __('auth.register_card_subtitle') }}</p>
                </div>

                @if($errors->any())
                  <div class="alert alert-danger tx-13">
                    <strong>{{ __('auth.validation_review_errors') }}</strong>
                    <ul class="mg-b-0 mg-t-5 pd-l-20">
                      @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                      @endforeach
                    </ul>
                  </div>
                @endif

                <form action="{{ route('register') }}" method="POST" id="stkRegisterForm">
                  @csrf

                  <div class="form-group">
                    <label for="name">{{ __('auth.full_name_label') }} <span class="tx-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('auth.full_name_placeholder') }}" value="{{ old('name') }}" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                  </div>

                  <div class="form-group">
                    <label for="email">{{ __('auth.email_label') }} <span class="tx-danger">*</span></label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" placeholder="{{ __('auth.email_placeholder') }}" value="{{ old('email', session('pending_invitation_email')) }}" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                  </div>

                  <div class="form-group">
                    <label for="password">{{ __('auth.password_label') }} <span class="tx-danger">*</span></label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="{{ __('auth.password_placeholder_min') }}" required>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">{{ __('auth.password_rule_hint') }}</small>
                  </div>

                  <div class="form-group">
                    <label for="password_confirmation">{{ __('auth.confirm_password_label') }} <span class="tx-danger">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="{{ __('auth.confirm_password_placeholder') }}" required>
                  </div>

                  <div class="form-group mg-b-20">
                    <div class="custom-control custom-checkbox">
                      <input type="checkbox" name="agree_terms" value="1" class="custom-control-input @error('agree_terms') is-invalid @enderror" id="agree_terms" {{ old('agree_terms') ? 'checked' : '' }} required>
                      <label class="custom-control-label tx-13 tx-color-02" for="agree_terms">
                        {{ __('auth.agree_terms_prefix') }} <a href="#stkTermsModal" data-toggle="modal" data-target="#stkTermsModal" class="tx-semibold">{{ __('auth.terms_conditions_link') }}</a>
                      </label>
                      @error('agree_terms')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                  </div>

                  <button type="submit" id="stkRegisterSubmit" class="btn btn-brand-01 btn-block py-2">
                    {{ __('auth.register_submit_btn') }}
                  </button>
                </form>

                <div class="mg-t-20 pt-3 border-top d-flex flex-wrap justify-content-between align-items-center tx-13" style="gap: 8px;">
                  <span class="tx-color-03">{{ __('auth.already_have_account') }}</span>
                  <a href="{{ route('login') }}" class="tx-semibold">{{ __('auth.sign_in_btn') }} &rarr;</a>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>

    {{-- Terms and Conditions Modal --}}
    <div class="modal fade" id="stkTermsModal" tabindex="-1" role="dialog" aria-labelledby="stkTermsTitle" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title font-weight-bold" id="stkTermsTitle">{{ __('auth.terms_modal_title') }}</h6>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body tx-13 tx-color-02" style="line-height: 1.6;">
            <p class="mg-b-10">
              {{ __('auth.terms_modal_intro') }}
            </p>
            <ul class="mg-b-0 pd-l-20">
              @foreach((array) __('auth.terms_modal_bullets') as $bullet)
                <li>{{ $bullet }}</li>
              @endforeach
            </ul>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-brand-01 btn-sm" data-dismiss="modal" id="stkAcceptTermsBtn">{{ __('auth.terms_modal_close') }}</button>
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
        $('#stkRegisterForm').on('submit', function () {
          var $btn = $('#stkRegisterSubmit');
          $btn.addClass('stk-btn-loading').prop('disabled', true);
          $btn.html('<span class="stk-spinner" aria-hidden="true"></span> ' + @json(__('auth.creating_account_btn')));
        });
      });
    </script>
  </body>
</html>
