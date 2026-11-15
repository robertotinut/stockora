@extends('layouts.admin')

@section('title', __('operations.issues_create'))
@section('page_title', __('operations.issues_create_heading'))
@section('page_subtitle', __('operations.issues_create_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('issues.index') }}">{{ __('operations.issues_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.new_issue_breadcrumb') }}</li>
@endsection

@section('content')
  @include('partials.quota-banner', ['limitKey' => 'max_monthly_stock_movements'])

  @php
    $initialLines = old('lines', [['product_id' => '', 'unit_id' => '', 'quantity' => '1']]);
  @endphp

  <form action="{{ route('issues.store') }}" method="POST">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

    <div class="card card-hover mg-b-20">
      <div class="card-header">
        <h6 class="card-title mg-b-0">{{ __('operations.issue_header_title') }}</h6>
      </div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-md-4">
            <label>
              {{ __('operations.issue_number') }}
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
            <label>{{ __('operations.issuing_warehouse_label') }} <span class="tx-danger">*</span></label>
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
            <label>{{ __('operations.issue_notes_label') }}</label>
            <input type="text" name="notes" value="{{ old('notes') }}" class="form-control" placeholder="{{ __('operations.issue_notes_placeholder') }}">
          </div>
        </div>
      </div>
    </div>

    <div class="card card-hover mg-b-20">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mg-b-0">{{ __('operations.items_issued_title') }}</h6>
        <button type="button" class="btn btn-xs btn-white" id="addIssueRow">
          <i data-feather="plus" class="svg-14"></i> {{ __('operations.add_line_btn') }}
        </button>
      </div>
      <div class="table-responsive">
        <table class="table mg-b-0" id="issueItemsTable">
          <thead>
            <tr>
              <th style="min-width: 260px;">{{ __('operations.col_product_active_stock') }}</th>
              <th style="min-width: 160px;">{{ __('operations.uom_col') }}</th>
              <th class="text-right" style="min-width: 140px;">{{ __('operations.col_qty_issued') }}</th>
              <th class="text-center" style="width: 56px;"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($initialLines as $idx => $line)
              <tr class="iss-row">
                <td>
                  <select name="lines[{{ $idx }}][product_id]" class="form-control form-control-sm iss-product-select" required>
                    <option value="">{{ __('operations.select_product') }}</option>
                    @foreach($products as $p)
                      <option value="{{ $p->id }}" data-unit-id="{{ $p->unit_id }}" {{ (string) ($line['product_id'] ?? '') === (string) $p->id ? 'selected' : '' }}>
                        {{ $p->code }} - {{ $p->name }} ({{ __('operations.stock_inline') }} {{ rtrim(rtrim((string) $p->stock, '0'), '.') ?: '0' }} {{ $p->unit->symbol ?? '' }})
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="lines[{{ $idx }}][unit_id]" class="form-control form-control-sm iss-unit-select" required>
                    <option value="">{{ __('operations.uom_placeholder') }}</option>
                    @foreach($units as $u)
                      <option value="{{ $u->id }}" {{ (string) ($line['unit_id'] ?? '') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->symbol }})</option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <input type="number" step="0.0001" min="0.0001" name="lines[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" class="form-control form-control-sm text-right tx-numeric" required>
                </td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-xs btn-outline-danger remove-iss-row">&times;</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex justify-content-end" style="gap: 8px;">
      <a href="{{ route('issues.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
      <button type="submit" class="btn btn-brand-01">{{ __('operations.post_issue_btn') }}</button>
    </div>
  </form>
@endsection

@push('scripts')
<script>
$(function () {
  var rowIdx = {{ count($initialLines) }};
  $('#addIssueRow').on('click', function () {
    var $row = $('#issueItemsTable tbody tr.iss-row:first').clone();
    $row.find('select, input').each(function () {
      var name = $(this).attr('name');
      if (name) $(this).attr('name', name.replace(/\[\d+\]/, '[' + rowIdx + ']'));
      if ($(this).is('select')) $(this).val('');
      else $(this).val('1');
    });
    $('#issueItemsTable tbody').append($row);
    rowIdx++;
  });
  $(document).on('click', '.remove-iss-row', function () {
    if ($('#issueItemsTable tbody tr.iss-row').length > 1) $(this).closest('tr').remove();
  });
  $(document).on('change', '.iss-product-select', function () {
    var unitId = $(this).find('option:selected').data('unit-id');
    if (unitId) $(this).closest('tr').find('.iss-unit-select').val(String(unitId));
  });
});
</script>
@endpush
