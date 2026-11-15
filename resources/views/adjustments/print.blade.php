@extends('layouts.print')

@section('title', __('operations.print_adjustment_btn') . ' - ' . $adjustment->adjustment_number)
@section('back_url', route('adjustments.show', $adjustment))
@section('doc_heading', __('operations.print_adjustment_heading'))
@section('doc_number', $adjustment->adjustment_number)

@section('doc_status_meta')
  <div class="stk-doc-status-line">
    <span class="stk-doc-badge">
      {{ $adjustment->status === 'draft' ? __('operations.status_draft') : __('operations.status_posted') }}
    </span>
    @if($adjustment->reversalAdjustment)
      <span class="stk-doc-badge">{{ __('purchases.statuses.reversed') }}</span>
    @endif
  </div>
@endsection

@section('print_content')
  @if($adjustment->reversalAdjustment)
    <div class="stk-doc-alert">
      {{ __('purchases.print_reversed_notice', ['rev' => $adjustment->reversalAdjustment->adjustment_number]) }}
    </div>
  @endif

  <div class="stk-doc-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.adjustment_summary_title') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('operations.adjustment_number') }}</td>
          <td class="stk-kv-val tx-mono">{{ $adjustment->adjustment_number }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.warehouse_label') }}</td>
          <td class="stk-kv-val">{{ $adjustment->warehouse?->code }} &mdash; {{ $adjustment->warehouse?->name ?? '-' }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('app.status') }}</td>
          <td class="stk-kv-val">{{ $adjustment->status === 'draft' ? __('operations.status_draft') : __('operations.status_posted') }}</td>
        </tr>
        @if($adjustment->reverses_type)
          <tr>
            <td class="stk-kv-label">{{ __('operations.reverses_document') }}</td>
            <td class="stk-kv-val">{{ $adjustment->reverses_type }} #{{ $adjustment->reverses_id }}</td>
          </tr>
        @endif
      </table>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.col_posted_user') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('operations.posted_at') }}</td>
          <td class="stk-kv-val">
            {{ $adjustment->posted_at ? \App\Support\StockoraFormat::dateTime($adjustment->posted_at) : __('operations.unposted_draft') }}
          </td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.posted_by') }}</td>
          <td class="stk-kv-val">{{ $adjustment->user->name ?? __('app.system') }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.col_lines') }}</td>
          <td class="stk-kv-val">{{ __('operations.items_count_inline', ['count' => $adjustment->items->count()]) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <table class="stk-items-table">
    <thead>
      <tr>
        <th style="width: 34px;" class="text-center">#</th>
        <th>{{ __('purchases.col_product') }}</th>
        <th style="width: 82px;" class="text-center">{{ __('operations.print_direction') }}</th>
        <th style="width: 115px;" class="text-right">{{ __('app.quantity') }}</th>
        <th style="width: 115px;" class="text-right">{{ __('operations.col_unit_cost_rp') }}</th>
        <th style="width: 125px;" class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
        <th style="width: 120px;">{{ __('operations.col_valuation_basis') }}</th>
      </tr>
    </thead>
    <tbody>
      @php($totalIn = '0')
      @php($totalOut = '0')
      @forelse($adjustment->items as $idx => $item)
        @if($item->direction === 'in')
          @php($totalIn = bcadd($totalIn, (string) $item->total_cost, 4))
        @else
          @php($totalOut = bcadd($totalOut, (string) $item->total_cost, 4))
        @endif
        <tr>
          <td class="text-center">{{ $idx + 1 }}</td>
          <td>
            <div style="font-weight: 600;">{{ $item->product->name ?? '-' }}</div>
            @if($item->product?->code)
              <div class="tx-mono" style="font-size: 10.5px; color: #4b5563;">{{ $item->product->code }}</div>
            @endif
          </td>
          <td class="text-center">
            <span class="stk-doc-badge">
              {{ $item->direction === 'in' ? __('operations.print_direction_in') : __('operations.print_direction_out') }}
            </span>
          </td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::number($item->quantity, 4) }} {{ $item->product?->unit?->symbol ?? '' }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($item->unit_cost, 6) }}</td>
          <td class="text-right tx-mono" style="font-weight: 600;">{{ \App\Support\StockoraFormat::currency($item->total_cost, 2) }}</td>
          <td>{{ $item->valuation_basis }}</td>
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
      <div class="stk-doc-box-title">{{ __('operations.reason_opname_label') }}</div>
      <div style="white-space: pre-line;">{{ $adjustment->reason ?: __('operations.adjustment_default_reason') }}</div>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('app.total') }}</div>
      <table class="stk-totals-table">
        <tr>
          <td>{{ __('operations.print_direction_in') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($totalIn, 2) }}</td>
        </tr>
        <tr>
          <td>{{ __('operations.print_direction_out') }}</td>
          <td class="text-right tx-mono">{{ \App\Support\StockoraFormat::currency($totalOut, 2) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <div class="stk-signature-grid">
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('app.prepared_by') }}</div>
      <div style="font-size: 11px; color: #374151;">{{ $adjustment->user->name ?? __('app.system') }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('app.verified_by') }}</div>
      <div style="font-size: 11px; color: #374151;">&nbsp;</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('app.approved_by') }}</div>
      <div style="font-size: 11px; color: #374151;">&nbsp;</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
  </div>
@endsection
