<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>{{ __('subscription.public_plans.page_title') }} &mdash; {{ __('app.brand_name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
  </head>
  <body style="background-color: #f8fafc; overflow-x: hidden;">
    <header class="bg-white border-bottom py-3">
      <div class="container d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
        <a href="{{ url('/') }}" class="d-inline-flex align-items-center text-decoration-none" style="gap: 10px;">
          <span class="stk-brand-icon" aria-hidden="true">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </span>
          <span>
            <span class="tx-18 tx-bold tx-color-01 d-block" style="line-height: 1.1; letter-spacing: -0.025em;">stockora</span>
            <small class="tx-11 tx-color-03 d-block">{{ __('app.tagline') }}</small>
          </span>
        </a>

        <div class="d-flex flex-wrap align-items-center" style="gap: 8px;">
          @include('partials.language-switcher', ['route' => Auth::guard('platform')->check() && !Auth::check() ? 'platform.locale.update' : 'locale.update'])
          @if(Auth::guard('platform')->check())
            <a href="{{ route('platform.plans.index') }}" class="btn btn-white btn-sm">{{ __('subscription.public_plans.back_to_platform') }}</a>
          @elseif(Auth::check())
            <a href="{{ route('dashboard') }}" class="btn btn-white btn-sm">{{ __('subscription.public_plans.back_to_app') }}</a>
          @else
            <a href="{{ route('login') }}" class="btn btn-white btn-sm">{{ __('subscription.public_plans.sign_in_cta') }}</a>
            <a href="{{ route('register') }}" class="btn btn-brand-01 btn-sm">{{ __('subscription.public_plans.start_trial_cta') }}</a>
          @endif
        </div>
      </div>
    </header>

    <main class="container py-5">
      <div class="text-center mx-auto mg-b-35" style="max-width: 700px;">
        <span class="badge badge-light border mg-b-10" style="padding: 5px 12px; font-weight: 600;">
          {{ __('subscription.public_plans.badge') }}
        </span>
        <h1 class="tx-bold tx-color-01 mg-b-10" style="font-size: 32px; letter-spacing: -0.03em;">
          {{ __('subscription.public_plans.heading') }}
        </h1>
        <p class="tx-14 tx-color-02 mb-0">
          {{ __('subscription.public_plans.subtitle') }}
        </p>
      </div>

      {{-- Top Plan Summary Cards --}}
      <div class="row row-sm mg-b-35">
        @foreach($plans as $plan)
          <div class="col-md-6 col-xl-3 mg-b-20">
            <div class="card h-100 {{ $plan->is_recommended ? 'border-primary shadow-sm' : '' }}">
              <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mg-b-8">
                  <span class="stk-tenant-code">{{ $plan->code }}</span>
                  @if($plan->is_recommended)
                    <span class="badge badge-primary">{{ __('subscription.public_plans.recommended') }}</span>
                  @endif
                </div>
                <h5 class="tx-bold tx-color-01 mg-b-6">{{ $plan->name }}</h5>
                <div class="tx-24 tx-bold tx-color-01 tx-numeric mg-b-2">{{ $plan->formattedPrice() }}</div>
                <div class="tx-12 tx-color-03 mg-b-12">{{ $plan->billingIntervalLabel() }} &bull; {{ __('subscription.public_plans.manual_activation') }}</div>
                <p class="tx-12 tx-color-02 mg-b-16 flex-grow-1">{{ $plan->description ?? __('subscription.public_plans.default_desc') }}</p>

                <div class="pt-3 border-top tx-12">
                  @php
                    $highlights = ['max_users', 'max_warehouses', 'max_products'];
                  @endphp
                  @foreach($highlights as $hKey)
                    @php
                      $feat = $plan->features->firstWhere('feature_key', $hKey);
                      $rawH = $matrix[$plan->id][$hKey] ?? null;
                      if (!$feat) {
                        $valStr = '—';
                      } elseif ($feat->isLimit() && ($rawH === null || trim((string) $rawH) === '')) {
                        $valStr = __('subscription.unlimited');
                      } else {
                        $valStr = $feat->formatDisplayValue($rawH);
                      }
                    @endphp
                    <div class="d-flex justify-content-between mg-b-4">
                      <span class="tx-color-03">{{ __('subscription.limits.' . $hKey) }}</span>
                      <strong class="tx-color-01 tx-numeric">{{ $valStr }}</strong>
                    </div>
                  @endforeach
                </div>

                @if(!Auth::check() && !Auth::guard('platform')->check())
                  <a href="{{ route('register') }}" class="btn btn-sm btn-block mg-t-16 {{ $plan->is_recommended ? 'btn-brand-01' : 'btn-white' }}">
                    {{ __('subscription.public_plans.start_with_plan', ['plan' => $plan->name]) }}
                  </a>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>

      {{-- Database-Driven Feature & Limit Matrix --}}
      <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('subscription.public_plans.comparison_heading') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('subscription.public_plans.matrix_subtitle') }}</span>
          </div>
          <span class="badge badge-light">{{ __('subscription.public_plans.draft_pricing_badge') }}</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th style="min-width: 240px;">{{ __('subscription.public_plans.feature_column') }}</th>
                  @foreach($plans as $plan)
                    <th class="text-center" style="min-width: 130px;">
                      <div class="tx-bold tx-color-01">{{ $plan->name }}</div>
                      <div class="tx-11 tx-color-03">{{ $plan->formattedPrice() }} / {{ $plan->billingIntervalLabel() }}</div>
                    </th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach($groupedFeatures as $categoryName => $categoryFeatures)
                  @php
                    $catTransKey = 'subscription.categories.' . $categoryName;
                    $categoryLabel = \Illuminate\Support\Facades\Lang::has($catTransKey) ? __($catTransKey) : $categoryName;
                  @endphp
                  <tr class="bg-light">
                    <td colspan="{{ 1 + $plans->count() }}" class="tx-11 text-uppercase tx-bold tx-color-02">
                      {{ $categoryLabel }}
                    </td>
                  </tr>
                  @foreach($categoryFeatures as $feature)
                    @php
                      $featTransKey = 'subscription.features.' . $feature->feature_key;
                      $limTransKey = 'subscription.limits.' . $feature->feature_key;
                      $featureLabel = \Illuminate\Support\Facades\Lang::has($featTransKey)
                        ? __($featTransKey)
                        : (\Illuminate\Support\Facades\Lang::has($limTransKey) ? __($limTransKey) : $feature->name);
                    @endphp
                    <tr>
                      <td>
                        <div class="tx-13 tx-semibold tx-color-01">{{ $featureLabel }}</div>
                        @if($feature->description && app()->getLocale() === 'en')
                          <div class="tx-11 tx-color-03">{{ $feature->description }}</div>
                        @endif
                      </td>
                      @foreach($plans as $plan)
                        @php
                          $rawVal = $matrix[$plan->id][$feature->feature_key] ?? null;
                        @endphp
                        <td class="text-center align-middle tx-13">
                          @if($feature->isBoolean())
                            @if(in_array(strtolower((string) $rawVal), ['1', 'true', 'yes', 'on'], true))
                              <span class="badge badge-success">{{ __('subscription.included') }}</span>
                            @else
                              <span class="tx-color-03">&mdash;</span>
                            @endif
                          @elseif($feature->isLimit())
                            @if($rawVal === null || trim((string) $rawVal) === '')
                              <span class="badge badge-primary">{{ __('subscription.unlimited') }}</span>
                            @else
                              <strong class="tx-numeric tx-color-01">{{ \App\Support\StockoraFormat::number((int) $rawVal) }}</strong>
                            @endif
                          @else
                            <span class="tx-12 tx-color-02">{{ $feature->formatDisplayValue($rawVal) }}</span>
                          @endif
                        </td>
                      @endforeach
                    </tr>
                  @endforeach
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="text-center tx-12 tx-color-03 mg-t-20">
        {{ __('subscription.public_plans.footer_note') }}
        @if(!empty($support['email']))
          {{ __('subscription.public_plans.contact_cta') }} <a href="mailto:{{ $support['email'] }}">{{ $support['email'] }}</a>.
        @endif
      </div>
    </main>

    <script src="{{ asset('lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('lib/feather-icons/feather.min.js') }}"></script>
    <script>if (window.feather) { feather.replace(); }</script>
  </body>
</html>
