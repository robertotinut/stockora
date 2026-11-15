@extends('layouts.print')

@section('title', __('purchases.print_grn_btn') . ' - ' . $grn->receipt_number)
@section('back_url', route('grns.show', $grn))
@section('doc_heading', __('purchases.print_grn_heading'))
@section('doc_number', $grn->receipt_number)

@section('doc_status_meta')
  <div class="stk-doc-status-line">
    <span class="stk-doc-badge">
      {{ $reversalAdjustment ? __('purchases.grn_status_cancelled') : __('purchases.grn_status_posted') }}
    </span>
  </div>
@endsection

@section('print_content')
  @if($reversalAdjustment)
    <div class="stk-doc-alert">
      {{ __('purchases.print_reversed_notice', ['rev' => $reversalAdjustment->adjustment_number]) }}
    </div>
  @endif

  @php($poItemsById = $grn->purchase?->items?->keyBy('id') ?? collect())

  <div class="stk-doc-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.print_receipt_metadata') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('purchases.receipt_number') }}</td>
          <td class="stk-kv-val tx-mono">{{ $grn->receipt_number }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.warehouse') }}</td>
          <td class="stk-kv-val">
            @if($grn->warehouse)
              {{ $grn->warehouse->code }} &mdash; {{ $grn->warehouse->name }}
            @else
              -
            @endif
          </td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.posted_at_label') }}</td>
          <td class="stk-kv-val">{{ \App\Support\StockoraFormat::dateTime($grn->posted_at ?? $grn->created_at) }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.received_by') }}</td>
          <td class="stk-kv-val">{{ $grn->receivedBy->name ?? __('app.system') }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('app.status') }}</td>
          <td class="stk-kv-val">{{ $reversalAdjustment ? __('purchases.grn_status_cancelled') : __('purchases.grn_status_posted') }}</td>
        </tr>
      </table>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.print_vendor_ref') }} &amp; {{ __('purchases.supplier') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('purchases.po_number') }}</td>
          <td class="stk-kv-val tx-mono">{{ $grn->purchase->purchase_number ?? '-' }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.order_date') }}</td>
          <td class="stk-kv-val">{{ $grn->purchase?->date ? \App\Support\StockoraFormat::shortDate($grn->purchase->date) : '-' }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.supplier') }}</td>
          <td class="stk-kv-val">{{ $grn->purchase?->supplier?->name ?? __('purchases.unknown_supplier') }}</td>
        </tr>
        @if($grn->purchase?->supplier?->phone)
          <tr>
            <td class="stk-kv-label">{{ __('app.phone') }}</td>
            <td class="stk-kv-val">{{ $grn->purchase->supplier->phone }}</td>
          </tr>
        @endif
        @if($grn->purchase?->supplier?->address)
          <tr>
            <td class="stk-kv-label">{{ __('app.address') }}</td>
            <td class="stk-kv-val">{{ $grn->purchase->supplier->address }}</td>
          </tr>
        @endif
      </table>
    </div>
  </div>

  <table class="stk-items-table">
    <thead>
      <tr>
        <th style="width: 30px;" class="text-center">#</th>
        <th>{{ __('purchases.col_product') }}</th>
        <th style="width: 92px;" class="text-right">{{ __('purchases.print_ordered_qty') }}</th>
        <th style="width: 95px;" class="text-right">{{ __('purchases.col_qty_received') }}</th>
        <th style="width: 66px;" class="text-right">{{ __('purchases.col_conv_factor') }}</th>
        <th style="width: 92px;" class="text-right">{{ __('purchases.col_base_qty') }}</th>
        <th style="width: 105px;" class="text-right">{{ __('purchases.col_unit_cost_base_rp') }}</th>
        <th style="width: 112px;" class="text-right">{{ __('purchases.col_line_capitalized_rp') }}</th>
        <th style="width: 120px;">{{ __('purchases.print_col_remarks') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($grn->items as $idx => $item)
        @php($poItem = $item->purchase_item_id ? $poItemsById->get($item->purchase_item_id) : null)
        @php($needsReview = $poItem && ($poItem->conversion_status === 'NEEDS_REVIEW' || $poItem->cost_breakdown_status === 'UNKNOWN_COMPONENTS'))
        <tr>
          <td class="text-center">{{ $idx + 1 }}</td>
          <td>
            <div style="font-weight: 600;">{{ $item->product->name ?? '-' }}</div>
            @if($item->product?->code)
              <div class="tx-mono" style="font-size: 10.5px; color: #4b5563;">{{ $item->product->code }}</div>
            @endif
          </td>
          <td class="text-right tx-mono">
            @if($poItem)
              {{ \App\Support\StockoraFormat::number($poItem->quantity, 4) }}
              {{ $poItem->unit->symbol ?? $item->product?->unit?->symbol ?? '' }}
            @else
              -
            @endif
          </td>
          <td class="text-right tx-mono">
            {{ \App\Support\StockoraFormat::number($item->received_quantity, 4) }}
            {{ $item->receivedUnit->symbol ?? '' }}
          </td>
          <td class="text-right tx-mono">&times; {{ rtrim(rtrim((string) $item->conversion_factor, '0'), '.') }}</td>
          <td class="text-right tx-mono" style="font-weight: 600;">
            {{ \App\Support\StockoraFormat::number($item->base_quantity, 4) }}
            {{ $item->baseUnit->symbol ?? '' }}
          </td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($item->unit_cost_base, 6) }}</td>
          <td class="text-right tx-mono" style="font-weight: 600;">{{ \App\Support\StockoraFormat::currency($item->capitalized_total_cost, 2) }}</td>
          <td>{{ $needsReview ? __('purchases.print_condition_review') : __('purchases.print_condition_verified') }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="9" class="text-center">{{ __('app.no_data') }}</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="stk-doc-summary-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.receiving_notes_label') }}</div>
      <div style="white-space: pre-line;">{{ $grn->notes ?: '-' }}</div>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.ias2_card_title') }}</div>
      <table class="stk-totals-table">
        <tr>
          <td>{{ __('purchases.gross_merchandise_value') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($grn->total_gross_amount, 2) }}</td>
        </tr>
        <tr>
          <td>{{ __('purchases.less_purchase_discounts') }}</td>
          <td class="text-right tx-mono">- {{ \App\Support\StockoraFormat::currency($grn->total_discount_amount, 2) }}</td>
        </tr>
        <tr>
          <td>{{ __('purchases.plus_freight_delivery') }}</td>
          <td class="text-right tx-mono">+ {{ \App\Support\StockoraFormat::currency($grn->total_freight_amount, 2) }}</td>
        </tr>
        <tr>
          <td>{{ __('purchases.plus_non_rec_tax') }}</td>
          <td class="text-right tx-mono">+ {{ \App\Support\StockoraFormat::currency($grn->total_non_recoverable_tax, 2) }}</td>
        </tr>
        <tr class="stk-total-grand">
          <td>{{ __('purchases.total_capitalized_inventory') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($grn->total_capitalized_cost, 2) }}</td>
        </tr>
        <tr>
          <td>{{ __('purchases.rec_tax_excluded') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($grn->total_recoverable_tax, 2) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="stk-signature-grid">
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('purchases.print_delivered_by') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $grn->purchase?->supplier?->name ?? '-' }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('purchases.print_received_by') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $grn->receivedBy->name ?? __('app.system') }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('purchases.print_warehouse_supervisor') }}</div>
      <div style="font-size: 11px; color: #374151;">&nbsp;</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
  </div>
@endsection
