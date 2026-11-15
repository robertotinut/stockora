@extends('layouts.admin')

@section('title', $adjustment->exists ? __('operations.adjustments_edit') : __('operations.adjustments_create'))
@section('page_title', $adjustment->exists ? __('operations.edit_draft_heading', ['number' => $adjustment->adjustment_number]) : __('operations.create_adjustment_heading'))
@section('page_subtitle', __('operations.adjustment_form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('adjustments.index') }}">{{ __('operations.adjustments_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $adjustment->exists ? __('operations.edit_draft_btn') : __('app.create') }}</li>
@endsection

@section('content')
  @include('partials.quota-banner', ['limitKey' => 'max_monthly_stock_movements'])

  @php
    $initialLines = old('lines');
    if ($initialLines === null) {
        if ($adjustment->exists && $adjustment->items->isNotEmpty()) {
            $initialLines = $adjustment->items->map(fn ($it) => [
                'product_id' => $it->product_id,
                'direction' => $it->direction,
                'quantity' => rtrim(rtrim((string) $it->quantity, '0'), '.'),
                'unit_cost' => rtrim(rtrim((string) $it->unit_cost, '0'), '.') ?: '0',
            ])->toArray();
        } else {
            $initialLines = [['product_id' => '', 'direction' => 'in', 'quantity' => '1', 'unit_cost' => '0']];
        }
    }
  @endphp

  <form action="{{ $adjustment->exists ? route('adjustments.update', $adjustment) : route('adjustments.store') }}" method="POST">
    @csrf
    @if($adjustment->exists)
      @method('PUT')
    @else
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $adjustment->idempotency_key) }}">
    @endif

    <div class="card card-hover mg-b-20">
      <div class="card-header">
        <h6 class="card-title mg-b-0">{{ __('operations.adjustment_header_title') }}</h6>
      </div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>
              {{ __('operations.document_number') }}
              <span class="badge badge-light ml-1 tx-10">{{ $adjustment->exists ? __('app.locked_label') : __('app.auto_label') }}</span>
            </label>
            <input type="text"
                   value="{{ $adjustment->adjustment_number }}"
                   class="form-control stk-docnum-readonly"
                   readonly
                   tabindex="-1"
                   aria-readonly="true">
            <small class="form-text text-muted tx-11">
              {{ $adjustment->exists ? __('app.document_number_immutable') : __('app.auto_generated_upon_save') }}
            </small>
          </div>
          <div class="form-group col-md-4">
            <label>{{ __('operations.warehouse_label') }} <span class="tx-danger">*</span></label>
            <select name="warehouse_id" class="form-control @error('warehouse_id') is-invalid @enderror" {{ $adjustment->exists ? 'disabled' : 'required' }}>
              @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}" {{ (string) old('warehouse_id', $adjustment->warehouse_id) === (string) $wh->id || (!$adjustment->exists && !old('warehouse_id') && $wh->is_default) ? 'selected' : '' }}>
                  {{ $wh->code }} - {{ $wh->name }}
                </option>
              @endforeach
            </select>
            @error('warehouse_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-group col-md-4">
            <label>{{ __('operations.reason_opname_label') }} <span class="tx-danger">*</span></label>
            <input type="text" name="reason" value="{{ old('reason', $adjustment->reason) }}" class="form-control @error('reason') is-invalid @enderror" placeholder="{{ __('operations.reason_opname_placeholder') }}" required>
            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
        </div>
        <div class="alert alert-info tx-12 mg-b-0">
          <strong>{{ __('operations.valuation_rule_title') }}</strong> {{ __('operations.valuation_rule_body') }}
        </div>
      </div>
    </div>

    <div class="card card-hover mg-b-20">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mg-b-0">{{ __('operations.adjustment_lines_title') }}</h6>
        <button type="button" class="btn btn-xs btn-white" id="addAdjRow">
          <i data-feather="plus" class="svg-14"></i> {{ __('operations.add_line_btn') }}
        </button>
      </div>
      <div class="table-responsive">
        <table class="table mg-b-0" id="adjItemsTable">
          <thead>
            <tr>
              <th style="min-width: 240px;">{{ __('operations.col_product_base_unit') }}</th>
              <th style="min-width: 165px;">{{ __('operations.col_direction') }}</th>
              <th class="text-right" style="min-width: 130px;">{{ __('operations.col_base_qty') }}</th>
              <th class="text-right" style="min-width: 160px;">{{ __('operations.col_unit_cost_opt') }}</th>
              <th class="text-center" style="width: 56px;"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($initialLines as $idx => $line)
              <tr class="adj-row">
                <td>
                  <select name="lines[{{ $idx }}][product_id]" class="form-control form-control-sm" required>
                    <option value="">{{ __('operations.select_product') }}</option>
                    @foreach($products as $p)
                      <option value="{{ $p->id }}" {{ (string) ($line['product_id'] ?? '') === (string) $p->id ? 'selected' : '' }}>
                        {{ $p->code }} - {{ $p->name }} ({{ $p->unit->symbol ?? '' }} | {{ __('operations.stock_inline') }} {{ rtrim(rtrim((string) $p->stock, '0'), '.') ?: '0' }})
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="lines[{{ $idx }}][direction]" class="form-control form-control-sm" required>
                    <option value="in" {{ ($line['direction'] ?? 'in') === 'in' ? 'selected' : '' }}>{{ __('operations.direction_in') }}</option>
                    <option value="out" {{ ($line['direction'] ?? '') === 'out' ? 'selected' : '' }}>{{ __('operations.direction_out') }}</option>
                  </select>
                </td>
                <td>
                  <input type="number" step="0.0001" min="0.0001" name="lines[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" class="form-control form-control-sm text-right tx-numeric" required>
                </td>
                <td>
                  <input type="number" step="0.000001" min="0" name="lines[{{ $idx }}][unit_cost]" value="{{ $line['unit_cost'] ?? '0' }}" class="form-control form-control-sm text-right tx-numeric">
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-xs btn-outline-danger remove-adj-row">&times;</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex flex-wrap justify-content-end" style="gap: 8px;">
      <a href="{{ $adjustment->exists ? route('adjustments.show', $adjustment) : route('adjustments.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
      <button type="submit" name="submit_action" value="draft" class="btn btn-white">{{ __('operations.save_draft_short') }}</button>
      @if(Auth::user()?->canAccessModule('stock_adjustments', 'approve'))
        <button type="submit" name="submit_action" value="post" class="btn btn-brand-01">{{ __('operations.post_to_ledger_btn') }}</button>
      @endif
    </div>
  </form>
@endsection

@push('scripts')
<script>
$(function () {
  var rowIdx = {{ count($initialLines) }};
  $('#addAdjRow').on('click', function () {
    var $row = $('#adjItemsTable tbody tr.adj-row:first').clone();
    $row.find('select, input').each(function () {
      var name = $(this).attr('name');
      if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + rowIdx + ']'));
      if ($(this).is('select')) {
        $(this).val($(this).find('option:first').val());
      } else if (name && name.indexOf('[quantity]') !== -1) {
        $(this).val('1');
      } else {
        $(this).val('0');
      }
    });
    $('#adjItemsTable tbody').append($row);
    rowIdx++;
  });
  $(document).on('click', '.remove-adj-row', function () {
    if ($('#adjItemsTable tbody tr.adj-row').length > 1) $(this).closest('tr').remove();
  });
});
</script>
@endpush
