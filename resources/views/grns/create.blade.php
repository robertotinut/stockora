@extends('layouts.admin')

@section('title', __('purchases.grn_title'))
@section('page_title', __('purchases.grn_create_heading'))
@section('page_subtitle', __('purchases.grn_create_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('grns.index') }}">{{ __('purchases.goods_receiving_nav') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('purchases.grn_breadcrumb_receive') }}</li>
@endsection

@section('content')
  @include('partials.quota-banner', ['limitKey' => 'max_monthly_stock_movements'])

  <div class="card card-hover mg-b-20">
    <div class="card-header">
      <h6 class="card-title mg-b-0">{{ __('purchases.grn_step_1_title') }}</h6>
    </div>
    <div class="card-body">
      <form action="{{ route('grns.create') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-md-9 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.grn_open_po_label') }}</label>
          <select name="purchase_id" class="form-control" required>
            <option value="">{{ __('purchases.select_po_placeholder') }}</option>
            @foreach($receivablePos as $po)
              <option value="{{ $po->id }}" {{ $selectedPurchase && (int) $selectedPurchase->id === (int) $po->id ? 'selected' : '' }}>
                {{ $po->purchase_number }} &mdash; {{ $po->supplier->name ?? '-' }} ({{ __('purchases.statuses.' . $po->status) }} | {{ \App\Support\StockoraFormat::currency($po->total_amount, 2) }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-white btn-block">{{ __('purchases.load_po_items_btn') }}</button>
        </div>
      </form>
      @if($receivablePos->isEmpty())
        <div class="mg-t-15">
          @include('partials.empty-state', [
            'title' => __('purchases.no_receivable_pos_title'),
            'description' => __('purchases.no_receivable_pos_desc'),
            'icon' => 'shopping-cart',
            'actionUrl' => (Auth::user()?->canAccessModule('purchase_orders', 'create') && ($planFeatures['module_purchase_orders']['enabled'] ?? false)) ? route('purchases.create') : null,
            'actionLabel' => __('purchases.create_title'),
          ])
        </div>
      @endif
    </div>
  </div>

  @if($selectedPurchase)
    <form action="{{ route('grns.store') }}" method="POST">
      @csrf
      <input type="hidden" name="purchase_id" value="{{ $selectedPurchase->id }}">
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

      <div class="card card-hover mg-b-20">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
          <h6 class="card-title mg-b-0">{{ __('purchases.grn_step_2_title', ['number' => $selectedPurchase->purchase_number]) }}</h6>
          <span class="badge badge-light">{{ $selectedPurchase->supplier->name ?? '-' }}</span>
        </div>
        <div class="card-body">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>
                {{ __('purchases.receipt_number') }}
                <span class="badge badge-light ml-1 tx-10">{{ __('app.auto_label') }}</span>
              </label>
              <input type="text"
                     value="{{ $suggestedNumber }}"
                     class="form-control stk-docnum-readonly"
                     readonly
                     tabindex="-1"
                     aria-readonly="true">
              <small class="form-text text-muted tx-11">{{ __('app.auto_generated_upon_save') }}</small>
            </div>
            <div class="form-group col-md-4">
              <label>{{ __('purchases.target_warehouse_label') }} <span class="tx-danger">*</span></label>
              <select name="warehouse_id" class="form-control @error('warehouse_id') is-invalid @enderror" required>
                @foreach($warehouses as $wh)
                  <option value="{{ $wh->id }}" {{ (string) old('warehouse_id') === (string) $wh->id || (!old('warehouse_id') && $wh->is_default) ? 'selected' : '' }}>
                    {{ $wh->code }} - {{ $wh->name }}
                  </option>
                @endforeach
              </select>
              @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-group col-md-4">
              <label>{{ __('purchases.receiving_notes_label') }}</label>
              <input type="text" name="notes" value="{{ old('notes') }}" class="form-control" placeholder="{{ __('purchases.receiving_notes_placeholder') }}">
            </div>
          </div>
          <div class="alert alert-info tx-12 mg-b-0">
            <strong>{{ __('purchases.ias2_formula_title') }}</strong> {{ __('purchases.ias2_formula_body') }}
          </div>
        </div>
      </div>

      <div class="card card-hover mg-b-20">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('purchases.grn_step_3_title') }}</h6>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mg-b-0">
            <thead>
              <tr>
                <th style="min-width: 190px;">{{ __('purchases.col_product_open_balance') }}</th>
                <th style="min-width: 120px;">{{ __('purchases.col_receive_uom') }}</th>
                <th class="text-right" style="min-width: 110px;">{{ __('purchases.col_qty_received') }}</th>
                <th class="text-right" style="min-width: 120px;">{{ __('purchases.col_unit_price') }}</th>
                <th class="text-right" style="min-width: 105px;">{{ __('purchases.col_discount_rp') }}</th>
                <th class="text-right" style="min-width: 105px;">{{ __('purchases.col_freight_rp') }}</th>
                <th class="text-right" style="min-width: 115px;">{{ __('purchases.col_non_rec_tax') }}</th>
                <th class="text-right" style="min-width: 115px;">{{ __('purchases.col_rec_tax_vat') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach($selectedPurchase->items as $idx => $item)
                @php
                  $remaining = bccomp((string) $item->quantity, (string) $item->received_quantity, 4) > 0
                      ? bcsub((string) $item->quantity, (string) $item->received_quantity, 4)
                      : '0.0000';
                  $remClean = rtrim(rtrim($remaining, '0'), '.') ?: '0';
                  $isQuarantined = ($item->product->legacy_reconciliation_status ?? '') === 'CHAIN_BREAK';
                @endphp
                <tr class="{{ $isQuarantined ? 'bg-warning-light' : '' }}">
                  <td>
                    <input type="hidden" name="lines[{{ $idx }}][purchase_item_id]" value="{{ $item->id }}">
                    <div class="tx-medium tx-color-01">{{ $item->product->code ?? '-' }}</div>
                    <div class="tx-11 tx-color-03">{{ $item->product->name ?? '-' }}</div>
                    <div class="tx-11">
                      {{ __('purchases.ordered_inline') }} <span class="tx-numeric">{{ \App\Support\StockoraFormat::number($item->quantity, 2) }}</span> |
                      {{ __('purchases.open_inline') }} <strong class="tx-numeric {{ bccomp($remaining, '0', 4) > 0 ? 'tx-primary' : 'tx-success' }}">{{ \App\Support\StockoraFormat::number($remaining, 2) }}</strong>
                      {{ $item->unit->symbol ?? $item->product->unit->symbol ?? '' }}
                    </div>
                    @if($isQuarantined)
                      <span class="badge badge-danger tx-10">{{ __('purchases.quarantined_posting_blocked') }}</span>
                    @endif
                  </td>
                  <td>
                    <select name="lines[{{ $idx }}][received_unit_id]" class="form-control form-control-sm">
                      @foreach($units as $u)
                        <option value="{{ $u->id }}" {{ (string) old("lines.$idx.received_unit_id", $item->unit_id ?? $item->product->unit_id) === (string) $u->id ? 'selected' : '' }}>
                          {{ $u->symbol }}
                        </option>
                      @endforeach
                    </select>
                  </td>
                  <td>
                    <input type="number" step="0.0001" min="0" max="{{ $isQuarantined ? '0' : $remaining }}"
                           name="lines[{{ $idx }}][received_quantity]"
                           value="{{ old("lines.$idx.received_quantity", ($isQuarantined || bccomp($remaining, '0', 4) <= 0) ? '0' : $remClean) }}"
                           class="form-control form-control-sm text-right tx-numeric" {{ $isQuarantined || bccomp($remaining, '0', 4) <= 0 ? 'readonly' : '' }}>
                  </td>
                  <td>
                    <input type="number" step="0.000001" min="0"
                           name="lines[{{ $idx }}][unit_price]"
                           value="{{ old("lines.$idx.unit_price", rtrim(rtrim((string) $item->unit_price, '0'), '.') ?: '0') }}"
                           class="form-control form-control-sm text-right tx-numeric">
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][discount_amount]" value="{{ old("lines.$idx.discount_amount", '0') }}" class="form-control form-control-sm text-right tx-numeric">
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][allocated_freight_amount]" value="{{ old("lines.$idx.allocated_freight_amount", '0') }}" class="form-control form-control-sm text-right tx-numeric">
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][non_recoverable_tax_amount]" value="{{ old("lines.$idx.non_recoverable_tax_amount", '0') }}" class="form-control form-control-sm text-right tx-numeric">
                  </td>
                  <td>
                    <input type="number" step="0.01" min="0" name="lines[{{ $idx }}][recoverable_tax_amount]" value="{{ old("lines.$idx.recoverable_tax_amount", '0') }}" class="form-control form-control-sm text-right tx-numeric">
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <div class="d-flex justify-content-end" style="gap: 8px;">
        <a href="{{ Auth::user()?->canAccessModule('purchase_orders', 'view') && ($planFeatures['module_purchase_orders']['enabled'] ?? false) ? route('purchases.show', $selectedPurchase) : route('grns.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
        <button type="submit" class="btn btn-brand-01">{{ __('purchases.post_grn_btn') }}</button>
      </div>
    </form>
  @endif
@endsection
