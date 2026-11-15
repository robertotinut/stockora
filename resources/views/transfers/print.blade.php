@extends('layouts.print')

@section('title', __('operations.print_transfer_btn') . ' - ' . $transfer->transfer_number)
@section('back_url', route('transfers.show', $transfer))
@section('doc_heading', __('operations.print_transfer_heading'))
@section('doc_number', $transfer->transfer_number)

@section('doc_status_meta')
  <div class="stk-doc-status-line">
    <span class="stk-doc-badge">
      {{ $transfer->reversalAdjustment ? __('purchases.statuses.reversed') : __('operations.status_posted') }}
    </span>
  </div>
@endsection

@section('print_content')
  @if($transfer->reversalAdjustment)
    <div class="stk-doc-alert">
      {{ __('purchases.print_reversed_notice', ['rev' => $transfer->reversalAdjustment->adjustment_number]) }}
    </div>
  @endif

  <div class="stk-doc-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.transfer_header_card') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('operations.transfer_number') }}</td>
          <td class="stk-kv-val tx-mono">{{ $transfer->transfer_number }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.from_warehouse') }}</td>
          <td class="stk-kv-val">{{ $transfer->fromWarehouse?->code }} &mdash; {{ $transfer->fromWarehouse?->name ?? '-' }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.to_warehouse') }}</td>
          <td class="stk-kv-val">{{ $transfer->toWarehouse?->code }} &mdash; {{ $transfer->toWarehouse?->name ?? '-' }}</td>
        </tr>
      </table>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.col_posted_user') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('operations.posted_at') }}</td>
          <td class="stk-kv-val">{{ \App\Support\StockoraFormat::dateTime($transfer->posted_at ?? $transfer->created_at) }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.posted_by') }}</td>
          <td class="stk-kv-val">{{ $transfer->user->name ?? __('app.system') }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('app.status') }}</td>
          <td class="stk-kv-val">{{ $transfer->reversalAdjustment ? __('purchases.statuses.reversed') : __('operations.status_posted') }}</td>
        </tr>
      </table>
    </div>
  </div>

  <table class="stk-items-table">
    <thead>
      <tr>
        <th style="width: 34px;" class="text-center">#</th>
        <th>{{ __('purchases.col_product') }}</th>
        <th style="width: 115px;" class="text-right">{{ __('app.quantity') }}</th>
        <th style="width: 115px;" class="text-right">{{ __('operations.col_base_qty') }}</th>
        <th style="width: 120px;" class="text-right">{{ __('operations.col_carry_mac') }}</th>
        <th style="width: 130px;" class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
      </tr>
    </thead>
    <tbody>
      @php($totalValue = '0')
      @forelse($transfer->items as $idx => $item)
        @php($totalValue = bcadd($totalValue, (string) $item->total_cost_snapshot, 4))
        <tr>
          <td class="text-center">{{ $idx + 1 }}</td>
          <td>
            <div style="font-weight: 600;">{{ $item->product->name ?? '-' }}</div>
            @if($item->product?->code)
              <div class="tx-mono" style="font-size: 10.5px; color: #4b5563;">{{ $item->product->code }}</div>
            @endif
          </td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::number($item->quantity, 4) }} {{ $item->unit->symbol ?? '' }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::number($item->base_quantity, 4) }} {{ $item->product?->unit?->symbol ?? '' }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($item->unit_cost_snapshot, 6) }}</td>
          <td class="text-right tx-mono" style="font-weight: 600;">{{ \App\Support\StockoraFormat::currency($item->total_cost_snapshot, 2) }}</td>
        </tr>
      @empty
        <tr>
          <td colspan="6" class="text-center">{{ __('app.no_data') }}</td>
        </tr>
      @endforelse
    </tbody>
  </table>

  <div class="stk-doc-summary-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.dispatch_notes_label') }}</div>
      <div style="white-space: pre-line;">{{ $transfer->notes ?: '-' }}</div>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('app.total') }}</div>
      <table class="stk-totals-table">
        <tr>
          <td>{{ __('operations.col_lines') }}</td>
          <td class="text-right tx-mono">{{ __('operations.items_count_inline', ['count' => $transfer->items->count()]) }}</td>
        </tr>
        <tr class="stk-total-grand">
          <td>{{ __('operations.col_total_cost_rp') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($totalValue, 2) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="stk-signature-grid">
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('app.prepared_by') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $transfer->user->name ?? __('app.system') }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('operations.print_issued_by') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $transfer->fromWarehouse?->name ?? '-' }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('operations.print_received_dest') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $transfer->toWarehouse?->name ?? '-' }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
  </div>
@endsection
