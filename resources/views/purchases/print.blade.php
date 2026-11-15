@extends('layouts.print')

@section('title', __('purchases.print_po_btn') . ' - ' . $purchase->purchase_number)
@section('back_url', route('purchases.show', $purchase))
@section('doc_heading', __('purchases.print_po_heading'))
@section('doc_number', $purchase->purchase_number)

@section('doc_status_meta')
  <div class="stk-doc-status-line">
    <span class="stk-doc-badge">{{ __('purchases.statuses.' . $purchase->status) }}</span>
    <span class="stk-doc-badge">{{ __('purchases.payment_statuses.' . $purchase->payment_status) }}</span>
  </div>
@endsection

@section('print_content')
  @if($purchase->status === 'cancelled')
    <div class="stk-doc-alert">
      {{ __('purchases.flash.cancelled', ['number' => $purchase->purchase_number]) }}
    </div>
  @endif

  <div class="stk-doc-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.print_supplier_info') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('purchases.supplier') }}</td>
          <td class="stk-kv-val">{{ $purchase->supplier->name ?? __('purchases.unknown_supplier') }}</td>
        </tr>
        @if($purchase->supplier?->code)
          <tr>
            <td class="stk-kv-label">{{ __('app.code') }}</td>
            <td class="stk-kv-val tx-mono">{{ $purchase->supplier->code }}</td>
          </tr>
        @endif
        @if($purchase->supplier?->contact_person)
          <tr>
            <td class="stk-kv-label">{{ __('app.name') }}</td>
            <td class="stk-kv-val">{{ $purchase->supplier->contact_person }}</td>
          </tr>
        @endif
        @if($purchase->supplier?->phone)
          <tr>
            <td class="stk-kv-label">{{ __('app.phone') }}</td>
            <td class="stk-kv-val">{{ $purchase->supplier->phone }}</td>
          </tr>
        @endif
        @if($purchase->supplier?->email)
          <tr>
            <td class="stk-kv-label">{{ __('app.email') }}</td>
            <td class="stk-kv-val">{{ $purchase->supplier->email }}</td>
          </tr>
        @endif
        @if($purchase->supplier?->address)
          <tr>
            <td class="stk-kv-label">{{ __('app.address') }}</td>
            <td class="stk-kv-val">{{ $purchase->supplier->address }}</td>
          </tr>
        @endif
      </table>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.print_order_metadata') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('purchases.po_number') }}</td>
          <td class="stk-kv-val tx-mono">{{ $purchase->purchase_number }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.order_date') }}</td>
          <td class="stk-kv-val">{{ \App\Support\StockoraFormat::shortDate($purchase->date) }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.status') }}</td>
          <td class="stk-kv-val">{{ __('purchases.statuses.' . $purchase->status) }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.payment_status') }}</td>
          <td class="stk-kv-val">{{ __('purchases.payment_statuses.' . $purchase->payment_status) }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.created_by') }}</td>
          <td class="stk-kv-val">{{ $purchase->user->name ?? __('app.system') }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('purchases.items_heading') }}</td>
          <td class="stk-kv-val">{{ __('purchases.print_items_summary', ['count' => $purchase->items->count()]) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <table class="stk-items-table">
    <thead>
      <tr>
        <th style="width: 34px;" class="text-center">#</th>
        <th>{{ __('purchases.col_product') }}</th>
        <th style="width: 70px;">{{ __('purchases.col_unit') }}</th>
        <th style="width: 105px;" class="text-right">{{ __('purchases.col_ordered_qty') }}</th>
        <th style="width: 105px;" class="text-right">{{ __('purchases.received_qty') }}</th>
        <th style="width: 120px;" class="text-right">{{ __('purchases.col_unit_price_rp') }}</th>
        <th style="width: 130px;" class="text-right">{{ __('purchases.col_subtotal_rp') }}</th>
      </tr>
    </thead>
    <tbody>
      @forelse($purchase->items as $idx => $item)
        <tr>
          <td class="text-center">{{ $idx + 1 }}</td>
          <td>
            <div style="font-weight: 600;">{{ $item->product->name ?? '-' }}</div>
            @if($item->product?->code)
              <div class="tx-mono" style="font-size: 10.5px; color: #4b5563;">{{ $item->product->code }}</div>
            @endif
          </td>
          <td>{{ $item->unit->symbol ?? $item->product?->unit?->symbol ?? '-' }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::number($item->quantity, 4) }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::number($item->received_quantity, 4) }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::number($item->unit_price, 2) }}</td>
          <td class="text-right tx-mono" style="font-weight: 600;">{{ \App\Support\StockoraFormat::currency($item->subtotal, 2) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="7" class="text-center">{{ __('app.no_data') }}</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="stk-doc-summary-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.notes_instructions') }}</div>
      <div style="white-space: pre-line;">{{ $purchase->notes ?: '-' }}</div>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('purchases.order_summary_title') }}</div>
      <table class="stk-totals-table">
        <tr>
          <td>{{ __('purchases.items_heading') }}</td>
          <td class="text-right tx-mono">{{ __('purchases.print_items_summary', ['count' => $purchase->items->count()]) }}</td>
        </tr>
        <tr class="stk-total-grand">
          <td>{{ __('purchases.print_po_total_amount') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($purchase->total_amount, 2) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="stk-signature-grid">
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('app.prepared_by') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $purchase->user->name ?? __('app.system') }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('app.approved_by') }}</div>
      <div style="font-size: 11px; color: #374151;">&nbsp;</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('purchases.print_vendor_confirm') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $purchase->supplier->name ?? __('purchases.unknown_supplier') }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
  </div>
@endsection
