@extends('layouts.platform')

@section('title', __('platform.plans_ui.show_tab', ['name' => $plan->name]))
@section('page_title', $plan->name . ' (' . $plan->code . ')')
@section('page_subtitle', __('platform.plans_ui.show_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('platform.plans.index') }}">{{ __('platform.plans_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $plan->code }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.plans.index') }}" class="btn btn-white">
    <i data-feather="arrow-left" class="svg-14"></i> {{ __('platform.plans_ui.all_plans_btn') }}
  </a>
  <a href="{{ route('platform.plans.edit', $plan) }}" class="btn btn-brand-01">
    <i data-feather="edit-2" class="svg-14"></i> {{ __('platform.plans_ui.edit_plan_btn') }}
  </a>
@endsection

@section('content')
  <div class="card mg-b-20">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-start" style="gap: 16px;">
        <div>
          <div class="d-flex flex-wrap align-items-center mg-b-8" style="gap: 8px;">
            <h5 class="tx-bold tx-color-01 mb-0">{{ $plan->name }}</h5>
            <span class="stk-tenant-code">{{ $plan->code }}</span>
            @include('partials.access-type-badge', ['accessType' => $plan->tier])
            @if($plan->is_active)
              <span class="badge badge-success">{{ __('app.active') }}</span>
            @else
              <span class="badge badge-secondary">{{ __('app.inactive') }}</span>
            @endif
            @if($plan->is_internal)
              <span class="badge badge-dark">{{ __('platform.plans_ui.internal_badge') }}</span>
            @endif
            @if($plan->is_recommended)
              <span class="badge badge-primary">{{ __('platform.plans_ui.recommended_plan_badge') }}</span>
            @endif
          </div>
          <p class="tx-13 tx-color-02 mg-b-0">{{ $plan->description ?? __('platform.plans_ui.no_desc') }}</p>
        </div>
        <div class="text-md-right">
          <div class="tx-20 tx-bold tx-color-01 tx-numeric">{{ $plan->formattedPrice() }}</div>
          <div class="tx-12 tx-color-03">{{ $plan->billingIntervalLabel() }} &bull; {{ __('platform.plans_ui.sort_prefix', ['order' => $plan->sort_order]) }}</div>
          <div class="mg-t-8 d-inline-flex" style="gap: 6px;">
            @if($plan->is_active)
              <form action="{{ route('platform.plans.deactivate', $plan) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-white btn-xs text-danger">{{ __('platform.plans_ui.deactivate_btn') }}</button>
              </form>
            @else
              <form action="{{ route('platform.plans.activate', $plan) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-white btn-xs text-success">{{ __('platform.plans_ui.activate_btn') }}</button>
              </form>
            @endif
            @if(!$plan->isCoreSystemPlan() && $assignedCompanies->total() === 0)
              <form action="{{ route('platform.plans.destroy', $plan) }}" method="POST" data-confirm="{{ __('platform.plans_ui.confirm_delete_msg', ['code' => $plan->code]) }}" data-confirm-title="{{ __('platform.plans_ui.confirm_delete_title') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-white btn-xs text-danger">{{ __('app.delete') }}</button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row row-sm">
    <div class="col-lg-7 mg-b-20">
      @foreach($featuresByCategory as $catName => $items)
        @php
          $catKey = "subscription.categories.{$catName}";
          $catLabel = \Illuminate\Support\Facades\Lang::has($catKey) ? __($catKey) : $catName;
        @endphp
        <div class="card mg-b-20">
          <div class="card-header">
            <h6 class="mg-b-0 tx-semibold tx-color-01">{{ $catLabel }}</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('platform.plans_ui.col_feature') }}</th>
                    <th>{{ __('platform.plans_ui.col_key') }}</th>
                    <th class="text-right">{{ __('platform.plans_ui.col_configured_value') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($items as $feat)
                    @php
                      $featTrans = "subscription.features.{$feat->feature_key}";
                      $limTrans = "subscription.limits.{$feat->feature_key}";
                      $featLabel = \Illuminate\Support\Facades\Lang::has($featTrans)
                        ? __($featTrans)
                        : (\Illuminate\Support\Facades\Lang::has($limTrans) ? __($limTrans) : $feat->name);
                    @endphp
                    <tr>
                      <td>
                        <div class="tx-semibold tx-color-01">{{ $featLabel }}</div>
                        @if(isset($limitSpecs[$feat->feature_key]))
                          <div class="tx-11 tx-color-03">
                            {{ __('platform.plans_ui.table_inline') }} <code>{{ $limitSpecs[$feat->feature_key]['table'] }}</code> &bull; {{ $limitSpecs[$feat->feature_key]['basis'] }}
                          </div>
                        @endif
                      </td>
                      <td class="tx-12"><code>{{ $feat->feature_key }}</code></td>
                      <td class="text-right tx-13">
                        @if($feat->isBoolean())
                          @php $inc = in_array(strtolower((string) $feat->pivot?->value), ['1', 'true', 'yes', 'on'], true); @endphp
                          <span class="badge {{ $inc ? 'badge-success' : 'badge-light text-muted' }}">{{ $inc ? __('subscription.included') : __('subscription.not_included') }}</span>
                        @elseif($feat->isLimit())
                          @if($feat->pivot?->value === null || trim((string) $feat->pivot?->value) === '')
                            <span class="badge badge-primary">{{ __('subscription.unlimited') }}</span>
                          @else
                            <strong class="tx-numeric">{{ \App\Support\StockoraFormat::number((int) $feat->pivot?->value) }}</strong>
                          @endif
                        @else
                          <span>{{ $feat->formatDisplayValue($feat->pivot?->value) }}</span>
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

    <div class="col-lg-5">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mg-b-2 tx-semibold tx-color-01">{{ __('platform.plans_ui.assigned_companies') }}</h6>
            <span class="tx-12 tx-color-03">{{ __('platform.plans_ui.assigned_sub', ['code' => $plan->code]) }}</span>
          </div>
          <span class="badge badge-light">{{ __('platform.plans_ui.companies_badge', ['count' => $assignedCompanies->total()]) }}</span>
        </div>
        <div class="card-body p-0">
          @if($assignedCompanies->isEmpty())
            <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.plans_ui.no_assigned_companies') }}</div>
          @else
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead>
                  <tr>
                    <th>{{ __('platform.dashboard.col_company') }}</th>
                    <th>{{ __('app.status') }}</th>
                    <th>{{ __('platform.plans_ui.col_expires') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($assignedCompanies as $co)
                    <tr>
                      <td>
                        <a href="{{ route('platform.customers.show', $co) }}" class="tx-semibold tx-color-01 d-block">{{ $co->name }}</a>
                        <span class="stk-tenant-code">{{ $co->code }}</span>
                      </td>
                      <td>@include('partials.company-status-badge', ['status' => $co->effectiveStatus()])</td>
                      <td class="tx-12 tx-color-03">{{ $co->access_expires_at ? \App\Support\StockoraFormat::shortDate($co->access_expires_at) : __('platform.common.unlimited') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            @if($assignedCompanies->hasPages())
              <div class="card-footer">{{ $assignedCompanies->links() }}</div>
            @endif
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
