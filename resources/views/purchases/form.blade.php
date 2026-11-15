@extends('layouts.admin')

@section('title', $purchase->exists ? __('purchases.edit_po_tab', ['number' => $purchase->purchase_number]) : __('purchases.create_title'))
@section('page_title', $purchase->exists ? __('purchases.edit_po_heading', ['number' => $purchase->purchase_number]) : __('purchases.new_po_heading'))
@section('page_subtitle', __('purchases.form_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">{{ __('purchases.page_title') }}</a></li>
  @if($purchase->exists)
    <li class="breadcrumb-item"><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_number }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('app.edit') }}</li>
  @else
    <li class="breadcrumb-item active" aria-current="page">{{ __('purchases.create_breadcrumb') }}</li>
  @endif
@endsection

@section('content')
  @if(!$purchase->exists)
    @include('partials.quota-banner', ['limitKey' => 'max_monthly_purchases'])
  @endif
  @php
    $initialLines = old('items');
    if ($initialLines === null) {
        if ($purchase->exists && $purchase->items->isNotEmpty()) {
            $initialLines = $purchase->items->map(fn ($it) => [
                'product_id' => $it->product_id,
                'unit_id' => $it->unit_id,
                'quantity' => rtrim(rtrim((string) $it->quantity, '0'), '.'),
                'unit_price' => rtrim(rtrim((string) $it->unit_price, '0'), '.') ?: '0',
            ])->toArray();
        } else {
            $preProd = $preselectedProductId ? $products->firstWhere('id', (int) $preselectedProductId) : null;
            $initialLines = [[
                'product_id' => $preProd->id ?? '',
                'unit_id' => $preProd->unit_id ?? '',
                'quantity' => '1',
                'unit_price' => $preProd ? (rtrim(rtrim((string) $preProd->purchase_price, '0'), '.') ?: '0') : '0',
            ]];
        }
    }
  @endphp

  <form action="{{ $purchase->exists ? route('purchases.update', $purchase) : route('purchases.store') }}" method="POST" id="poForm">
    @csrf
    @if($purchase->exists)
      @method('PUT')
    @endif

    <div class="card card-hover mg-b-20">
      <div class="card-header">
        <h6 class="card-title mg-b-0">{{ __('purchases.header_card_title') }}</h6>
      </div>
      <div class="card-body">
        <div class="form-row">
          <div class="form-group col-md-3">
            <label>
              {{ __('purchases.po_number') }}
              <span class="badge badge-light ml-1 tx-10">{{ $purchase->exists ? __('app.locked_label') : __('app.auto_label') }}</span>
            </label>
            <input type="text"
                   value="{{ $purchase->purchase_number }}"
                   class="form-control stk-docnum-readonly"
                   readonly
                   tabindex="-1"
                   aria-readonly="true">
            <small class="form-text text-muted tx-11">
              {{ $purchase->exists ? __('app.document_number_immutable') : __('app.auto_generated_upon_save') }}
            </small>
          </div>
          <div class="form-group col-md-4">
            <label>{{ __('purchases.supplier') }} <span class="tx-danger">*</span></label>
            <select name="supplier_id" class="form-control @error('supplier_id') is-invalid @enderror" required>
              <option value="">{{ __('purchases.select_supplier') }}</option>
              @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" {{ (string) old('supplier_id', $purchase->supplier_id) === (string) $supplier->id ? 'selected' : '' }}>
                  {{ $supplier->code }} - {{ $supplier->name }}
                </option>
              @endforeach
            </select>
            @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-group col-md-3">
            <label>{{ __('purchases.order_date') }} <span class="tx-danger">*</span></label>
            <input type="date" name="date" value="{{ old('date', $purchase->date ? \Carbon\Carbon::parse($purchase->date)->toDateString() : now()->toDateString()) }}" class="form-control @error('date') is-invalid @enderror" required>
            @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="form-group col-md-2">
            <label>{{ __('purchases.payment_status') }} <span class="tx-danger">*</span></label>
            <select name="payment_status" class="form-control @error('payment_status') is-invalid @enderror" required>
              @foreach(['unpaid', 'partial', 'paid'] as $val)
                <option value="{{ $val }}" {{ old('payment_status', $purchase->payment_status) === $val ? 'selected' : '' }}>{{ __('purchases.payment_statuses.' . $val) }}</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="form-group mg-b-0">
          <label>{{ __('purchases.notes_instructions') }}</label>
          <textarea name="notes" rows="2" class="form-control" placeholder="{{ __('purchases.notes_placeholder') }}">{{ old('notes', $purchase->notes) }}</textarea>
        </div>
      </div>
    </div>

    <div class="card card-hover mg-b-20">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
        <div>
          <h6 class="card-title mg-b-0">{{ __('purchases.items_heading') }}</h6>
          <small class="tx-11 tx-color-03">{!! __('purchases.quarantined_note', ['code' => '<code>CHAIN_BREAK</code>']) !!}</small>
        </div>
        <button type="button" class="btn btn-xs btn-white" id="addPoRow">
          <i data-feather="plus" class="svg-14"></i> {{ __('purchases.add_line_btn') }}
        </button>
      </div>
      <div class="table-responsive">
        <table class="table mg-b-0" id="poItemsTable">
          <thead>
            <tr>
              <th style="min-width: 240px;">{{ __('purchases.col_product') }}</th>
              <th style="min-width: 145px;">{{ __('purchases.col_purchase_uom') }}</th>
              <th class="text-right" style="min-width: 120px;">{{ __('purchases.col_qty') }}</th>
              <th class="text-right" style="min-width: 150px;">{{ __('purchases.col_unit_price_rp') }}</th>
              <th class="text-right" style="min-width: 150px;">{{ __('purchases.col_subtotal_rp') }}</th>
              <th class="text-center" style="width: 56px;"></th>
            </tr>
          </thead>
          <tbody>
            @foreach($initialLines as $idx => $line)
              <tr class="po-item-row">
                <td>
                  <select name="items[{{ $idx }}][product_id]" class="form-control form-control-sm po-product-select" required>
                    <option value="">{{ __('purchases.select_product') }}</option>
                    @foreach($products as $p)
                      <option value="{{ $p->id }}"
                              data-unit-id="{{ $p->unit_id }}"
                              data-price="{{ rtrim(rtrim((string) $p->purchase_price, '0'), '.') ?: '0' }}"
                              {{ (string) ($line['product_id'] ?? '') === (string) $p->id ? 'selected' : '' }}>
                        {{ $p->code }} - {{ $p->name }}
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <select name="items[{{ $idx }}][unit_id]" class="form-control form-control-sm po-unit-select" required>
                    <option value="">{{ __('purchases.uom_placeholder') }}</option>
                    @foreach($units as $u)
                      <option value="{{ $u->id }}" {{ (string) ($line['unit_id'] ?? '') === (string) $u->id ? 'selected' : '' }}>
                        {{ $u->name }} ({{ $u->symbol }})
                      </option>
                    @endforeach
                  </select>
                </td>
                <td>
                  <input type="number" step="0.0001" min="0.0001" name="items[{{ $idx }}][quantity]" value="{{ $line['quantity'] ?? '1' }}" class="form-control form-control-sm text-right tx-numeric po-qty-input" required>
                </td>
                <td>
                  <input type="number" step="0.000001" min="0" name="items[{{ $idx }}][unit_price]" value="{{ $line['unit_price'] ?? '0' }}" class="form-control form-control-sm text-right tx-numeric po-price-input" required>
                </td>
                <td class="text-right tx-numeric tx-medium align-middle po-subtotal-cell">{{ \App\Support\StockoraFormat::number(0, 2) }}</td>
                <td class="text-center align-middle">
                  <button type="button" class="btn btn-xs btn-outline-danger remove-po-row" title="{{ __('purchases.remove_row_title') }}">&times;</button>
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="bg-light">
              <th colspan="4" class="text-right tx-13">{{ __('purchases.estimated_po_total') }}</th>
              <th class="text-right tx-numeric tx-brand-01 tx-15" id="poGrandTotal">{{ \App\Support\StockoraFormat::currency(0, 2) }}</th>
              <th></th>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="d-flex flex-wrap justify-content-end" style="gap: 8px;">
      <a href="{{ $purchase->exists ? route('purchases.show', $purchase) : route('purchases.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
      <button type="submit" name="submit_action" value="draft" class="btn btn-white">{{ __('purchases.save_draft') }}</button>
      @if(Auth::user()?->canAccessModule('purchase_orders', 'approve'))
        <button type="submit" name="submit_action" value="confirm" class="btn btn-brand-01">{{ __('purchases.save_and_confirm') }}</button>
      @endif
    </div>
  </form>
@endsection

@push('scripts')
<script>
$(function () {
  var rowIndex = {{ count($initialLines) }};
  var numberLocale = @json(app()->getLocale() === 'id' ? 'id-ID' : 'en-US');

  function formatIdr(val) {
    return val.toLocaleString(numberLocale, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function recalcTotals() {
    var grand = 0;
    $('#poItemsTable tbody tr.po-item-row').each(function () {
      var qty = parseFloat($(this).find('.po-qty-input').val()) || 0;
      var price = parseFloat($(this).find('.po-price-input').val()) || 0;
      var sub = qty * price;
      grand += sub;
      $(this).find('.po-subtotal-cell').text(formatIdr(sub));
    });
    $('#poGrandTotal').text('Rp ' + formatIdr(grand));
  }

  $('#addPoRow').on('click', function () {
    var $first = $('#poItemsTable tbody tr.po-item-row:first').clone();
    $first.find('select, input').each(function () {
      var name = $(this).attr('name');
      if (name) {
        $(this).attr('name', name.replace(/\[\d+\]/, '[' + rowIndex + ']'));
      }
      if ($(this).is('select')) {
        $(this).val('');
      } else if ($(this).hasClass('po-qty-input')) {
        $(this).val('1');
      } else {
        $(this).val('0');
      }
    });
    $('#poItemsTable tbody').append($first);
    rowIndex++;
    recalcTotals();
  });

  $(document).on('click', '.remove-po-row', function () {
    if ($('#poItemsTable tbody tr.po-item-row').length <= 1) {
      return;
    }
    $(this).closest('tr').remove();
    recalcTotals();
  });

  $(document).on('change', '.po-product-select', function () {
    var $opt = $(this).find('option:selected');
    var $tr = $(this).closest('tr');
    var unitId = $opt.data('unit-id');
    var price = $opt.data('price');
    if (unitId) {
      $tr.find('.po-unit-select').val(String(unitId));
    }
    if (price !== undefined) {
      $tr.find('.po-price-input').val(price);
    }
    recalcTotals();
  });

  $(document).on('input', '.po-qty-input, .po-price-input', recalcTotals);
  recalcTotals();
});
</script>
@endpush
