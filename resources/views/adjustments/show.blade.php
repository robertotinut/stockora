@extends('layouts.admin')

@section('title', __('operations.adjustment_detail_tab', ['number' => $adjustment->adjustment_number]))
@section('page_title', __('operations.adjustment_heading', ['number' => $adjustment->adjustment_number]))
@section('page_subtitle', ($adjustment->warehouse->code ?? '') . ' - ' . ($adjustment->warehouse->name ?? '-') . ' • ' . ($adjustment->reason ?? __('operations.adjustment_default_reason')))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">{{ __('operations.adjustments_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $adjustment->adjustment_number }}</li>
@endsection

@section('actions')
  <a href="{{ route('adjustments.print', $adjustment) }}" class="btn btn-white">
    <i data-feather="printer" class="svg-14"></i> {{ __('operations.print_adjustment_btn') }}
  </a>
  @if($adjustment->status === 'draft' && Auth::user()?->canAccessModule('stock_adjustments', 'edit'))
    <a href="{{ route('adjustments.edit', $adjustment) }}" class="btn btn-white">
      <i data-feather="edit-2" class="svg-14"></i> {{ __('operations.edit_draft_btn') }}
    </a>
  @endif
  <a href="{{ route('adjustments.index') }}" class="btn btn-white">
    <i data-feather="list" class="svg-14"></i> {{ __('operations.all_adjustments_btn') }}
  </a>
@endsection

@section('content')
  @if($adjustment->reversalAdjustment)
    <div class="alert alert-warning mg-b-20" role="alert">
      <strong>{{ __('operations.adjustment_reversed_prefix') }}</strong> {{ __('operations.counter_entry_via') }}
      <a href="{{ route('adjustments.show', $adjustment->reversalAdjustment) }}" class="tx-semibold">{{ $adjustment->reversalAdjustment->adjustment_number }}</a>.
    </div>
  @endif

  <div class="row row-sm mg-b-20">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('operations.adjustment_summary_title') }}</h6>
          @if($adjustment->reversalAdjustment)
            @include('partials.status-badge', ['status' => 'REVERSED'])
          @else
            @include('partials.status-badge', ['status' => $adjustment->status])
          @endif
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-10">
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.warehouse_label') }}</td><td class="text-right tx-medium">{{ $adjustment->warehouse->code ?? '' }} - {{ $adjustment->warehouse->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.reason') }}</td><td class="text-right">{{ $adjustment->reason }}</td></tr>
            @if($adjustment->reverses_type)
              <tr><td class="tx-color-03 pd-l-0">{{ __('operations.reverses_document') }}</td><td class="text-right"><span class="badge badge-info">{{ $adjustment->reverses_type }} #{{ $adjustment->reverses_id }}</span></td></tr>
            @endif
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.posted_by') }}</td><td class="text-right">{{ $adjustment->user->name ?? __('app.system') }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.posted_at') }}</td><td class="text-right">{{ $adjustment->posted_at ? \App\Support\StockoraFormat::dateTime($adjustment->posted_at) : __('operations.unposted_draft') }}</td></tr>
          </table>

          @if($adjustment->status === 'draft' && Auth::user()?->canAccessModule('stock_adjustments', 'approve'))
            <hr class="mg-y-12">
            <form action="{{ route('adjustments.post', $adjustment) }}" method="POST"
                  data-confirm-title="{{ __('operations.confirm_post_adj_title', ['number' => $adjustment->adjustment_number]) }}"
                  data-confirm="{{ __('operations.confirm_post_adj_msg') }}"
                  data-confirm-btn="{{ __('operations.post_now_btn') }}"
                  data-confirm-btn-class="btn-primary">
              @csrf
              <button type="submit" class="btn btn-sm btn-brand-01 btn-block">{{ __('operations.post_draft_btn') }}</button>
            </form>
          @elseif($adjustment->status === 'posted' && !$adjustment->reversalAdjustment && Auth::user()?->canAccessModule('stock_adjustments', 'approve'))
            <hr class="mg-y-12">
            <form action="{{ route('adjustments.reverse', $adjustment) }}" method="POST"
                  data-confirm-title="{{ __('operations.confirm_reverse_adj_title', ['number' => $adjustment->adjustment_number]) }}"
                  data-confirm="{{ __('operations.confirm_reverse_adj_msg') }}">
              @csrf
              <div class="form-group mg-b-10">
                <label class="tx-12 tx-medium">{{ __('operations.reversal_reason_label') }} <span class="tx-danger">*</span></label>
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('operations.reversal_reason_adj_placeholder') }}" required>
              </div>
              <button type="submit" class="btn btn-sm btn-outline-danger btn-block">{{ __('operations.reverse_adj_btn') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('operations.adjustment_lines_card') }}</h6>
          <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($adjustment->items->count()) }}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mg-b-0">
            <thead>
              <tr>
                <th>{{ __('purchases.col_product') }}</th>
                <th class="text-center">{{ __('operations.col_direction') }}</th>
                <th class="text-right">{{ __('purchases.col_qty') }}</th>
                <th class="text-right">{{ __('operations.col_unit_cost_rp') }}</th>
                <th class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($adjustment->items as $item)
                <tr>
                  <td>
                    <a href="{{ route('products.show', $item->product_id) }}" class="tx-medium tx-color-01">{{ $item->product->code ?? '-' }}</a>
                    <div class="tx-11 tx-color-03">{{ $item->product->name ?? '-' }}</div>
                  </td>
                  <td class="text-center">
                    <span class="badge {{ $item->direction === 'in' ? 'badge-success' : 'badge-warning' }}">{{ strtoupper($item->direction) }}</span>
                  </td>
                  <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($item->quantity, 4) }} {{ $item->product->unit->symbol ?? '' }}</td>
                  <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($item->unit_cost, 6) }}</td>
                  <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($item->total_cost, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  @if($movements->isNotEmpty())
    <div class="card card-hover">
      <div class="card-header">
        <h6 class="card-title mg-b-0">{{ __('operations.ledger_entries_title') }}</h6>
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
  @endif
@endsection
