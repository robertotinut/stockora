@extends('layouts.platform')

@section('title', $isEdit ? __('platform.plans_ui.edit_tab', ['name' => $plan->name]) : __('platform.plan_create_title'))
@section('page_title', $isEdit ? __('platform.plans_ui.edit_heading', ['name' => $plan->name]) : __('platform.plan_create_title'))
@section('page_subtitle', __('platform.plans_ui.form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('platform.plans.index') }}">{{ __('platform.plans_title') }}</a></li>
  @if($isEdit)
    <li class="breadcrumb-item"><a href="{{ route('platform.plans.show', $plan) }}">{{ $plan->code }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ $isEdit ? __('platform.plans_ui.configure_btn') : __('app.create') }}</li>
@endsection

@section('actions')
  <a href="{{ $isEdit ? route('platform.plans.show', $plan) : route('platform.plans.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('app.back') }}
  </a>
@endsection

@section('content')
  <form action="{{ $isEdit ? route('platform.plans.update', $plan) : route('platform.plans.store') }}" method="POST" id="subscriptionPlanForm">
    @csrf
    @if($isEdit)
      @method('PUT')
    @endif

    <div class="row row-sm">
      <div class="col-lg-5 mg-b-20">
        <div class="card">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ __('platform.plans_ui.identity_pricing_heading') }}</h6>
          </div>
          <div class="card-body">
            <div class="row row-sm">
              <div class="col-md-7 form-group">
                <label for="plan_name">{{ __('platform.plans_ui.plan_name') }} <span class="text-danger">*</span></label>
                <input type="text" id="plan_name" name="name" class="form-control" value="{{ old('name', $plan->name) }}" placeholder="{{ __('platform.plans_ui.plan_name_ph') }}" required>
              </div>
              <div class="col-md-5 form-group">
                <label for="plan_code">{{ __('platform.plans_ui.plan_code') }} <span class="text-danger">*</span></label>
                <input type="text" id="plan_code" name="code" class="form-control text-uppercase" value="{{ old('code', $plan->code) }}" placeholder="{{ __('platform.plans_ui.plan_code_ph') }}" {{ ($isEdit && $plan->isCoreSystemPlan()) ? 'readonly' : 'required' }}>
                @if($isEdit && $plan->isCoreSystemPlan())
                  <small class="form-text text-muted">{{ __('platform.plans_ui.core_code_locked') }}</small>
                @endif
              </div>
            </div>

            <div class="row row-sm">
              <div class="col-md-6 form-group">
                <label for="plan_tier">{{ __('platform.plans_ui.compatible_tier') }} <span class="text-danger">*</span></label>
                @php($tierLockedByCode = $isEdit && array_key_exists(strtoupper((string) $plan->code), \App\Models\Company::ACCESS_TYPES))
                <select id="plan_tier" name="tier" class="form-control" {{ $tierLockedByCode ? 'disabled' : 'required' }}>
                  @foreach($tiers as $tKey => $tMeta)
                    <option value="{{ $tKey }}" {{ old('tier', $plan->tier) === $tKey ? 'selected' : '' }}>{{ __('platform.access_types.' . $tKey) }}</option>
                  @endforeach
                </select>
                @if($tierLockedByCode)
                  <input type="hidden" name="tier" value="{{ $plan->code }}">
                @endif
              </div>
              <div class="col-md-6 form-group">
                <label for="billing_interval">{{ __('platform.plans_ui.billing_interval') }} <span class="text-danger">*</span></label>
                <select id="billing_interval" name="billing_interval" class="form-control" required>
                  @foreach($intervals as $iKey => $iLabel)
                    @php
                      $intKey = "subscription.billing_intervals.{$iKey}";
                      $intText = \Illuminate\Support\Facades\Lang::has($intKey) ? __($intKey) : $iLabel;
                    @endphp
                    <option value="{{ $iKey }}" {{ old('billing_interval', $plan->billing_interval) === $iKey ? 'selected' : '' }}>{{ $intText }}</option>
                  @endforeach
                </select>
              </div>
            </div>

            <div class="row row-sm">
              <div class="col-md-7 form-group">
                <label for="plan_price">{{ __('platform.plans_ui.info_price_rp') }} <span class="text-danger">*</span></label>
                <input type="number" id="plan_price" name="price" step="1" min="0" class="form-control" value="{{ old('price', (int) $plan->price) }}" required>
                <small class="form-text text-muted">{{ __('platform.plans_ui.info_price_help') }}</small>
              </div>
              <div class="col-md-5 form-group">
                <label for="sort_order">{{ __('platform.plans_ui.sort_order') }} <span class="text-danger">*</span></label>
                <input type="number" id="sort_order" name="sort_order" min="0" max="9999" class="form-control" value="{{ old('sort_order', $plan->sort_order ?? 10) }}" required>
              </div>
            </div>

            <div class="form-group">
              <label for="plan_description">{{ __('app.description') }}</label>
              <textarea id="plan_description" name="description" rows="3" class="form-control" placeholder="{{ __('platform.plans_ui.desc_ph') }}">{{ old('description', $plan->description) }}</textarea>
            </div>

            <div class="border-top pt-3">
              <div class="custom-control custom-checkbox mg-b-8">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" class="custom-control-input" id="plan_is_active" name="is_active" value="1" {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }}>
                <label class="custom-control-label tx-13" for="plan_is_active">{{ __('platform.plans_ui.is_active_label') }}</label>
              </div>
              <div class="custom-control custom-checkbox mg-b-8">
                <input type="hidden" name="is_recommended" value="0">
                <input type="checkbox" class="custom-control-input" id="plan_is_recommended" name="is_recommended" value="1" {{ old('is_recommended', $plan->is_recommended) ? 'checked' : '' }} {{ old('is_internal', $plan->is_internal) ? 'disabled' : '' }}>
                <label class="custom-control-label tx-13" for="plan_is_recommended">{{ __('platform.plans_ui.is_recommended_label') }}</label>
              </div>
              <div class="custom-control custom-checkbox">
                <input type="hidden" name="is_internal" value="0">
                <input type="checkbox" class="custom-control-input" id="plan_is_internal" name="is_internal" value="1" {{ old('is_internal', $plan->is_internal) ? 'checked' : '' }} {{ ($isEdit && $plan->is_internal) ? 'disabled' : '' }}>
                <label class="custom-control-label tx-13" for="plan_is_internal">{{ __('platform.plans_ui.is_internal_label') }}</label>
                @if($isEdit && $plan->is_internal)
                  <input type="hidden" name="is_internal" value="1">
                @endif
              </div>
            </div>

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ $isEdit ? route('platform.plans.show', $plan) : route('platform.plans.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01 px-4">
                {{ $isEdit ? __('platform.plans_ui.save_config_btn') : __('platform.plan_create_title') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        @foreach($groupedFeatures as $categoryName => $categoryFeatures)
          @php
            $catKey = "subscription.categories.{$categoryName}";
            $catLabel = \Illuminate\Support\Facades\Lang::has($catKey) ? __($catKey) : $categoryName;
          @endphp
          <div class="card mg-b-20">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h6 class="mg-b-0 tx-semibold tx-color-01">{{ $catLabel }}</h6>
              <span class="badge badge-light">{{ __('platform.plans_ui.items_count', ['count' => $categoryFeatures->count()]) }}</span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0">
                  <thead>
                    <tr>
                      <th>{{ __('platform.plans_ui.col_feature_resource') }}</th>
                      <th style="width: 220px;">{{ __('platform.plans_ui.col_configured_value') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    @foreach($categoryFeatures as $feature)
                      @php
                        $currentVal = old("features.{$feature->id}", $featureValues[$feature->id] ?? $feature->default_value);
                        $featTrans = "subscription.features.{$feature->feature_key}";
                        $limTrans = "subscription.limits.{$feature->feature_key}";
                        $featLabel = \Illuminate\Support\Facades\Lang::has($featTrans)
                          ? __($featTrans)
                          : (\Illuminate\Support\Facades\Lang::has($limTrans) ? __($limTrans) : $feature->name);
                      @endphp
                      <tr>
                        <td>
                          <div class="tx-semibold tx-color-01">
                            {{ $featLabel }}
                            @if(!$feature->is_active)
                              <span class="badge badge-secondary ml-1">{{ __('platform.plans_ui.inactive_feature_badge') }}</span>
                            @endif
                          </div>
                          <div class="tx-11 tx-color-03"><code>{{ $feature->feature_key }}</code>@if(app()->getLocale() === 'en' && $feature->description) &bull; {{ $feature->description }}@endif</div>
                        </td>
                        <td class="align-middle">
                          @if($feature->isBoolean())
                            <input type="hidden" name="features[{{ $feature->id }}]" value="0">
                            <div class="custom-control custom-switch">
                              <input type="checkbox" class="custom-control-input" id="feat_{{ $feature->id }}" name="features[{{ $feature->id }}]" value="1" {{ in_array(strtolower((string) $currentVal), ['1', 'true', 'yes', 'on'], true) ? 'checked' : '' }}>
                              <label class="custom-control-label tx-12" for="feat_{{ $feature->id }}">{{ __('subscription.enabled') }}</label>
                            </div>
                          @elseif($feature->isLimit())
                            <input type="number" min="0" step="1" name="features[{{ $feature->id }}]" class="form-control form-control-sm" value="{{ $currentVal }}" placeholder="{{ __('platform.plans_ui.unlimited_ph') }}">
                            <small class="tx-11 text-muted">{{ __('platform.plans_ui.blank_unlimited_help') }}</small>
                          @else
                            <input type="text" name="features[{{ $feature->id }}]" maxlength="255" class="form-control form-control-sm" value="{{ $currentVal }}" placeholder="{{ __('platform.plans_ui.custom_text_ph') }}">
                          @endif
                        </td>
                      </tr>
                    @endforeach
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </form>
@endsection
