@extends('layouts.admin')

@section('title', __('operations.issue_detail_tab', ['number' => $issue->issue_number]))
@section('page_title', __('operations.issue_heading', ['number' => $issue->issue_number]))
@section('page_subtitle', ($issue->warehouse->code ?? '') . ' - ' . ($issue->warehouse->name ?? '-') . ' • ' . __('operations.posted_inline') . ' ' . ($issue->posted_at ? \App\Support\StockoraFormat::dateTime($issue->posted_at) : '-'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('issues.index') }}">{{ __('operations.issues_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $issue->issue_number }}</li>
@endsection

@section('actions')
  <a href="{{ route('issues.print', $issue) }}" class="btn btn-white">
    <i data-feather="printer" class="svg-14"></i> {{ __('operations.print_issue_btn') }}
  </a>
  <a href="{{ route('issues.index') }}" class="btn btn-white">
    <i data-feather="list" class="svg-14"></i> {{ __('operations.all_issues_btn') }}
  </a>
@endsection

@section('content')
  @if($issue->reversalAdjustment)
    <div class="alert alert-warning mg-b-20" role="alert">
      <strong>{{ __('operations.issue_reversed_prefix') }}</strong> {{ __('operations.stock_restored_via') }}
      @if(Auth::user()?->canAccessModule('stock_adjustments', 'view') && ($planFeatures['module_stock_adjustments']['enabled'] ?? false))
        <a href="{{ route('adjustments.show', $issue->reversalAdjustment) }}" class="tx-semibold">{{ $issue->reversalAdjustment->adjustment_number }}</a>
      @else
        <strong class="tx-semibold">{{ $issue->reversalAdjustment->adjustment_number }}</strong>
      @endif
      ({{ $issue->reversalAdjustment->reason }}).
    </div>
  @endif

  <div class="row row-sm mg-b-20">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('operations.issue_header_title') }}</h6>
          @if($issue->reversalAdjustment)
            @include('partials.status-badge', ['status' => 'REVERSED'])
          @else
            @include('partials.status-badge', ['status' => $issue->status])
          @endif
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-10">
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.warehouse_label') }}</td><td class="text-right tx-medium">{{ $issue->warehouse->code ?? '' }} - {{ $issue->warehouse->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.posted_by') }}</td><td class="text-right">{{ $issue->user->name ?? __('app.system') }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('operations.posted_at') }}</td><td class="text-right">{{ $issue->posted_at ? \App\Support\StockoraFormat::dateTime($issue->posted_at) : '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.notes') }}</td><td class="text-right">{{ $issue->notes ?: '-' }}</td></tr>
          </table>

          @if($issue->status === 'posted' && !$issue->reversalAdjustment && Auth::user()?->canAccessModule('stock_issues', 'approve'))
            <hr class="mg-y-12">
            <form action="{{ route('issues.reverse', $issue) }}" method="POST"
                  data-confirm-title="{{ __('operations.confirm_reverse_issue_title', ['number' => $issue->issue_number]) }}"
                  data-confirm="{{ __('operations.confirm_reverse_issue_msg') }}">
              @csrf
              <div class="form-group mg-b-10">
                <label class="tx-12 tx-medium">{{ __('operations.reversal_reason_label') }} <span class="tx-danger">*</span></label>
                <input type="text" name="reason" class="form-control form-control-sm" placeholder="{{ __('operations.reversal_reason_issue_placeholder') }}" required>
              </div>
              <button type="submit" class="btn btn-sm btn-outline-danger btn-block">{{ __('operations.reverse_issue_btn') }}</button>
            </form>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('operations.issued_lines_title') }}</h6>
          <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($issue->items->count()) }}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mg-b-0">
            <thead>
              <tr>
                <th>{{ __('purchases.col_product') }}</th>
                <th class="text-right">{{ __('purchases.col_qty') }}</th>
                <th class="text-right">{{ __('operations.col_base_qty') }}</th>
                <th class="text-right">{{ __('operations.col_mac_cost') }}</th>
                <th class="text-right">{{ __('operations.col_total_value_rp') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($issue->items as $item)
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
      <h6 class="card-title mg-b-0">{{ __('operations.ledger_entries_title') }}</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mg-b-0">
        <thead>
          <tr>
            <th>{{ __('purchases.col_product') }}</th>
            <th>{{ __('app.type') }}</th>
            <th class="text-right">{{ __('operations.col_qty_out') }}</th>
            <th class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
            <th class="text-right">{{ __('operations.col_balance_after') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($movements as $mv)
            <tr>
              <td class="tx-medium">{{ $mv->product->code ?? '-' }}</td>
              <td>@include('partials.status-badge', ['status' => $mv->type])</td>
              <td class="text-right tx-numeric tx-danger">-{{ \App\Support\StockoraFormat::number($mv->quantity, 4) }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($mv->total_cost, 2) }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mv->running_stock_after, 4) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
