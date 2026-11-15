@extends('layouts.print')

@section('title', __('operations.print_issue_btn') . ' - ' . $issue->issue_number)
@section('back_url', route('issues.show', $issue))
@section('doc_heading', __('operations.print_issue_heading'))
@section('doc_number', $issue->issue_number)

@section('doc_status_meta')
  <div class="stk-doc-status-line">
    <span class="stk-doc-badge">
      {{ $issue->reversalAdjustment ? __('purchases.statuses.reversed') : __('operations.status_posted') }}
    </span>
  </div>
@endsection

@section('print_content')
  @if($issue->reversalAdjustment)
    <div class="stk-doc-alert">
      {{ __('purchases.print_reversed_notice', ['rev' => $issue->reversalAdjustment->adjustment_number]) }}
    </div>
  @endif

  <div class="stk-doc-grid">
    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.issue_header_title') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('operations.issue_number') }}</td>
          <td class="stk-kv-val tx-mono">{{ $issue->issue_number }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.issuing_warehouse_label') }}</td>
          <td class="stk-kv-val">{{ $issue->warehouse?->code }} &mdash; {{ $issue->warehouse?->name ?? '-' }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('app.status') }}</td>
          <td class="stk-kv-val">{{ $issue->reversalAdjustment ? __('purchases.statuses.reversed') : __('operations.status_posted') }}</td>
        </tr>
      </table>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('operations.col_posted_user') }}</div>
      <table class="stk-kv-table">
        <tr>
          <td class="stk-kv-label">{{ __('operations.posted_at') }}</td>
          <td class="stk-kv-val">{{ \App\Support\StockoraFormat::dateTime($issue->posted_at ?? $issue->created_at) }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.posted_by') }}</td>
          <td class="stk-kv-val">{{ $issue->user->name ?? __('app.system') }}</td>
        </tr>
        <tr>
          <td class="stk-kv-label">{{ __('operations.col_lines') }}</td>
          <td class="stk-kv-val">{{ __('operations.items_count_inline', ['count' => $issue->items->count()]) }}</td>
        </tr>
      </table>
    </div>
  </div>

  <table class="stk-items-table">
    <thead>
      <tr>
        <th style="width: 34px;" class="text-center">#</th>
        <th>{{ __('purchases.col_product') }}</th>
        <th style="width: 115px;" class="text-right">{{ __('operations.col_qty_issued') }}</th>
        <th style="width: 115px;" class="text-right">{{ __('operations.col_base_qty') }}</th>
        <th style="width: 120px;" class="text-right">{{ __('operations.col_mac_cost') }}</th>
        <th style="width: 130px;" class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
      </tr>
    </thead>
    <tbody>
      @php($totalValue = '0')
      @forelse($issue->items as $idx => $item)
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
      <div class="stk-doc-box-title">{{ __('operations.issue_notes_label') }}</div>
      <div style="white-space: pre-line;">{{ $issue->notes ?: '-' }}</div>
    </div>

    <div class="stk-doc-box">
      <div class="stk-doc-box-title">{{ __('app.total') }}</div>
      <table class="stk-totals-table">
        <tr>
          <td>{{ __('operations.col_lines') }}</td>
          <td class="text-right tx-mono">{{ __('operations.items_count_inline', ['count' => $issue->items->count()]) }}</td>
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
      <div style="font-size: 11px; color: #374151;">{{ $issue->user->name ?? __('app.system') }}</div>
      <div class="stk-signature-line">{{ __('app.signature_name_date') }}</div>
    </div>
    <div class="stk-signature-box">
      <div class="stk-signature-role">{{ __('operations.print_requested_by') }}</div>
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
