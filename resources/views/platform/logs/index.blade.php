@extends('layouts.platform')

@section('title', __('platform.logs_title'))
@section('page_title', __('platform.logs.heading'))
@section('page_subtitle', __('platform.logs.subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('platform.logs.breadcrumb') }}</li>
@endsection

@section('content')
  <div class="card mg-b-20">
    <div class="card-body">
      <form action="{{ route('platform.logs.index') }}" method="GET" class="row row-sm align-items-end">
        <div class="col-md-3 form-group mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.logs.company_label') }}</label>
          <select name="company_id" class="form-control">
            <option value="">{{ __('platform.logs.all_companies') }}</option>
            @foreach($companies as $co)
              <option value="{{ $co->id }}" {{ $companyId === (int) $co->id ? 'selected' : '' }}>{{ $co->code }} &mdash; {{ $co->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 form-group mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.logs.action_type_label') }}</label>
          <select name="action" class="form-control">
            <option value="">{{ __('platform.logs.all_actions') }}</option>
            @foreach($availableActions as $act)
              <option value="{{ $act }}" {{ $action === $act ? 'selected' : '' }}>{{ $act }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4 form-group mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.logs.keyword_label') }}</label>
          <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('platform.logs.keyword_ph') }}">
        </div>
        <div class="col-md-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-brand-01 flex-grow-1">{{ __('app.filter') }}</button>
          <a href="{{ route('platform.logs.index') }}" class="btn btn-white">{{ __('app.reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @if($logs->isEmpty())
        <div class="p-4 text-center tx-13 tx-color-03">{{ __('platform.logs.no_entries') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('platform.customer_show.col_timestamp') }}</th>
                <th>{{ __('app.action') }}</th>
                <th>{{ __('platform.logs.col_target_entity') }}</th>
                <th>{{ __('platform.dashboard.col_actor') }}</th>
                <th>{{ __('platform.customer_show.col_note') }}</th>
                <th>{{ __('platform.logs.col_state_diff') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($logs as $log)
                <tr>
                  <td class="tx-12 tx-color-03 text-nowrap">{{ $log->created_at ? \App\Support\StockoraFormat::dateTime($log->created_at, true) : '—' }}</td>
                  <td><span class="badge badge-light">{{ $log->action }}</span></td>
                  <td>
                    @if($log->company)
                      <a href="{{ route('platform.customers.show', $log->company) }}" class="tx-semibold">{{ $log->company->name }}</a>
                      <div class="tx-11 tx-color-03">{{ $log->company->code }}</div>
                    @elseif($log->subscriptionPlan)
                      <a href="{{ route('platform.plans.show', $log->subscriptionPlan) }}" class="tx-semibold">{{ __('platform.common.plan_prefix') }}: {{ $log->subscriptionPlan->name }}</a>
                      <div class="tx-11 tx-color-03">{{ $log->subscriptionPlan->code }}</div>
                    @elseif($log->subscriptionFeature)
                      <a href="{{ route('platform.features.index') }}" class="tx-semibold">{{ __('platform.common.feature_prefix') }}: {{ $log->subscriptionFeature->name }}</a>
                      <div class="tx-11 tx-color-03"><code>{{ $log->subscriptionFeature->feature_key }}</code></div>
                    @else
                      <span class="tx-color-03">&mdash;</span>
                    @endif
                  </td>
                  <td class="tx-12">
                    @if($log->platformAdmin)
                      <div class="tx-semibold">{{ $log->platformAdmin->name }}</div>
                      <div class="tx-11 tx-color-03">{{ $log->platformAdmin->email }}</div>
                    @elseif($log->actorUser)
                      <div class="tx-semibold">{{ $log->actorUser->name }}</div>
                      <div class="tx-11 tx-color-03">{{ $log->actorUser->email }}</div>
                    @else
                      <span class="tx-color-03">{{ __('platform.common.automated_system') }}</span>
                    @endif
                  </td>
                  <td class="tx-12 tx-color-02">{{ $log->note ?? '—' }}</td>
                  <td class="tx-11">
                    @if($log->previous_values)
                      <div class="tx-color-03">{{ __('platform.logs.diff_from') }} <code>{{ \Illuminate\Support\Str::limit(json_encode($log->previous_values), 65) }}</code></div>
                    @endif
                    @if($log->new_values)
                      <div class="tx-color-01">{{ __('platform.logs.diff_to') }} <code>{{ \Illuminate\Support\Str::limit(json_encode($log->new_values), 65) }}</code></div>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($logs->hasPages())
          <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="tx-12 tx-color-03">{{ __('platform.common.showing_entries', ['from' => $logs->firstItem(), 'to' => $logs->lastItem(), 'total' => $logs->total()]) }}</span>
            {{ $logs->links() }}
          </div>
        @endif
      @endif
    </div>
  </div>
@endsection
