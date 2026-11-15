@extends('layouts.admin')

@section('title', __('operations.movements_tab_title'))
@section('page_title', __('operations.movements_heading'))
@section('page_subtitle', __('operations.movements_index_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_warehouse_stock']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('inventory.warehouses.index') }}">{{ __('operations.inventory_breadcrumb') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.movements_tab_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('inventory_reports', 'view') && ($planFeatures['module_inventory_reports']['enabled'] ?? false))
    <a href="{{ route('reports.index') }}" class="btn btn-white">
      <i data-feather="download" class="svg-14"></i> {{ __('operations.reports_csv_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm mg-b-20">
    <div class="col-md-6 mg-b-10 mg-md-b-0">
      <div class="card card-hover stk-kpi-card">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="stk-kpi-label mg-b-4">{{ __('operations.total_inbound_filtered') }}</div>
            <div class="stk-kpi-value tx-numeric tx-success">+{{ __('operations.units_suffix', ['qty' => \App\Support\StockoraFormat::number($inQty, 4)]) }}</div>
          </div>
          <div class="text-right">
            <span class="stk-kpi-icon stk-kpi-icon-emerald mg-b-4"><i data-feather="arrow-down-left" class="svg-16"></i></span>
            <div class="tx-13 tx-numeric tx-medium tx-color-02">{{ \App\Support\StockoraFormat::currency($inVal, 2) }}</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card card-hover stk-kpi-card">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <div class="stk-kpi-label mg-b-4">{{ __('operations.total_outbound_filtered') }}</div>
            <div class="stk-kpi-value tx-numeric tx-danger">-{{ __('operations.units_suffix', ['qty' => \App\Support\StockoraFormat::number($outQty, 4)]) }}</div>
          </div>
          <div class="text-right">
            <span class="stk-kpi-icon stk-kpi-icon-red mg-b-4"><i data-feather="arrow-up-right" class="svg-16"></i></span>
            <div class="tx-13 tx-numeric tx-medium tx-color-02">{{ \App\Support\StockoraFormat::currency($outVal, 2) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('stock-movements.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-3 mg-b-10">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('app.search') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('operations.search_sku_notes_placeholder') }}">
        </div>
        <div class="col-sm-6 col-md-3 mg-b-10">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.warehouse_label') }}</label>
          <select name="warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_sites') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $warehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-3 mg-b-10">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('purchases.col_product') }}</label>
          <select name="product_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_products') }}</option>
            @foreach($products as $p)
              <option value="{{ $p->id }}" {{ (string) $productId === (string) $p->id ? 'selected' : '' }}>{{ $p->code }} - {{ \Illuminate\Support\Str::limit($p->name, 20) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-3 mg-b-10">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.movement_type_label') }}</label>
          <select name="type" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_types') }}</option>
            @foreach(['opening_balance', 'in', 'out', 'transfer_in', 'transfer_out', 'issue', 'adjustment', 'reversal_in', 'reversal_out'] as $t)
              <option value="{{ $t }}" {{ $type === $t ? 'selected' : '' }}>{{ strtoupper($t) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-md-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.col_direction') }}</label>
          <select name="direction" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_directions') }}</option>
            <option value="in" {{ $direction === 'in' ? 'selected' : '' }}>IN (+)</option>
            <option value="out" {{ $direction === 'out' ? 'selected' : '' }}>OUT (-)</option>
          </select>
        </div>
        <div class="col-6 col-md-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.from_date') }}</label>
          <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.to_date') }}</label>
          <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
        </div>
        <div class="col-6 col-md-3 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $warehouseId !== '' || $productId !== '' || $type !== '' || $direction !== '' || $dateFrom !== '' || $dateTo !== '')
            <a href="{{ route('stock-movements.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.movement_entries_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($movements->total()) }}</span>
      </div>
    </div>

    @if($movements->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('operations.no_movements_title'),
          'description' => __('operations.no_movements_desc'),
          'icon' => 'activity',
          'actionUrl' => ($search !== '' || $warehouseId !== '' || $productId !== '' || $type !== '' || $direction !== '' || $dateFrom !== '' || $dateTo !== '') ? route('stock-movements.index') : null,
          'actionLabel' => __('app.reset_filters'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('operations.col_timestamp') }}</th>
              <th>{{ __('operations.warehouse_label') }}</th>
              <th>{{ __('purchases.col_product') }}</th>
              <th>{{ __('app.type') }}</th>
              <th class="text-right">{{ __('purchases.col_qty') }}</th>
              <th class="text-right">{{ __('operations.col_unit_cost') }}</th>
              <th class="text-right">{{ __('operations.col_total_cost_rp') }}</th>
              <th class="text-right">{{ __('operations.col_balance_after') }}</th>
              <th>{{ __('operations.col_ref_notes') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($movements as $mv)
              <tr>
                <td class="tx-12 tx-color-03 text-nowrap">{{ $mv->created_at ? \App\Support\StockoraFormat::dateTime($mv->created_at) : '-' }}</td>
                <td><span class="badge badge-light">{{ $mv->warehouse->code ?? '-' }}</span></td>
                <td>
                  @if($mv->product)
                    <a href="{{ route('products.show', $mv->product) }}" class="tx-medium tx-color-01">{{ $mv->product->code }}</a>
                    <div class="tx-11 tx-color-03">{{ \Illuminate\Support\Str::limit($mv->product->name, 28) }}</div>
                  @else - @endif
                </td>
                <td class="text-nowrap">
                  @include('partials.status-badge', ['status' => $mv->type])
                  <span class="badge {{ $mv->direction === 'in' ? 'badge-success' : 'badge-warning' }}">{{ strtoupper($mv->direction) }}</span>
                </td>
                <td class="text-right tx-numeric tx-semibold {{ $mv->direction === 'in' ? 'tx-success' : 'tx-danger' }}">
                  {{ $mv->direction === 'in' ? '+' : '-' }}{{ \App\Support\StockoraFormat::number($mv->quantity, 4) }} {{ $mv->product->unit->symbol ?? '' }}
                </td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mv->unit_cost, 4) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mv->total_cost, 2) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::number($mv->running_stock_after, 4) }}</td>
                <td class="tx-12">
                  @php
                    $refUrl = match ($mv->reference_type) {
                        'PurchaseReceipt' => (Auth::user()?->canAccessModule('grn', 'view') && ($planFeatures['module_grn']['enabled'] ?? false) && Route::has('grns.show')) ? route('grns.show', $mv->reference_id) : null,
                        'StockTransfer' => (Auth::user()?->canAccessModule('stock_transfers', 'view') && ($planFeatures['module_stock_transfers']['enabled'] ?? false) && Route::has('transfers.show')) ? route('transfers.show', $mv->reference_id) : null,
                        'StockIssue' => (Auth::user()?->canAccessModule('stock_issues', 'view') && ($planFeatures['module_stock_issues']['enabled'] ?? false) && Route::has('issues.show')) ? route('issues.show', $mv->reference_id) : null,
                        'StockAdjustment' => (Auth::user()?->canAccessModule('stock_adjustments', 'view') && ($planFeatures['module_stock_adjustments']['enabled'] ?? false) && Route::has('adjustments.show')) ? route('adjustments.show', $mv->reference_id) : null,
                        default => null,
                    };
                  @endphp
                  @if($refUrl)
                    <a href="{{ $refUrl }}" class="tx-medium">{{ $mv->reference_type }} #{{ $mv->reference_id }}</a>
                  @elseif($mv->reference_type)
                    <span class="tx-medium">{{ $mv->reference_type }} #{{ $mv->reference_id }}</span>
                  @endif
                  <div class="tx-color-03">{{ $mv->user->name ?? __('app.system') }} &bull; {{ \Illuminate\Support\Str::limit($mv->notes ?? '-', 45) }}</div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($movements->hasPages())
      <div class="card-footer">{{ $movements->links() }}</div>
    @endif
  </div>
@endsection
