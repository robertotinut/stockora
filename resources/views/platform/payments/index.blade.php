@extends('layouts.platform')

@section('title', __('payments.platform.index_title'))
@section('page_title', __('payments.platform.index_heading'))
@section('page_subtitle', __('payments.platform.index_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('payments.platform.index_breadcrumb') }}</li>
@endsection

@section('actions')
  <a href="{{ route('platform.settings.index') }}#paymentInstructionsSection" class="btn btn-white">
    <i data-feather="sliders" class="svg-14"></i> {{ __('payments.platform.settings_instructions_btn') }}
  </a>
  <a href="{{ route('platform.payments.create') }}" class="btn btn-brand-01">
    <i data-feather="plus" class="svg-14"></i> {{ __('payments.platform.record_offline_btn') }}
  </a>
@endsection

@section('content')
  {{-- KPI Row --}}
  <div class="row row-sm mg-b-20">
    <div class="col-sm-6 col-lg-3 mg-b-15 mg-lg-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('payments.platform.kpi_pending') }}</div>
          <div class="stk-kpi-value tx-numeric text-warning">{{ \App\Support\StockoraFormat::number($stats['pending']) }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('payments.platform.kpi_pending_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3 mg-b-15 mg-lg-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('payments.platform.kpi_verified_unapplied') }}</div>
          <div class="stk-kpi-value tx-numeric text-primary">{{ \App\Support\StockoraFormat::number($stats['verified_unapplied']) }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('payments.platform.kpi_verified_unapplied_sub') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3 mg-b-15 mg-sm-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('payments.platform.kpi_verified_total') }}</div>
          <div class="stk-kpi-value tx-numeric text-success" style="font-size: 20px;">{{ \App\Support\StockoraFormat::currency($stats['verified_amount'], 0) }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('payments.platform.kpi_verified_total_sub', ['count' => \App\Support\StockoraFormat::number($stats['verified_count'])]) }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-lg-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="stk-kpi-label">{{ __('payments.platform.kpi_rejected') }}</div>
          <div class="stk-kpi-value tx-numeric text-danger">{{ \App\Support\StockoraFormat::number($stats['rejected']) }}</div>
          <div class="tx-11 tx-color-03 mg-t-4">{{ __('payments.platform.kpi_rejected_sub') }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Multi-Filter Bar --}}
  <div class="card mg-b-20">
    <div class="card-body">
      <form action="{{ route('platform.payments.index') }}" method="GET">
        <div class="row row-sm align-items-end">
          <div class="col-md-3 form-group">
            <label for="search">{{ __('payments.platform.filter_search') }}</label>
            <input type="text" name="search" id="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="{{ __('payments.platform.filter_search_ph') }}">
          </div>
          <div class="col-md-2 form-group">
            <label for="status">{{ __('payments.platform.filter_status') }}</label>
            <select name="status" id="status" class="form-control form-control-sm">
              <option value="">{{ __('platform.common.all') }}</option>
              @foreach(\App\Models\SubscriptionPayment::STATUSES as $stKey => $stMeta)
                <option value="{{ $stKey }}" {{ $status === $stKey ? 'selected' : '' }}>{{ __('payments.statuses.' . $stKey) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 form-group">
            <label for="request_type">{{ __('payments.platform.filter_type') }}</label>
            <select name="request_type" id="request_type" class="form-control form-control-sm">
              <option value="">{{ __('platform.common.all') }}</option>
              @foreach(\App\Models\SubscriptionPayment::REQUEST_TYPES as $rtKey => $rtLabel)
                <option value="{{ $rtKey }}" {{ $requestType === $rtKey ? 'selected' : '' }}>{{ __('payments.request_types.' . $rtKey) }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 form-group">
            <label for="company_id">{{ __('payments.platform.filter_company') }}</label>
            <select name="company_id" id="company_id" class="form-control form-control-sm">
              <option value="">{{ __('platform.common.all') }}</option>
              @foreach($companies as $co)
                <option value="{{ $co->id }}" {{ $companyId === (int) $co->id ? 'selected' : '' }}>{{ $co->name }} ({{ $co->code }})</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2 form-group">
            <label for="subscription_plan_id">{{ __('payments.platform.filter_plan') }}</label>
            <select name="subscription_plan_id" id="subscription_plan_id" class="form-control form-control-sm">
              <option value="">{{ __('platform.common.all') }}</option>
              @foreach($plans as $pl)
                <option value="{{ $pl->id }}" {{ $planId === (int) $pl->id ? 'selected' : '' }}>{{ $pl->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-2 form-group mb-md-0">
            <label for="applied_state">{{ __('payments.platform.filter_applied') }}</label>
            <select name="applied_state" id="applied_state" class="form-control form-control-sm">
              <option value="">{{ __('platform.common.all') }}</option>
              <option value="unapplied" {{ $appliedState === 'unapplied' ? 'selected' : '' }}>{{ __('payments.platform.filter_unapplied_only') }}</option>
              <option value="applied" {{ $appliedState === 'applied' ? 'selected' : '' }}>{{ __('payments.platform.filter_applied_only') }}</option>
            </select>
          </div>
          <div class="col-md-2 form-group mb-md-0">
            <label for="date_from">{{ __('payments.platform.filter_date_from') }}</label>
            <input type="date" name="date_from" id="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
          </div>
          <div class="col-md-2 form-group mb-md-0">
            <label for="date_to">{{ __('payments.platform.filter_date_to') }}</label>
            <input type="date" name="date_to" id="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
          </div>
          <div class="col-md-6 d-flex align-items-end justify-content-md-end" style="gap: 8px;">
            <button type="submit" class="btn btn-brand-01 btn-sm">{{ __('payments.platform.filter_btn') }}</button>
            <a href="{{ route('platform.payments.index') }}" class="btn btn-white btn-sm">{{ __('payments.platform.reset_btn') }}</a>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Ledger Table --}}
  <div class="card">
    <div class="card-body p-0">
      @if($payments->isEmpty())
        <div class="p-4 text-center tx-13 tx-color-03">{{ __('payments.platform.no_records') }}</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>{{ __('payments.customer.col_reference') }}</th>
                <th>{{ __('platform.dashboard.col_company') }}</th>
                <th>{{ __('payments.customer.col_plan_period') }}</th>
                <th>{{ __('payments.customer.col_method') }}</th>
                <th class="text-right">{{ __('payments.customer.col_amount') }}</th>
                <th>{{ __('payments.customer.col_payment_date') }}</th>
                <th>{{ __('payments.customer.col_status') }}</th>
                <th class="text-right">{{ __('payments.customer.col_actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($payments as $pay)
                <tr>
                  <td>
                    <a href="{{ route('platform.payments.show', $pay) }}" class="tx-semibold tx-color-01">{{ $pay->payment_reference }}</a>
                    <div class="tx-11 tx-color-03">{{ $pay->requestTypeLabel() }} &bull; {{ $pay->sourceLabel() }}</div>
                  </td>
                  <td>
                    @if($pay->company)
                      <a href="{{ route('platform.customers.show', $pay->company) }}" class="tx-semibold tx-color-01">{{ $pay->company->name }}</a>
                      <div class="tx-11 tx-color-03">
                        <span class="stk-tenant-code">{{ $pay->company->code }}</span>
                        @include('partials.company-status-badge', ['status' => $pay->company->effectiveStatus()])
                      </div>
                    @endif
                  </td>
                  <td>
                    <div class="tx-semibold tx-color-01">{{ $pay->plan_name_snapshot }}</div>
                    <div class="tx-11 tx-color-03">{{ $pay->billingIntervalLabel() }} &bull; {{ __('payments.checkout.summary_days', ['days' => $pay->duration_days]) }}</div>
                  </td>
                  <td class="tx-12">
                    <div>{{ $pay->payment_method_name }}</div>
                    @if($pay->customer_transfer_reference)
                      <div class="tx-11 tx-color-03">{{ $pay->customer_transfer_reference }}</div>
                    @endif
                  </td>
                  <td class="text-right tx-numeric tx-semibold">{{ $pay->formattedAmount() }}</td>
                  <td class="tx-12">{{ \App\Support\StockoraFormat::shortDate($pay->payment_date) }}</td>
                  <td>
                    <span class="badge {{ $pay->statusBadgeClass() }}">{{ $pay->statusLabel() }}</span>
                    @if($pay->isVerified())
                      <div class="tx-11 mg-t-4">
                        @if($pay->isApplied())
                          <span class="badge badge-light text-success">{{ __('payments.customer.applied_badge') }}</span>
                        @else
                          <span class="badge badge-light text-warning">{{ __('payments.customer.awaiting_apply_badge') }}</span>
                        @endif
                      </div>
                    @endif
                  </td>
                  <td class="text-right">
                    <a href="{{ route('platform.payments.show', $pay) }}" class="btn btn-xs {{ $pay->isPending() || ($pay->isVerified() && !$pay->isApplied()) ? 'btn-brand-01' : 'btn-white' }}">
                      {{ __('payments.platform.review_btn') }}
                    </a>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @if($payments->hasPages())
          <div class="card-footer">
            {{ $payments->links() }}
          </div>
        @endif
      @endif
    </div>
  </div>
@endsection
