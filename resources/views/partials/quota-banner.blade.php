@php
  $ctxId = app(\App\Services\TenantContext::class)->id();
  $quotaBannerCompany = $ctxId ? \App\Models\Company::find($ctxId) : null;
  $quotaBannerItem = null;
  if ($quotaBannerCompany && !empty($limitKey)) {
      $summary = app(\App\Services\SubscriptionPlanService::class)->getUsageSummary($quotaBannerCompany);
      $quotaBannerItem = $summary['items'][$limitKey] ?? null;
      $quotaPlanName = $summary['plan']['name'] ?? ($quotaBannerCompany->access_type ?: 'Free');
  }
@endphp

@if($quotaBannerItem && in_array($quotaBannerItem['status'], ['warning', 'reached'], true))
  @php
    $isReached = $quotaBannerItem['status'] === 'reached';
    $supportCfg = config('stockora.support', []);
  @endphp
  <div class="alert {{ $isReached ? 'alert-danger' : 'alert-warning' }} mg-b-20" role="alert">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center" style="gap: 10px 16px;">
      <div>
        <strong class="d-block mg-b-2">
          @if($isReached)
            {{ __('subscription.banner_reached_title', ['resource' => __('subscription.limits.' . $limitKey)]) }}
          @else
            {{ __('subscription.banner_warning_title', ['resource' => __('subscription.limits.' . $limitKey), 'percent' => $quotaBannerItem['percentage']]) }}
          @endif
        </strong>
        <div class="tx-13">
          {{ __('subscription.banner_usage_detail', [
              'usage' => \App\Support\StockoraFormat::number($quotaBannerItem['usage']),
              'limit' => \App\Support\StockoraFormat::number($quotaBannerItem['limit']),
              'plan' => $quotaPlanName ?? 'Free',
          ]) }}
          @if(!empty($supportCfg['email']) || !empty($supportCfg['whatsapp']))
            <span class="d-block d-sm-inline ml-sm-1">
              {{ __('subscription.banner_contact', ['email' => $supportCfg['email'] ?? '', 'whatsapp' => $supportCfg['whatsapp'] ?? '']) }}
            </span>
          @endif
        </div>
      </div>
      <a href="{{ route('plans.index') }}" class="btn btn-xs {{ $isReached ? 'btn-danger' : 'btn-warning' }} flex-shrink-0" target="_blank" rel="noopener">
        {{ __('subscription.view_upgrade_options') }}
      </a>
    </div>
  </div>
@endif
