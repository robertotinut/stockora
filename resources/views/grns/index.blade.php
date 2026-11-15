@extends('layouts.admin')

@section('title', __('purchases.grn_title'))
@section('page_title', __('purchases.grn_list_heading'))
@section('page_subtitle', __('purchases.grn_index_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('purchase_orders', 'view') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">{{ __('purchases.page_title') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('purchases.goods_receiving_nav') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('grn', 'create'))
    <a href="{{ route('grns.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('purchases.receive_goods_action') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('grns.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-4 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.grn_search_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('purchases.grn_search_placeholder') }}">
        </div>
        <div class="col-sm-6 col-md-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.warehouse') }}</label>
          <select name="warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('purchases.all_warehouses') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $warehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-8 col-md-3 mg-b-10 mg-sm-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.status') }}</label>
          <select name="status" class="form-control form-control-sm">
            <option value="">{{ __('purchases.all_statuses') }}</option>
            <option value="posted" {{ $status === 'posted' ? 'selected' : '' }}>{{ __('purchases.grn_status_posted') }}</option>
            <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>{{ __('purchases.grn_status_cancelled') }}</option>
          </select>
        </div>
        <div class="col-sm-4 col-md-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $warehouseId !== '' || $status !== '')
            <a href="{{ route('grns.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('purchases.grn_list_heading') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($receipts->total()) }}</span>
      </div>
    </div>

    @if($receipts->isEmpty())
      <div class="card-body">
        @php
          $isFiltered = $search !== '' || $warehouseId !== '' || $status !== '';
          $emptyActionUrl = $isFiltered
              ? route('grns.index')
              : (Auth::user()?->canAccessModule('grn', 'create') ? route('grns.create') : null);
        @endphp
        @include('partials.empty-state', [
          'title' => __('purchases.no_grns_list_title'),
          'description' => __('purchases.no_grns_list_desc'),
          'icon' => 'check-square',
          'actionUrl' => $emptyActionUrl,
          'actionLabel' => $isFiltered ? __('app.reset_filters') : __('purchases.receive_goods_action'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('purchases.receipt_number') }}</th>
              <th>{{ __('purchases.col_po_supplier') }}</th>
              <th>{{ __('purchases.col_warehouse') }}</th>
              <th class="text-right">{{ __('purchases.col_gross_rp') }}</th>
              <th class="text-right">{{ __('purchases.col_capitalized_cost_rp') }}</th>
              <th class="text-center">{{ __('purchases.status') }}</th>
              <th>{{ __('purchases.col_posted_user') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($receipts as $grn)
              <tr>
                <td><a href="{{ route('grns.show', $grn) }}" class="tx-medium tx-color-01">{{ $grn->receipt_number }}</a></td>
                <td>
                  @if($grn->purchase)
                    @if(Auth::user()?->canAccessModule('purchase_orders', 'view') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
                      <a href="{{ route('purchases.show', $grn->purchase) }}" class="tx-medium">{{ $grn->purchase->purchase_number }}</a>
                    @else
                      <span class="tx-medium">{{ $grn->purchase->purchase_number }}</span>
                    @endif
                    <div class="tx-11 tx-color-03">{{ $grn->purchase->supplier->name ?? '-' }}</div>
                  @else - @endif
                </td>
                <td><span class="badge badge-light">{{ $grn->warehouse->code ?? '-' }}</span></td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($grn->total_gross_amount, 2) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($grn->total_capitalized_cost, 2) }}</td>
                <td class="text-center">
                  @if($grn->reversalAdjustment)
                    @include('partials.status-badge', ['status' => 'REVERSED'])
                  @else
                    @include('partials.status-badge', ['status' => $grn->status])
                  @endif
                </td>
                <td class="tx-12">
                  <div>{{ $grn->posted_at ? \App\Support\StockoraFormat::dateTime($grn->posted_at) : '-' }}</div>
                  <div class="tx-color-03">{{ $grn->receivedBy->name ?? __('app.system') }}</div>
                </td>
                <td class="text-right">
                  <a href="{{ route('grns.show', $grn) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($receipts->hasPages())
      <div class="card-footer">{{ $receipts->links() }}</div>
    @endif
  </div>
@endsection
