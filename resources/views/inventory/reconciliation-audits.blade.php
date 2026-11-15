@extends('layouts.admin')

@section('title', __('operations.reconciliation_tab'))
@section('page_title', __('operations.reconciliation_heading'))
@section('page_subtitle', __('operations.reconciliation_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_warehouse_stock']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('inventory.warehouses.index') }}">{{ __('operations.stock_overview_breadcrumb') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.reconciliation_tab') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('inventory_reports', 'view') && ($planFeatures['module_inventory_reports']['enabled'] ?? false))
    <a href="{{ route('reports.index') }}" class="btn btn-white">
      <i data-feather="bar-chart-2" class="svg-14"></i> {{ __('operations.reports_exports_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm mg-b-20">
    <div class="col-6 col-xl-3 mg-b-10 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">HISTORICALLY_VERIFIED</div>
            <span class="stk-kpi-icon stk-kpi-icon-emerald"><i data-feather="check-circle" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric tx-success">{{ __('operations.skus_count', ['count' => \App\Support\StockoraFormat::number($summaryByClass['HISTORICALLY_VERIFIED'])]) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3 mg-b-10 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">CHAIN_CONSISTENT</div>
            <span class="stk-kpi-icon stk-kpi-icon-blue"><i data-feather="link" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric tx-info">{{ __('operations.skus_count', ['count' => \App\Support\StockoraFormat::number($summaryByClass['CHAIN_CONSISTENT'])]) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3 mg-b-10 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">BASELINE_INITIALIZATION</div>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="database" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric tx-brand-01">{{ __('operations.skus_count', ['count' => \App\Support\StockoraFormat::number($summaryByClass['BASELINE_INITIALIZATION'])]) }}</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('operations.chain_break_quarantined') }}</div>
            <span class="stk-kpi-icon {{ $summaryByClass['CHAIN_BREAK'] > 0 ? 'stk-kpi-icon-red' : 'stk-kpi-icon-emerald' }}">
              <i data-feather="shield-off" class="svg-16"></i>
            </span>
          </div>
          <div class="stk-kpi-value tx-numeric {{ $summaryByClass['CHAIN_BREAK'] > 0 ? 'tx-danger' : 'tx-success' }}">{{ __('operations.skus_count', ['count' => \App\Support\StockoraFormat::number($summaryByClass['CHAIN_BREAK'])]) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4 tx-numeric">{{ __('operations.legacy_units_count', ['count' => \App\Support\StockoraFormat::number($quarantinedLegacyStock, 2)]) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card card-hover mg-b-20">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px 14px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.reconciliation_trail_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($audits->total()) }}</span>
      </div>
      <form action="{{ route('reconciliation-audits.index') }}" method="GET" class="d-flex flex-wrap align-items-center" style="gap: 6px;">
        <select name="classification" class="form-control form-control-sm" style="width: auto; min-width: 185px;">
          <option value="">{{ __('operations.all_classifications') }}</option>
          @foreach(['CHAIN_BREAK', 'HISTORICALLY_VERIFIED', 'CHAIN_CONSISTENT', 'BASELINE_INITIALIZATION'] as $cls)
            <option value="{{ $cls }}" {{ $classification === $cls ? 'selected' : '' }}>{{ $cls }}</option>
          @endforeach
        </select>
        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" style="width: auto; min-width: 165px;" placeholder="{{ __('operations.search_sku_name_placeholder') }}">
        <button type="submit" class="btn btn-sm btn-brand-01">{{ __('app.apply') }}</button>
        @if($classification !== '' || $actionTaken !== '' || $search !== '')
          <a href="{{ route('reconciliation-audits.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
        @endif
      </form>
    </div>

    @if($audits->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('operations.no_reconciliation_title'),
          'description' => __('operations.no_reconciliation_desc'),
          'icon' => 'shield',
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('purchases.col_product') }}</th>
              <th>{{ __('operations.warehouse_label') }}</th>
              <th>{{ __('operations.col_classification') }}</th>
              <th class="text-right">{{ __('operations.col_legacy_stock') }}</th>
              <th class="text-right">{{ __('operations.col_computed_opening') }}</th>
              <th>{{ __('operations.col_backfill_action') }}</th>
              <th>{{ __('operations.col_audit_notes_reason') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($audits as $audit)
              @php $isBlocked = $audit->action_taken === 'BLOCKED_NEEDS_REVIEW'; @endphp
              <tr class="{{ $isBlocked ? 'bg-warning-light' : '' }}">
                <td>
                  @if($audit->product)
                    <a href="{{ route('products.show', $audit->product) }}" class="tx-medium tx-color-01">{{ $audit->product->code }}</a>
                    <div class="tx-11 tx-color-03">{{ $audit->product->name }}</div>
                  @else - @endif
                </td>
                <td><span class="badge badge-light">{{ $audit->warehouse->code ?? '-' }}</span></td>
                <td>@include('partials.status-badge', ['status' => $audit->classification])</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($audit->legacy_product_stock, 4) }}</td>
                <td class="text-right tx-numeric">{{ $audit->computed_opening_qty !== null ? \App\Support\StockoraFormat::number($audit->computed_opening_qty, 4) : '-' }}</td>
                <td><span class="badge {{ $isBlocked ? 'badge-danger' : 'badge-light' }}">{{ $audit->action_taken }}</span></td>
                <td class="tx-12">
                  @if($audit->blocking_reason)
                    <span class="tx-danger tx-medium">{{ $audit->blocking_reason }}</span>
                  @else
                    <span class="tx-color-03">{{ __('operations.verified_safe_note') }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($audits->hasPages())
      <div class="card-footer">{{ $audits->links() }}</div>
    @endif
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="card-title mg-b-0">{{ __('operations.flagged_po_items_title') }}</h6>
      <span class="badge {{ $flaggedPurchaseItems->count() > 0 ? 'badge-warning' : 'badge-light' }} tx-numeric">{{ \App\Support\StockoraFormat::number($flaggedPurchaseItems->count()) }}</span>
    </div>
    @if($flaggedPurchaseItems->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('operations.no_flagged_po_title'),
          'description' => __('operations.no_flagged_po_desc'),
          'icon' => 'check-circle',
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('purchases.po_number') }}</th>
              <th>{{ __('purchases.supplier') }}</th>
              <th>{{ __('purchases.col_product') }}</th>
              <th class="text-right">{{ __('operations.col_qty_price') }}</th>
              <th class="text-center">{{ __('operations.col_conversion_status') }}</th>
              <th class="text-center">{{ __('operations.col_cost_breakdown_status') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($flaggedPurchaseItems as $item)
              <tr>
                <td class="tx-medium">
                  @if($item->purchase && Auth::user()?->canAccessModule('purchase_orders', 'view') && ($planFeatures['module_purchase_orders']['enabled'] ?? false) && Route::has('purchases.show'))
                    <a href="{{ route('purchases.show', $item->purchase) }}">{{ $item->purchase->purchase_number }}</a>
                  @else
                    {{ $item->purchase->purchase_number ?? 'PO #' . $item->purchase_id }}
                  @endif
                </td>
                <td>{{ $item->purchase->supplier->name ?? '-' }}</td>
                <td>
                  <span class="tx-medium">{{ $item->product->code ?? '-' }}</span>
                  <span class="tx-11 tx-color-03">{{ $item->product->name ?? '' }}</span>
                </td>
                <td class="text-right tx-numeric">
                  {{ \App\Support\StockoraFormat::number($item->quantity, 2) }} {{ $item->unit->symbol ?? '' }} &commat; {{ \App\Support\StockoraFormat::currency($item->unit_price, 2) }}
                </td>
                <td class="text-center"><span class="badge {{ $item->conversion_status === 'NEEDS_REVIEW' ? 'badge-warning' : 'badge-success' }}">{{ $item->conversion_status }}</span></td>
                <td class="text-center"><span class="badge {{ $item->cost_breakdown_status === 'UNKNOWN_COMPONENTS' ? 'badge-warning' : 'badge-success' }}">{{ $item->cost_breakdown_status }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endsection
