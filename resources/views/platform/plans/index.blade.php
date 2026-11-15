@extends('layouts.platform')

@section('title', __('platform.plans_title'))
@section('page_title', __('platform.plans_ui.heading'))
@section('page_subtitle', __('platform.plans_ui.subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('platform.plans_title') }}</li>
@endsection

@section('actions')
  <a href="{{ route('plans.index') }}" class="btn btn-white">
    <i data-feather="columns" class="svg-14"></i> {{ __('platform.plans_ui.public_comparison_btn') }}
  </a>
  <a href="{{ route('platform.features.index') }}" class="btn btn-white">
    <i data-feather="sliders" class="svg-14"></i> {{ __('platform.plans_ui.feature_catalog_btn') }}
  </a>
  <a href="{{ route('platform.plans.create') }}" class="btn btn-brand-01">
    <i data-feather="plus" class="svg-14"></i> {{ __('platform.plans_ui.create_plan_btn') }}
  </a>
@endsection

@section('content')
  <div class="alert alert-info tx-13 mg-b-20 d-flex align-items-start" style="gap: 10px;">
    <i data-feather="info" class="svg-18 flex-shrink-0 mg-t-2"></i>
    <div>
      <strong>{{ __('platform.plans_ui.provisional_notice_title') }}</strong> {{ __('platform.plans_ui.provisional_notice_body') }}
    </div>
  </div>

  {{-- Top KPI Cards --}}
  <div class="row row-sm mg-b-20">
    <div class="col-6 col-lg-3 mg-b-15 mg-lg-b-0">
      <div class="card stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('platform.plans_ui.total_plans') }}</div>
          <div class="stk-kpi-value tx-numeric">{{ $stats['totalPlans'] }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('platform.plans_ui.active_plans_sub', ['count' => $stats['activePlans']]) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3 mg-b-15 mg-lg-b-0">
      <div class="card stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('platform.plans_ui.recommended_plan') }}</div>
          <div class="tx-18 tx-bold tx-color-01">{{ $stats['recommendedPlan']?->name ?? __('platform.plans_ui.none_set') }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ $stats['recommendedPlan']?->code ?? '—' }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('platform.plans_ui.assigned_companies') }}</div>
          <div class="stk-kpi-value tx-numeric">{{ $stats['assignedCompanies'] }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('platform.plans_ui.linked_tenants_sub') }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-3">
      <div class="card stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('platform.plans_ui.catalog_features') }}</div>
          <div class="stk-kpi-value tx-numeric">{{ $stats['activeFeatures'] }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('platform.plans_ui.active_catalog_sub') }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.plans_ui.configured_plans') }}</h6>
        <span class="tx-12 tx-color-03">{{ __('platform.plans_ui.configured_plans_sub') }}</span>
      </div>
    </div>
    <div class="card-body p-0">
      @if($plans->isEmpty())
        <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.plans_ui.no_plans') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('platform.plans_ui.col_plan_name') }}</th>
                <th>{{ __('platform.plans_ui.col_code_tier') }}</th>
                <th>{{ __('platform.plans_ui.col_price') }}</th>
                <th>{{ __('platform.plans_ui.col_interval') }}</th>
                <th class="text-center">{{ __('platform.plans_ui.col_companies') }}</th>
                <th>{{ __('app.status') }}</th>
                <th>{{ __('platform.plans_ui.col_indicator') }}</th>
                <th class="text-right">{{ __('app.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($plans as $plan)
                <tr>
                  <td>
                    <a href="{{ route('platform.plans.show', $plan) }}" class="tx-semibold tx-color-01 d-block">{{ $plan->name }}</a>
                    <div class="tx-11 tx-color-03" style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($plan->description ?? '—', 75) }}</div>
                  </td>
                  <td>
                    <span class="stk-tenant-code">{{ $plan->code }}</span>
                    <div class="tx-11 tx-color-03 mg-t-4">{{ __('platform.plans_ui.tier_inline') }} @include('partials.access-type-badge', ['accessType' => $plan->tier])</div>
                  </td>
                  <td class="tx-semibold tx-color-01 tx-numeric text-nowrap">{{ $plan->formattedPrice() }}</td>
                  <td class="tx-12">{{ $plan->billingIntervalLabel() }}</td>
                  <td class="text-center tx-numeric">
                    <span class="badge badge-light">{{ $plan->companies_count }}</span>
                  </td>
                  <td>
                    @if($plan->is_active)
                      <span class="badge badge-success">{{ __('app.active') }}</span>
                    @else
                      <span class="badge badge-secondary">{{ __('app.inactive') }}</span>
                    @endif
                  </td>
                  <td>
                    @if($plan->is_internal)
                      <span class="badge badge-dark">{{ __('platform.plans_ui.internal_badge') }}</span>
                    @elseif($plan->is_recommended)
                      <span class="badge badge-primary">{{ __('platform.plans_ui.recommended_badge') }}</span>
                    @else
                      <span class="tx-12 tx-color-03">&mdash;</span>
                    @endif
                  </td>
                  <td class="text-right text-nowrap">
                    <div class="d-inline-flex align-items-center" style="gap: 4px;">
                      <a href="{{ route('platform.plans.show', $plan) }}" class="btn btn-white btn-xs">{{ __('app.view') }}</a>
                      <a href="{{ route('platform.plans.edit', $plan) }}" class="btn btn-brand-01 btn-xs">{{ __('platform.plans_ui.configure_btn') }}</a>
                      @if($plan->is_active)
                        <form action="{{ route('platform.plans.deactivate', $plan) }}" method="POST" class="d-inline">
                          @csrf
                          <button type="submit" class="btn btn-white btn-xs text-danger">{{ __('platform.plans_ui.deactivate_btn') }}</button>
                        </form>
                      @else
                        <form action="{{ route('platform.plans.activate', $plan) }}" method="POST" class="d-inline">
                          @csrf
                          <button type="submit" class="btn btn-white btn-xs text-success">{{ __('platform.plans_ui.activate_btn') }}</button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>
@endsection
