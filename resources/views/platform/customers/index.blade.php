@extends('layouts.platform')

@php
  $pageHeading = match ($mode) {
      'pending' => __('platform.customers.mode_pending_title'),
      'access' => __('platform.customers.mode_access_title'),
      default => __('platform.customers.mode_default_title'),
  };
  $pageSub = match ($mode) {
      'pending' => __('platform.customers.mode_pending_sub'),
      'access' => __('platform.customers.mode_access_sub'),
      default => __('platform.customers.mode_default_sub'),
  };
  $baseRoute = match ($mode) {
      'pending' => route('platform.pending.index'),
      'access' => route('platform.access.index'),
      default => route('platform.customers.index'),
  };
@endphp

@section('title', $pageHeading)
@section('page_title', $pageHeading)
@section('page_subtitle', $pageSub)

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ $pageHeading }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.customers.create') }}" class="btn btn-brand-01">
    <i data-feather="plus" class="svg-14"></i> {{ __('platform.customers.create_account_btn') }}
  </a>
@endsection

@section('content')
  @if($mode !== 'pending')
    <div class="d-flex flex-wrap mg-b-15" style="gap: 8px;">
      <a href="{{ $baseRoute }}" class="btn btn-xs {{ $status === '' && !$expiringSoon ? 'btn-brand-01' : 'btn-white' }}">
        {{ __('app.all') }} ({{ (int) $counts->sum() }})
      </a>
      @foreach($statuses as $sKey => $sMeta)
        <a href="{{ $baseRoute }}?status={{ $sKey }}" class="btn btn-xs {{ $status === $sKey ? 'btn-brand-01' : 'btn-white' }}">
          {{ __('platform.account_statuses.' . $sKey) }} ({{ (int) ($counts[$sKey] ?? 0) }})
        </a>
      @endforeach
      <a href="{{ $baseRoute }}?expiring_soon=1" class="btn btn-xs {{ $expiringSoon ? 'btn-warning' : 'btn-white' }}">
        {{ __('platform.customers.expiring_14d') }}
      </a>
    </div>
  @endif

  <div class="card mg-b-20">
    <div class="card-body">
      <form action="{{ $baseRoute }}" method="GET" class="row row-sm align-items-end">
        <div class="col-md-5 mg-b-10 mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.customers.search_label') }}</label>
          <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('platform.customers.search_placeholder') }}">
        </div>
        @if($mode !== 'pending')
          <div class="col-md-2 mg-b-10 mg-md-b-0">
            <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.customers.account_status_label') }}</label>
            <select name="status" class="form-control">
              <option value="">{{ __('platform.customers.all_statuses') }}</option>
              @foreach($statuses as $sKey => $sMeta)
                <option value="{{ $sKey }}" {{ $status === $sKey ? 'selected' : '' }}>{{ __('platform.account_statuses.' . $sKey) }}</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="col-md-2 mg-b-10 mg-md-b-0">
          <label class="tx-12 tx-semibold tx-color-02">{{ __('platform.customers.access_type_label') }}</label>
          <select name="access_type" class="form-control">
            <option value="">{{ __('platform.customers.all_tiers') }}</option>
            @foreach($accessTypes as $tKey => $tMeta)
              <option value="{{ $tKey }}" {{ $accessType === $tKey ? 'selected' : '' }}>{{ __('platform.access_types.' . $tKey) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 d-flex" style="gap: 8px;">
          <button type="submit" class="btn btn-brand-01 flex-grow-1">{{ __('app.filter') }}</button>
          <a href="{{ $baseRoute }}" class="btn btn-white">{{ __('app.reset') }}</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      @if($companies->isEmpty())
        <div class="stk-empty-state">
          <div class="stk-empty-icon"><i data-feather="briefcase" class="svg-22"></i></div>
          <div class="stk-empty-title">{{ __('platform.customers.empty_title') }}</div>
          <div class="stk-empty-desc">
            {{ __('platform.customers.empty_desc') }}
          </div>
          <a href="{{ route('platform.customers.create') }}" class="btn btn-brand-01 btn-sm">
            <i data-feather="plus" class="svg-14"></i> {{ __('platform.customers.direct_onboarding_btn') }}
          </a>
        </div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('platform.dashboard.col_company') }}</th>
                <th>{{ __('platform.dashboard.col_owner') }}</th>
                <th>{{ __('app.status') }}</th>
                <th>{{ __('platform.customers.access_type_label') }}</th>
                <th>{{ __('platform.customers.col_access_window') }}</th>
                <th class="text-center">{{ __('platform.customers.col_users_wh') }}</th>
                <th class="text-right">{{ __('platform.customers.col_manage') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($companies as $co)
                @php
                  $owner = $co->users->first();
                  $effStatus = $co->effectiveStatus();
                @endphp
                <tr>
                  <td>
                    <div class="d-flex align-items-center" style="gap: 10px;">
                      <div class="stk-avatar-initial">{{ $co->initials() }}</div>
                      <div>
                        <a href="{{ route('platform.customers.show', $co) }}" class="tx-semibold tx-color-01 d-block">{{ $co->name }}</a>
                        <span class="stk-tenant-code">{{ $co->code }}</span>
                        @if($co->industry)
                          <span class="tx-11 tx-color-03 mg-l-4">{{ $co->industry }}</span>
                        @endif
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="tx-13 tx-color-01">{{ $owner?->name ?? __('platform.customers.unassigned') }}</div>
                    <div class="tx-11 tx-color-03">{{ $owner?->email ?? '—' }}</div>
                  </td>
                  <td>@include('partials.company-status-badge', ['status' => $effStatus])</td>
                  <td>
                    @include('partials.access-type-badge', ['accessType' => $co->access_type])
                    @if($co->subscriptionPlan)
                      <div class="tx-11 tx-color-03 mg-t-4">
                        {{ __('platform.customers.plan_inline') }} <a href="{{ route('platform.plans.show', $co->subscriptionPlan) }}" class="tx-semibold">{{ $co->subscriptionPlan->name }}</a>
                      </div>
                    @endif
                  </td>
                  <td class="tx-12">
                    <div>{{ __('platform.customers.start_inline') }} <span class="tx-color-01">{{ $co->access_starts_at ? \App\Support\StockoraFormat::shortDate($co->access_starts_at) : __('platform.common.not_started') }}</span></div>
                    <div class="{{ $co->access_expires_at && $co->access_expires_at->isBefore(now()->addDays(7)) ? 'text-danger tx-semibold' : 'tx-color-03' }}">
                      {{ __('platform.customers.expires_inline') }} {{ $co->access_expires_at ? \App\Support\StockoraFormat::shortDate($co->access_expires_at) : __('platform.common.unlimited') }}
                    </div>
                  </td>
                  <td class="text-center tx-numeric tx-12">
                    <span class="badge badge-light">{{ __('platform.customers.users_badge', ['count' => $co->active_members_count]) }}</span>
                    <span class="badge badge-light">{{ __('platform.customers.wh_badge', ['count' => $co->warehouses_count]) }}</span>
                  </td>
                  <td class="text-right">
                    <a href="{{ route('platform.customers.show', $co) }}" class="btn btn-xs {{ $effStatus === 'PENDING_ACTIVATION' ? 'btn-brand-01' : 'btn-white' }}">
                      {{ $effStatus === 'PENDING_ACTIVATION' ? __('platform.customers.activate_review_btn') : __('platform.customers.inspect_btn') }}
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @if($companies->hasPages())
          <div class="card-footer d-flex justify-content-between align-items-center">
            <span class="tx-12 tx-color-03">{{ __('platform.common.showing_customers', ['from' => $companies->firstItem(), 'to' => $companies->lastItem(), 'total' => $companies->total()]) }}</span>
            {{ $companies->links() }}
          </div>
        @endif
      @endif
    </div>
  </div>
@endsection
