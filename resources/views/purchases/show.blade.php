@extends('layouts.admin')

@section('title', __('purchases.detail_tab_title', ['number' => $purchase->purchase_number]))
@section('page_title', __('purchases.detail_heading', ['number' => $purchase->purchase_number]))
@section('page_subtitle', __('purchases.dated_subtitle', ['supplier' => $purchase->supplier->name ?? __('purchases.unknown_supplier'), 'date' => \App\Support\StockoraFormat::shortDate($purchase->date)]))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">{{ __('purchases.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $purchase->purchase_number }}</li>
@endsection

@section('actions')
  <a href="{{ route('purchases.print', $purchase) }}" class="btn btn-white">
    <i data-feather="printer" class="svg-14"></i> {{ __('purchases.print_po_btn') }}
  </a>
  @if(in_array($purchase->status, ['pending', 'ordered'], true) && $purchase->receipts->isEmpty() && Auth::user()?->canAccessModule('purchase_orders', 'edit'))
    <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-white">
      <i data-feather="edit-2" class="svg-14"></i> {{ __('purchases.edit_po_btn') }}
    </a>
  @endif
  @if(in_array($purchase->status, ['pending', 'ordered', 'partial'], true) && Auth::user()?->canAccessModule('grn', 'create') && ($planFeatures['module_grn']['enabled'] ?? false))
    <a href="{{ route('grns.create', ['purchase_id' => $purchase->id]) }}" class="btn btn-brand-01">
      <i data-feather="check-square" class="svg-14"></i> {{ __('purchases.receive_goods_action') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm mg-b-20">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('purchases.order_summary_title') }}</h6>
          @include('partials.status-badge', ['status' => $purchase->status])
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-15">
            <tr>
              <td class="tx-color-03 pd-l-0">{{ __('purchases.supplier') }}</td>
              <td class="text-right tx-medium">
                @if($purchase->supplier && Auth::user()?->canAccessModule('suppliers', 'view') && ($planFeatures['module_suppliers']['enabled'] ?? false))
                  <a href="{{ route('suppliers.show', $purchase->supplier) }}">{{ $purchase->supplier->name }}</a>
                @elseif($purchase->supplier)
                  {{ $purchase->supplier->name }}
                @else - @endif
              </td>
            </tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.order_date') }}</td><td class="text-right">{{ \App\Support\StockoraFormat::shortDate($purchase->date) }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.created_by') }}</td><td class="text-right">{{ $purchase->user->name ?? __('app.system') }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('purchases.total_amount') }}</td><td class="text-right tx-numeric tx-semibold tx-brand-01">{{ \App\Support\StockoraFormat::currency($purchase->total_amount, 2) }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.notes') }}</td><td class="text-right">{{ $purchase->notes ?? '-' }}</td></tr>
          </table>

          @if($purchase->status !== 'cancelled' && Auth::user()?->canAccessModule('purchase_orders', 'approve'))
            <form action="{{ route('purchases.update-payment', $purchase) }}" method="POST" class="d-flex justify-content-between align-items-center border-top pd-t-12 mg-b-12" style="gap: 8px;">
              @csrf
              @method('PATCH')
              <label class="tx-12 tx-color-03 mg-b-0">{{ __('purchases.payment_status') }}:</label>
              <div class="input-group input-group-sm" style="max-width: 190px;">
                <select name="payment_status" class="form-control">
                  @foreach(['unpaid', 'partial', 'paid'] as $v)
                    <option value="{{ $v }}" {{ $purchase->payment_status === $v ? 'selected' : '' }}>{{ __('purchases.payment_statuses.' . $v) }}</option>
                  @endforeach
                </select>
                <div class="input-group-append">
                  <button type="submit" class="btn btn-white">{{ __('app.save') }}</button>
                </div>
              </div>
            </form>
          @endif

          <div class="d-flex flex-wrap pd-t-12 border-top" style="gap: 8px;">
            @if($purchase->status === 'pending' && Auth::user()?->canAccessModule('purchase_orders', 'approve'))
              <form action="{{ route('purchases.confirm', $purchase) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-xs btn-primary">{{ __('purchases.confirm_order') }}</button>
              </form>
            @endif
            @if(in_array($purchase->status, ['pending', 'ordered'], true) && $purchase->receipts->filter(fn ($r) => $r->status === 'posted' && !$r->reversalAdjustment)->isEmpty() && Auth::user()?->canAccessModule('purchase_orders', 'approve'))
              <form action="{{ route('purchases.cancel', $purchase) }}" method="POST"
                    data-confirm-title="{{ __('purchases.confirm_cancel_title') }}"
                    data-confirm="{{ __('purchases.confirm_cancel_msg', ['number' => $purchase->purchase_number]) }}">
                @csrf
                <button type="submit" class="btn btn-xs btn-outline-warning">{{ __('purchases.cancel_order') }}</button>
              </form>
            @endif
            @if($purchase->status === 'pending' && $purchase->receipts->isEmpty() && Auth::user()?->canAccessModule('purchase_orders', 'delete'))
              <form action="{{ route('purchases.destroy', $purchase) }}" method="POST"
                    data-confirm-title="{{ __('purchases.confirm_delete_draft_title') }}"
                    data-confirm="{{ __('purchases.confirm_delete_draft_msg', ['number' => $purchase->purchase_number]) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('purchases.delete_draft_btn') }}</button>
              </form>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('purchases.grns_card_title') }}</h6>
          <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($purchase->receipts->count()) }}</span>
        </div>
        @if($purchase->receipts->isEmpty())
          <div class="card-body d-flex flex-column align-items-center justify-content-center">
            @php
              $canReceiveGrn = in_array($purchase->status, ['pending', 'ordered', 'partial'], true)
                  && Auth::user()?->canAccessModule('grn', 'create')
                  && ($planFeatures['module_grn']['enabled'] ?? false);
            @endphp
            @include('partials.empty-state', [
              'title' => __('purchases.no_grns_title'),
              'description' => __('purchases.no_grns_desc'),
              'icon' => 'check-square',
              'actionUrl' => $canReceiveGrn ? route('grns.create', ['purchase_id' => $purchase->id]) : null,
              'actionLabel' => __('purchases.receive_goods_action'),
            ])
            @if(Auth::user()?->canAccessModule('grn', 'create') && !($planFeatures['module_grn']['enabled'] ?? false))
              <div class="alert alert-light border tx-12 mg-t-10 mg-b-0 w-100 text-center">
                {{ __('subscription.feature_locked_inline', ['feature' => __('subscription.features.module_grn')]) }}
                <a href="{{ route('plans.index') }}" class="tx-semibold mg-l-4">{{ __('subscription.compare_plans_btn') }}</a>
              </div>
            @endif
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('purchases.receipt_number') }}</th>
                  <th>{{ __('purchases.warehouse') }}</th>
                  <th class="text-right">{{ __('purchases.col_capitalized_rp') }}</th>
                  <th class="text-center">{{ __('purchases.status') }}</th>
                  <th>{{ __('purchases.received_by') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($purchase->receipts as $grn)
                  <tr>
                    <td>
                      @if(Auth::user()?->canAccessModule('grn', 'view') && ($planFeatures['module_grn']['enabled'] ?? false))
                        <div class="d-inline-flex align-items-center" style="gap: 6px;">
                          <a href="{{ route('grns.show', $grn) }}" class="tx-medium">{{ $grn->receipt_number }}</a>
                          <a href="{{ route('grns.print', $grn) }}" class="btn btn-xs btn-white" title="{{ __('purchases.print_grn_btn') }}" aria-label="{{ __('purchases.print_grn_btn') }}">
                            <i data-feather="printer" class="svg-12"></i>
                          </a>
                        </div>
                      @else
                        <span class="tx-medium">{{ $grn->receipt_number }}</span>
                      @endif
                    </td>
                    <td><span class="badge badge-light">{{ $grn->warehouse->code ?? '-' }}</span></td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($grn->total_capitalized_cost, 2) }}</td>
                    <td class="text-center">
                      @if($grn->reversalAdjustment)
                        @include('partials.status-badge', ['status' => 'REVERSED'])
                      @else
                        @include('partials.status-badge', ['status' => $grn->status])
                      @endif
                    </td>
                    <td class="tx-12 tx-color-03">{{ $grn->receivedBy->name ?? '-' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="card-title mg-b-0">{{ __('purchases.items_heading') }}</h6>
      <span class="badge badge-light tx-numeric">{{ __('purchases.lines_count_badge', ['count' => \App\Support\StockoraFormat::number($purchase->items->count())]) }}</span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover mg-b-0">
        <thead>
          <tr>
            <th>{{ __('purchases.col_product') }}</th>
            <th>{{ __('purchases.col_unit') }}</th>
            <th class="text-right">{{ __('purchases.col_ordered_qty') }}</th>
            <th class="text-right">{{ __('purchases.received_qty') }}</th>
            <th class="text-right">{{ __('purchases.col_remaining') }}</th>
            <th class="text-right">{{ __('purchases.col_unit_price_rp') }}</th>
            <th class="text-right">{{ __('purchases.col_subtotal_rp') }}</th>
            <th class="text-center">{{ __('purchases.col_cost_audit') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($purchase->items as $item)
            @php
              $ordered = (string) $item->quantity;
              $received = (string) $item->received_quantity;
              $remaining = bccomp($ordered, $received, 4) > 0 ? bcsub($ordered, $received, 4) : '0.0000';
              $needsReview = $item->conversion_status === 'NEEDS_REVIEW' || $item->cost_breakdown_status === 'UNKNOWN_COMPONENTS';
            @endphp
            <tr class="{{ $needsReview ? 'bg-warning-light' : '' }}">
              <td>
                @if($item->product)
                  <a href="{{ route('products.show', $item->product) }}" class="tx-medium tx-color-01">{{ $item->product->code }}</a>
                  <div class="tx-11 tx-color-03">{{ $item->product->name }}</div>
                @else - @endif
              </td>
              <td>{{ $item->unit->symbol ?? $item->product->unit->symbol ?? '-' }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($ordered, 4) }}</td>
              <td class="text-right tx-numeric tx-success">{{ \App\Support\StockoraFormat::number($received, 4) }}</td>
              <td class="text-right tx-numeric tx-medium {{ bccomp($remaining, '0', 4) > 0 ? 'tx-primary' : 'tx-color-03' }}">{{ \App\Support\StockoraFormat::number($remaining, 4) }}</td>
              <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($item->unit_price, 2) }}</td>
              <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($item->subtotal, 2) }}</td>
              <td class="text-center">
                @if($needsReview)
                  <span class="badge badge-warning">{{ $item->conversion_status }} / {{ $item->cost_breakdown_status }}</span>
                @else
                  <span class="badge badge-success">{{ __('purchases.verified_badge') }}</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
