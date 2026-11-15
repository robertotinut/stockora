@extends('layouts.admin')

@section('title', __('purchases.page_title'))
@section('page_title', __('purchases.page_title'))
@section('page_subtitle', __('purchases.index_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('purchases.page_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('grn', 'view') && ($planFeatures['module_grn']['enabled'] ?? false))
    <a href="{{ route('grns.index') }}" class="btn btn-white">
      <i data-feather="check-square" class="svg-14"></i> {{ __('purchases.goods_receiving_nav') }}
    </a>
  @endif
  @if(Auth::user()?->canAccessModule('purchase_orders', 'create'))
    <a href="{{ route('purchases.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('purchases.create_title') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('purchases.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.search_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('purchases.search_placeholder') }}">
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.po_status_label') }}</label>
          <select name="status" class="form-control form-control-sm">
            <option value="">{{ __('purchases.all_statuses') }}</option>
            @foreach(['pending', 'ordered', 'partial', 'received', 'cancelled'] as $st)
              <option value="{{ $st }}" {{ $status === $st ? 'selected' : '' }}>{{ __('purchases.statuses.' . $st) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.payment_short') }}</label>
          <select name="payment_status" class="form-control form-control-sm">
            <option value="">{{ __('purchases.all_payments') }}</option>
            @foreach(['unpaid', 'partial', 'paid'] as $pst)
              <option value="{{ $pst }}" {{ $paymentStatus === $pst ? 'selected' : '' }}>{{ __('purchases.payment_statuses.' . $pst) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-8 col-xl-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.audit_compliance') }}</label>
          <select name="needs_review" class="form-control form-control-sm">
            <option value="">{{ __('purchases.all_pos') }}</option>
            <option value="1" {{ $needsReview === '1' ? 'selected' : '' }}>{{ __('purchases.needs_review_opt') }}</option>
          </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $status !== '' || $paymentStatus !== '' || $needsReview !== '')
            <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('purchases.page_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($purchases->total()) }}</span>
      </div>
    </div>

    @if($purchases->isEmpty())
      <div class="card-body">
        @php
          $isFiltered = $search !== '' || $status !== '' || $paymentStatus !== '' || $needsReview !== '';
          $emptyActionUrl = $isFiltered
              ? route('purchases.index')
              : (Auth::user()?->canAccessModule('purchase_orders', 'create') ? route('purchases.create') : null);
        @endphp
        @include('partials.empty-state', [
          'title' => __('purchases.no_pos_title'),
          'description' => __('purchases.no_pos_desc'),
          'icon' => 'shopping-cart',
          'actionUrl' => $emptyActionUrl,
          'actionLabel' => $isFiltered ? __('app.reset_filters') : __('purchases.create_title'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('purchases.po_number') }}</th>
              <th>{{ __('app.date') }}</th>
              <th>{{ __('purchases.supplier') }}</th>
              <th class="text-right">{{ __('purchases.col_lines_grns') }}</th>
              <th class="text-right">{{ __('purchases.col_total_rp') }}</th>
              <th class="text-center">{{ __('purchases.status') }}</th>
              <th class="text-center">{{ __('purchases.payment_short') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($purchases as $po)
              <tr>
                <td>
                  <a href="{{ route('purchases.show', $po) }}" class="tx-medium tx-color-01">{{ $po->purchase_number }}</a>
                  @if($po->review_items_count > 0)
                    <div class="mg-t-2"><span class="badge badge-warning">{{ __('purchases.items_need_review_badge', ['count' => $po->review_items_count]) }}</span></div>
                  @endif
                </td>
                <td class="tx-12 tx-color-03 text-nowrap">{{ \App\Support\StockoraFormat::shortDate($po->date) }}</td>
                <td class="tx-medium">{{ $po->supplier->name ?? '-' }}</td>
                <td class="text-right tx-numeric tx-12 tx-color-03">{{ __('purchases.items_grn_ratio', ['items' => \App\Support\StockoraFormat::number($po->items_count), 'grns' => \App\Support\StockoraFormat::number($po->receipts_count)]) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($po->total_amount, 2) }}</td>
                <td class="text-center">@include('partials.status-badge', ['status' => $po->status])</td>
                <td class="text-center">@include('partials.status-badge', ['status' => $po->payment_status])</td>
                <td class="text-right text-nowrap">
                  <a href="{{ route('purchases.show', $po) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                  @if(in_array($po->status, ['pending', 'ordered', 'partial'], true) && Auth::user()?->canAccessModule('grn', 'create') && ($planFeatures['module_grn']['enabled'] ?? false))
                    <a href="{{ route('grns.create', ['purchase_id' => $po->id]) }}" class="btn btn-xs btn-brand-01 mg-l-4">{{ __('purchases.receive_btn') }}</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($purchases->hasPages())
      <div class="card-footer">{{ $purchases->links() }}</div>
    @endif
  </div>
@endsection
