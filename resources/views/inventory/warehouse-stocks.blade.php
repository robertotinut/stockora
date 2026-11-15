@extends('layouts.admin')

@section('title', __('operations.warehouse_stock_tab'))
@section('page_title', __('operations.warehouse_stock_heading'))
@section('page_subtitle', __('operations.warehouse_stock_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('warehouses', 'view') && ($planFeatures['module_warehouses']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('warehouses.index') }}">{{ __('warehouses.page_title') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.stock_overview_breadcrumb') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'manage') && ($planFeatures['module_reconciliation_audits']['enabled'] ?? false))
    <a href="{{ route('reconciliation-audits.index') }}" class="btn btn-white">
      <i data-feather="shield" class="svg-14"></i> {{ __('operations.audit_log_btn') }}
    </a>
  @endif
  @if(Auth::user()?->canAccessModule('warehouses', 'view') && ($planFeatures['module_warehouses']['enabled'] ?? false))
    <a href="{{ route('warehouses.index') }}" class="btn btn-brand-01">
      <i data-feather="home" class="svg-14"></i> {{ __('operations.manage_warehouses_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm mg-b-20">
    <div class="col-md-4 mg-b-10 mg-md-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('operations.trusted_units_label') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="layers" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::number($trustedUnits, 4) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4 mg-b-10 mg-md-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('operations.trusted_valuation_label') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-blue"><i data-feather="dollar-sign" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric tx-brand-01">{{ \App\Support\StockoraFormat::currency($trustedValue, 2) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('operations.quarantined_rows_label') }}</div>
            <span class="stk-kpi-icon {{ $quarantinedRows > 0 ? 'stk-kpi-icon-red' : 'stk-kpi-icon-emerald' }}">
              <i data-feather="shield" class="svg-16"></i>
            </span>
          </div>
          <div class="stk-kpi-value tx-numeric {{ $quarantinedRows > 0 ? 'tx-danger' : 'tx-success' }}">{{ __('operations.rows_suffix', ['count' => \App\Support\StockoraFormat::number($quarantinedRows)]) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('inventory.warehouses.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.warehouse_label') }}</label>
          <select name="warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_warehouses') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $warehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.col_valuation_basis') }}</label>
          <select name="valuation_basis" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_bases') }}</option>
            @foreach(['VERIFIED_ACQUISITION', 'MIXED_BASELINE_AND_ACQUISITION', 'ESTIMATED_LEGACY_BASELINE', 'UNVERIFIED_QUARANTINED'] as $basis)
              <option value="{{ $basis }}" {{ $valuationBasis === $basis ? 'selected' : '' }}>{{ $basis }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.stock_state_label') }}</label>
          <select name="stock_state" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_states') }}</option>
            <option value="positive" {{ $stockState === 'positive' ? 'selected' : '' }}>{{ __('operations.stock_gt_zero') }}</option>
            <option value="zero" {{ $stockState === 'zero' ? 'selected' : '' }}>{{ __('operations.stock_eq_zero') }}</option>
            <option value="quarantined" {{ $stockState === 'quarantined' ? 'selected' : '' }}>{{ __('operations.stock_quarantined') }}</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-8 col-xl-2 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.search_sku_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('operations.search_sku_placeholder') }}">
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($warehouseId !== '' || $valuationBasis !== '' || $search !== '' || $stockState !== '')
            <a href="{{ route('inventory.warehouses.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.warehouse_balances_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($rows->total()) }}</span>
      </div>
    </div>

    @if($rows->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('operations.no_warehouse_balances_title'),
          'description' => __('operations.no_warehouse_balances_desc'),
          'icon' => 'layers',
          'actionUrl' => ($warehouseId !== '' || $valuationBasis !== '' || $search !== '' || $stockState !== '') ? route('inventory.warehouses.index') : null,
          'actionLabel' => __('app.reset_filters'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('operations.warehouse_label') }}</th>
              <th>{{ __('purchases.col_product') }}</th>
              <th class="text-right">{{ __('operations.col_stock') }}</th>
              <th class="text-right">{{ __('operations.col_mac_rp') }}</th>
              <th class="text-right">{{ __('operations.col_valuation_rp') }}</th>
              <th class="text-center">{{ __('operations.col_valuation_basis') }}</th>
              <th>{{ __('operations.col_last_reconciled') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($rows as $row)
              @php $isQuarantined = $row->valuation_basis === 'UNVERIFIED_QUARANTINED'; @endphp
              <tr class="{{ $isQuarantined ? 'bg-warning-light' : '' }}">
                <td>
                  <span class="badge badge-light">{{ $row->warehouse->code ?? '-' }}</span>
                  <div class="tx-11 tx-color-03 mg-t-2">{{ $row->warehouse->name ?? '-' }}</div>
                </td>
                <td>
                  @if($row->product)
                    <a href="{{ route('products.show', $row->product) }}" class="tx-medium tx-color-01">{{ $row->product->code }} &mdash; {{ $row->product->name }}</a>
                    <div class="tx-11 tx-color-03">{{ $row->product->category->name ?? '-' }}</div>
                  @else
                    -
                  @endif
                </td>
                <td class="text-right tx-numeric tx-medium">
                  {{ \App\Support\StockoraFormat::number($row->stock, 4) }} {{ $row->product->unit->symbol ?? '' }}
                </td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($row->moving_average_cost, 6) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($row->inventory_value, 2) }}</td>
                <td class="text-center">@include('partials.status-badge', ['status' => $row->valuation_basis])</td>
                <td class="tx-12 tx-color-03 text-nowrap">{{ $row->last_reconciled_at ? \App\Support\StockoraFormat::dateTime($row->last_reconciled_at) : '-' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($rows->hasPages())
      <div class="card-footer">{{ $rows->links() }}</div>
    @endif
  </div>
@endsection
