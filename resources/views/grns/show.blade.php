@extends('layouts.admin')

@section('title', __('purchases.grn_detail_tab_title', ['number' => $grn->receipt_number]))
@section('page_title', __('purchases.grn_title') . ': ' . $grn->receipt_number)
@section('page_subtitle', ($grn->warehouse->code ?? '') . ' - ' . ($grn->warehouse->name ?? '-') . ' • ' . __('purchases.supplier') . ': ' . ($grn->purchase->supplier->name ?? '-'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('grns.index') }}">{{ __('purchases.goods_receiving_nav') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $grn->receipt_number }}</li>
@endsection

@section('actions')
  <a href="{{ route('grns.print', $grn) }}" class="btn btn-white">
    <i data-feather="printer" class="svg-14"></i> {{ __('purchases.print_grn_btn') }}
  </a>
  @if($grn->purchase && Auth::user()?->canAccessModule('purchase_orders', 'view') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
    <a href="{{ route('purchases.show', $grn->purchase) }}" class="btn btn-white">
      <i data-feather="file-text" class="svg-14"></i> {{ __('purchases.view_po_btn') }}
    </a>
  @endif
  <a href="{{ route('grns.index') }}" class="btn btn-white">
    <i data-feather="list" class="svg-14"></i> {{ __('purchases.all_grns_btn') }}
  </a>
@endsection

@section('content')
  @if($reversalAdjustment || $grn->status === 'cancelled')
    <div class="alert alert-warning mg-b-20" role="alert">
      <strong>{{ __('purchases.reversed_alert_prefix') }}</strong> {{ __('purchases.reversed_alert_body') }}
      @if($reversalAdjustment)
        @if(Auth::user()?->canAccessModule('stock_adjustments', 'view') && ($planFeatures['module_stock_adjustments']['enabled'] ?? false))
          <a href="{{ route('adjustments.show', $reversalAdjustment) }}" class="tx-semibold">{{ $reversalAdjustment->adjustment_number }}</a>
        @else
          <strong class="tx-semibold">{{ $reversalAdjustment->adjustment_number }}</strong>
        @endif
        ({{ $reversalAdjustment->reason }}).
      @else
        {{ __('purchases.reversed_alert_fallback') }}
      @endif
    </div>
  @endif

  <div class="row row-sm mg-b-20">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('purchases.grn_header_title') }}</h6>
          @if($reversalAdjustment)
            @include('partials.status-badge', ['status' => 'REVERSED'])
          @else
            @include('partials.status-badge', ['status' => $grn->status])
          @endif
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-10">
            <tr>
              <td class="tx-color-03 pd-l-0">{{ __('purchases.po_number') }}</td>
              <td class="text-right tx-medium">
                @if($grn->purchase && Auth::user()?->canAccessModule('purchase_orders', 'view') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
                  <a href="{{ route('purchases.show', $grn->purchase) }}">{{ $grn->purchase->purchase_number }}</a>
                @elseif($grn->purchase)
                  {{ $grn->purchase->purchase_number }}
                @else - @endif
              </td>
            </tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.supplier') }}</td><td class="text-right">{{ $grn->purchase->supplier->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.warehouse') }}</td><td class="text-right">{{ $grn->warehouse->code ?? '' }} - {{ $grn->warehouse->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.received_by') }}</td><td class="text-right">{{ $grn->receivedBy->name ?? __('app.system') }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.posted_at_label') }}</td><td class="text-right">{{ $grn->posted_at ? \App\Support\StockoraFormat::dateTime($grn->posted_at) : '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.notes') }}</td><td class="text-right">{{ $grn->notes ?: '-' }}</td></tr>
          </table>

          @if($grn->status === 'posted' && !$reversalAdjustment && Auth::user()?->canAccessModule('grn', 'approve'))
            <hr class="mg-y-12">
            <form action="{{ route('grns.reverse', $grn) }}" method="POST"
                  data-confirm-title="{{ __('purchases.confirm_reverse_grn_title', ['number' => $grn->receipt_number]) }}"
                  data-confirm="{{ __('purchases.confirm_reverse_grn_msg') }}">
              @csrf
              <div class="form-group mg-b-10">
                <label class="tx-12 tx-medium">{{ __('purchases.reversal_reason_label') }} <span class="tx-danger">*</span></label>
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('purchases.reversal_reason_placeholder') }}" required>
              </div>
              <button type="submit" class="btn btn-sm btn-outline-danger btn-block">{{ __('purchases.reverse_grn_btn') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('purchases.ias2_card_title') }}</h6>
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm mg-b-0">
            <tr><td>{{ __('purchases.gross_merchandise_value') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($grn->total_gross_amount, 2) }}</td></tr>
            <tr><td>(&minus;) {{ __('purchases.less_purchase_discounts') }}</td><td class="text-right tx-numeric tx-danger">- {{ \App\Support\StockoraFormat::currency($grn->total_discount_amount, 2) }}</td></tr>
            <tr><td>(+) {{ __('purchases.plus_freight_delivery') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($grn->total_freight_amount, 2) }}</td></tr>
            <tr><td>(+) {{ __('purchases.plus_non_rec_tax') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($grn->total_non_recoverable_tax, 2) }}</td></tr>
            <tr class="tx-semibold bg-light"><td>{{ __('purchases.total_capitalized_inventory') }}</td><td class="text-right tx-numeric tx-brand-01">{{ \App\Support\StockoraFormat::currency($grn->total_capitalized_cost, 2) }}</td></tr>
            <tr><td class="tx-color-03">{{ __('purchases.rec_tax_excluded') }}</td><td class="text-right tx-numeric tx-color-03">{{ \App\Support\StockoraFormat::currency($grn->total_recoverable_tax, 2) }}</td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card card-hover mg-b-20">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="card-title mg-b-0">{{ __('purchases.received_items_title') }}</h6>
      <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($grn->items->count()) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mg-b-0">
        <thead>
          <tr>
            <th>{{ __('purchases.col_product') }}</th>
            <th class="text-right">{{ __('purchases.received_qty') }}</th>
            <th class="text-right">{{ __('purchases.col_conv_factor') }}</th>
            <th class="text-right">{{ __('purchases.col_base_qty') }}</th>
            <th class="text-right">{{ __('purchases.col_unit_price') }}</th>
            <th class="text-right">{{ __('purchases.col_line_capitalized_rp') }}</th>
            <th class="text-right">{{ __('purchases.col_unit_cost_base_rp') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($grn->items as $item)
            <tr>
              <td>
                <a href="{{ route('products.show', $item->product_id) }}" class="tx-medium tx-color-01">{{ $item->product->code ?? '-' }}</a>
                <div class="tx-11 tx-color-03">{{ $item->product->name ?? '-' }}</div>
              </td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($item->received_quantity, 4) }} {{ $item->receivedUnit->symbol ?? '' }}</td>
              <td class="text-right tx-numeric tx-12 tx-color-03">&times; {{ rtrim(rtrim((string) $item->conversion_factor, '0'), '.') }}</td>
              <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::number($item->base_quantity, 4) }} {{ $item->baseUnit->symbol ?? '' }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($item->unit_price, 2) }}</td>
              <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($item->capitalized_total_cost, 2) }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($item->unit_cost_base, 6) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header">
      <h6 class="card-title mg-b-0">{{ __('purchases.ledger_entries_title') }}</h6>
    </div>
    @if($movements->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('purchases.no_ledger_rows_title'),
          'description' => __('purchases.no_ledger_rows_desc'),
          'icon' => 'activity',
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('purchases.col_product') }}</th>
              <th>{{ __('app.type') }}</th>
              <th class="text-right">{{ __('purchases.col_qty_in') }}</th>
              <th class="text-right">{{ __('operations.col_unit_cost') }}</th>
              <th class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
              <th class="text-right">{{ __('purchases.col_balance_after') }}</th>
              <th class="text-center">{{ __('operations.col_valuation_basis') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($movements as $mv)
              <tr>
                <td class="tx-medium">{{ $mv->product->code ?? '-' }}</td>
                <td>@include('partials.status-badge', ['status' => $mv->type])</td>
                <td class="text-right tx-numeric tx-success">+{{ \App\Support\StockoraFormat::number($mv->quantity, 4) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mv->unit_cost, 6) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($mv->total_cost, 2) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mv->running_stock_after, 4) }}</td>
                <td class="text-center">@include('partials.status-badge', ['status' => $mv->valuation_basis])</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection
