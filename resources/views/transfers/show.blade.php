@extends('layouts.admin')

@section('title', __('operations.transfer_detail_tab', ['number' => $transfer->transfer_number]))
@section('page_title', __('operations.transfer_heading', ['number' => $transfer->transfer_number]))
@section('page_subtitle', ($transfer->fromWarehouse->code ?? '') . ' -> ' . ($transfer->toWarehouse->code ?? '') . ' • ' . __('operations.posted_inline') . ' ' . ($transfer->posted_at ? \App\Support\StockoraFormat::dateTime($transfer->posted_at) : '-'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('transfers.index') }}">{{ __('operations.transfers_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $transfer->transfer_number }}</li>
@endsection

@section('actions')
  <a href="{{ route('transfers.print', $transfer) }}" class="btn btn-white">
    <i data-feather="printer" class="svg-14"></i> {{ __('operations.print_transfer_btn') }}
  </a>
  <a href="{{ route('transfers.index') }}" class="btn btn-white">
    <i data-feather="list" class="svg-14"></i> {{ __('operations.all_transfers_btn') }}
  </a>
@endsection

@section('content')
  @if($transfer->reversalAdjustment)
    <div class="alert alert-warning mg-b-20" role="alert">
      <strong>{{ __('operations.transfer_reversed_prefix') }}</strong> {{ __('operations.reversed_via_doc') }}
      @if(Auth::user()?->canAccessModule('stock_adjustments', 'view') && ($planFeatures['module_stock_adjustments']['enabled'] ?? false))
        <a href="{{ route('adjustments.show', $transfer->reversalAdjustment) }}" class="tx-semibold">{{ $transfer->reversalAdjustment->adjustment_number }}</a>
      @else
        <strong class="tx-semibold">{{ $transfer->reversalAdjustment->adjustment_number }}</strong>
      @endif
      ({{ $transfer->reversalAdjustment->reason }}).
    </div>
  @endif

  <div class="row row-sm mg-b-20">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('operations.transfer_header_card') }}</h6>
          @if($transfer->reversalAdjustment)
            @include('partials.status-badge', ['status' => 'REVERSED'])
          @else
            @include('partials.status-badge', ['status' => $transfer->status])
          @endif
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-10">
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.from_warehouse') }}</td><td class="text-right tx-medium">{{ $transfer->fromWarehouse->code ?? '' }} - {{ $transfer->fromWarehouse->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.to_warehouse') }}</td><td class="text-right tx-medium">{{ $transfer->toWarehouse->code ?? '' }} - {{ $transfer->toWarehouse->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.posted_by') }}</td><td class="text-right">{{ $transfer->user->name ?? __('app.system') }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.posted_at') }}</td><td class="text-right">{{ $transfer->posted_at ? \App\Support\StockoraFormat::dateTime($transfer->posted_at) : '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.notes') }}</td><td class="text-right">{{ $transfer->notes ?: '-' }}</td></tr>
          </table>

          @if($transfer->status === 'posted' && !$transfer->reversalAdjustment && Auth::user()?->canAccessModule('stock_transfers', 'approve'))
            <hr class="mg-y-12">
            <form action="{{ route('transfers.reverse', $transfer) }}" method="POST"
                  data-confirm-title="{{ __('operations.confirm_reverse_transfer_title', ['number' => $transfer->transfer_number]) }}"
                  data-confirm="{{ __('operations.confirm_reverse_transfer_msg') }}">
              @csrf
              <div class="form-group mg-b-10">
                <label class="tx-12 tx-medium">{{ __('operations.reversal_reason_label') }} <span class="tx-danger">*</span></label>
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('operations.reversal_reason_transfer_placeholder') }}" required>
              </div>
              <button type="submit" class="btn btn-sm btn-outline-danger btn-block">{{ __('operations.reverse_transfer_btn') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('operations.transferred_lines_title') }}</h6>
          <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($transfer->items->count()) }}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mg-b-0">
            <thead>
              <tr>
                <th>{{ __('purchases.col_product') }}</th>
                <th class="text-right">{{ __('purchases.col_qty') }}</th>
                <th class="text-right">{{ __('operations.col_base_qty') }}</th>
                <th class="text-right">{{ __('operations.col_carry_mac') }}</th>
                <th class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($transfer->items as $item)
                <tr>
                  <td>
                    <a href="{{ route('products.show', $item->product_id) }}" class="tx-medium tx-color-01">{{ $item->product->code ?? '-' }}</a>
                    <div class="tx-11 tx-color-03">{{ $item->product->name ?? '-' }}</div>
                  </td>
                  <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($item->quantity, 4) }} {{ $item->unit->symbol ?? '' }}</td>
                  <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($item->base_quantity, 4) }} {{ $item->product->unit->symbol ?? '' }}</td>
                  <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($item->unit_cost_snapshot, 6) }}</td>
                  <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($item->total_cost_snapshot, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header">
      <h6 class="card-title mg-b-0">{{ __('operations.transfer_journal_title') }}</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mg-b-0">
        <thead>
          <tr>
            <th>{{ __('operations.warehouse_label') }}</th>
            <th>{{ __('purchases.col_product') }}</th>
            <th>{{ __('app.type') }}</th>
            <th class="text-right">{{ __('purchases.col_qty') }}</th>
            <th class="text-right">{{ __('operations.col_total_value_rp') }}</th>
            <th class="text-right">{{ __('operations.col_balance_after') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($movements as $mv)
            <tr>
              <td><span class="badge badge-light">{{ $mv->warehouse->code ?? '-' }}</span></td>
              <td class="tx-medium">{{ $mv->product->code ?? '-' }}</td>
              <td>@include('partials.status-badge', ['status' => $mv->type])</td>
              <td class="text-right tx-numeric {{ $mv->direction === 'in' ? 'tx-success' : 'tx-danger' }}">
                {{ $mv->direction === 'in' ? '+' : '-' }}{{ \App\Support\StockoraFormat::number($mv->quantity, 4) }}
              </td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($mv->total_cost, 2) }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mv->running_stock_after, 4) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
