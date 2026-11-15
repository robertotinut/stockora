<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('auth.onboarding_company_page_title') }} &mdash; Stockora</title>

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

          {{-- Brand Header & User Bar --}}
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
              <div class="text-right d-none d-sm-block">
                <div class="tx-12 tx-semibold tx-color-01">{{ Auth::user()->name }}</div>
                <div class="tx-11 tx-color-03">{{ Auth::user()->email }}</div>
              </div>
              <form action="{{ route('logout') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-white btn-sm text-danger">
                  <i data-feather="log-out" class="svg-14"></i> {{ __('app.sign_out') }}
                </button>
              </form>
            </div>
          </div>

          <div class="card card-hover">
            <div class="card-body p-4 p-md-5">

              <div class="mg-b-24" style="max-width: 440px;">
                @include('onboarding._stepper', ['step' => 2])
              </div>

              <div class="mg-b-24">
                <h3 class="tx-bold tx-color-01 mg-b-6" style="letter-spacing: -0.025em;">{{ __('auth.onboarding_company_title') }}</h3>
                <p class="tx-13 tx-color-03 mb-0">
                  {{ __('auth.onboarding_company_desc', ['warehouse' => 'WH-MAIN', 'role' => 'OWNER']) }}
                </p>
              </div>

              @if(session('success'))
                <div class="alert alert-success tx-13">{{ session('success') }}</div>
              @endif
              @if(session('warning'))
                <div class="alert alert-warning tx-13">{{ session('warning') }}</div>
              @endif
              @if($errors->any())
                <div class="alert alert-danger tx-13">
                  <strong>{{ __('auth.validation_company_errors') }}</strong>
                  <ul class="mg-b-0 mg-t-5 pd-l-20">
                    @foreach($errors->all() as $error)
                      <li>{{ $error }}</li>
                    @endforeach
                  </ul>
                </div>
              @endif

              <form action="{{ route('onboarding.company.store') }}" method="POST" enctype="multipart/form-data" id="stkOnboardingCompanyForm">
                @csrf

                <div class="row row-sm">
                  <div class="col-md-7 form-group">
                    <label for="companyName">{{ __('auth.company_name_label') }} <span class="tx-danger">*</span></label>
                    <input type="text" id="companyName" name="name" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('auth.company_name_placeholder') }}" value="{{ old('name') }}" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                  </div>

                  <div class="col-md-5 form-group">
                    <div class="d-flex justify-content-between align-items-center">
                      <label for="companyCode">{{ __('auth.company_code_label') }}</label>
                      <button type="button" id="stkRegenCodeBtn" class="btn btn-link p-0 tx-11 tx-semibold">{{ __('auth.autogen_btn') }}</button>
                    </div>
                    <input type="text" id="companyCode" name="code" class="form-control stk-mono text-uppercase @error('code') is-invalid @enderror" placeholder="{{ __('auth.company_code_placeholder') }}" value="{{ old('code') }}">
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <small class="form-text text-muted">{{ __('auth.company_code_edit_hint') }}</small>
                  </div>
                </div>

                <div class="row row-sm">
                  <div class="col-md-6 form-group">
                    <label for="industry">{{ __('auth.industry_label') }} <span class="tx-danger">*</span></label>
                    <select id="industry" name="industry" class="form-control @error('industry') is-invalid @enderror" required>
                      <option value="">{{ __('auth.industry_placeholder') }}</option>
                      @foreach($industries as $item)
                        <option value="{{ $item }}" {{ old('industry') === $item ? 'selected' : '' }}>{{ $item }}</option>
                      @endforeach
                    </select>
                    @error('industry')<div class="invalid-feedback">{{ $message }}</div>@enderror
                  </div>

                  <div class="col-md-6 form-group">
                    <label for="phone">{{ __('auth.company_phone_label') }} <span class="tx-danger">*</span></label>
                    <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" placeholder="{{ __('auth.company_phone_placeholder') }}" value="{{ old('phone') }}" required>
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                  </div>
                </div>

                <div class="form-group">
                  <label for="address">{{ __('auth.company_address_label') }} <span class="tx-danger">*</span></label>
                  <textarea id="address" name="address" rows="3" class="form-control @error('address') is-invalid @enderror" placeholder="{{ __('auth.company_address_placeholder') }}" required>{{ old('address') }}</textarea>
                  @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group mg-b-24">
                  <label for="companyLogo">{{ __('auth.company_logo_label') }}</label>
                  <div class="stk-upload-box">
                    <div class="stk-logo-preview" id="stkLogoPreview">
                      <i data-feather="image" class="svg-20 tx-color-04"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                      <input type="file" id="companyLogo" name="logo" class="form-control-file tx-13 @error('logo') is-invalid @enderror" accept="image/png,image/jpeg,image/webp">
                      <small class="form-text text-muted mb-0">{{ __('auth.logo_upload_hint') }}</small>
                      @error('logo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                  </div>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top" style="gap: 12px;">
                  <div class="tx-12 tx-color-03">
                    <i data-feather="shield" class="svg-14 text-success mg-r-4"></i>
                    {{ __('auth.atomic_creation_note') }}
                  </div>
                  <button type="submit" id="stkCompanySubmit" class="btn btn-brand-01 px-4 py-2">
                    {{ __('auth.complete_setup_btn') }} <i data-feather="arrow-right" class="svg-14 mg-l-4"></i>
                  </button>
                </div>
              </form>

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

        var $name = $('#companyName');
        var $code = $('#companyCode');
        var codeManuallyEdited = $code.val().trim() !== '';

        function slugCode(text) {
          return String(text || '')
            .toUpperCase()
            .replace(/[^A-Z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 18)
            .replace(/-+$/g, '');
        }

        $name.on('input', function () {
          if (!codeManuallyEdited) {
            $code.val(slugCode($name.val()));
          }
        });

        $code.on('input', function () {
          codeManuallyEdited = $code.val().trim() !== '';
          $code.val($code.val().toUpperCase().replace(/[^A-Z0-9_-]/g, ''));
        });

        $('#stkRegenCodeBtn').on('click', function () {
          codeManuallyEdited = false;
          $code.val(slugCode($name.val()));
        });

        $('#companyLogo').on('change', function (e) {
          var file = e.target.files && e.target.files[0];
          var $preview = $('#stkLogoPreview');
          if (!file) {
            $preview.html('<i data-feather="image" class="svg-20 tx-color-04"></i>');
            if (window.feather) feather.replace();
            return;
          }
          var reader = new FileReader();
          reader.onload = function (ev) {
            $preview.html($('<img>', { src: ev.target.result, alt: 'Company Logo Preview' }));
          };
          reader.readAsDataURL(file);
        });

        $('#stkOnboardingCompanyForm').on('submit', function () {
          var $btn = $('#stkCompanySubmit');
          $btn.addClass('stk-btn-loading').prop('disabled', true);
          $btn.html('<span class="stk-spinner" aria-hidden="true"></span> ' + @json(__('auth.creating_workspace_btn')));
        });
      });
    </script>
  </body>
</html>
