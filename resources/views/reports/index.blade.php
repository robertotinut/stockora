@extends('layouts.admin')

@section('title', __('reports.page_title'))
@section('page_title', __('reports.page_heading'))
@section('page_subtitle', __('reports.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('reports.breadcrumb') }}</li>
@endsection

@php
  $canExportReports = Auth::user()?->canAccessModule('inventory_reports', 'export') ?? false;
  $hasExportPlanFeature = $planFeatures['module_report_exports']['enabled'] ?? false;
  $hasAuditPlanFeature = $planFeatures['module_reconciliation_audits']['enabled'] ?? false;
  $canViewPo = (Auth::user()?->canAccessModule('purchase_orders', 'view') ?? false) && ($planFeatures['module_purchase_orders']['enabled'] ?? false);
  $canViewProducts = Auth::user()?->canAccessModule('products', 'view') ?? false;
@endphp

@section('actions')
  @if($canExportReports && $hasExportPlanFeature)
    <a href="{{ route('reports.export', ['type' => 'valuation', 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-white">
      <i data-feather="download" class="svg-14"></i> {{ __('reports.csv_valuation_btn') }}
    </a>
    <a href="{{ route('reports.export', ['type' => 'movements', 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-white">
      <i data-feather="download" class="svg-14"></i> {{ __('reports.csv_movements_btn') }}
    </a>
    <a href="{{ route('reports.export', ['type' => 'exceptions', 'warehouse_id' => $warehouseId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-brand-01">
      <i data-feather="download" class="svg-14"></i> {{ __('reports.csv_exceptions_btn') }}
    </a>
  @endif
@endsection

@section('content')
  @if($canExportReports && !$hasExportPlanFeature)
    <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center mg-b-20" style="gap: 10px;">
      <div class="d-flex align-items-center tx-13" style="gap: 8px;">
        <i data-feather="lock" class="svg-15 text-warning flex-shrink-0"></i>
        <span>{{ __('reports.export_locked_hint') }}</span>
      </div>
      <a href="{{ route('plans.index') }}" class="btn btn-xs btn-brand-01">{{ __('subscription.compare_plans_btn') }}</a>
    </div>
  @endif

  @php
    $periodTxCount = collect($movementSummary)->sum('tx_count');
    $totalExceptionsCount = $quarantinedAudits->count() + $flaggedPurchaseItems->count() + $lowStockProducts->count();
  @endphp

  {{-- Executive Summary Strip --}}
  <div class="row row-sm mg-b-20">
    <div class="col-sm-6 col-xl-3 mg-b-15 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('reports.kpi_trusted_val_title') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-blue"><i data-feather="layers" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric tx-brand-01">{{ \App\Support\StockoraFormat::currency($grandTrustedVal, 2) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4 tx-numeric">{{ __('reports.trusted_units_suffix', ['qty' => \App\Support\StockoraFormat::number($grandTrustedQty, 2)]) }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mg-b-15 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('reports.kpi_warehouses_covered') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="map-pin" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ __('reports.sites_count', ['count' => count($warehouseSummary)]) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('reports.quarantined_excluded_note') }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mg-b-15 mg-sm-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('reports.kpi_period_tx') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-emerald"><i data-feather="activity" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ __('reports.entries_count', ['count' => \App\Support\StockoraFormat::number($periodTxCount, 0)]) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('reports.date_range_to', ['from' => \App\Support\StockoraFormat::shortDate($dateFrom), 'to' => \App\Support\StockoraFormat::shortDate($dateTo)]) }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('reports.kpi_alerts_title') }}</div>
            <span class="stk-kpi-icon {{ $totalExceptionsCount > 0 ? 'stk-kpi-icon-amber' : 'stk-kpi-icon-emerald' }}">
              <i data-feather="alert-circle" class="svg-16"></i>
            </span>
          </div>
          <div class="stk-kpi-value tx-numeric {{ $totalExceptionsCount > 0 ? 'tx-warning' : 'tx-success' }}">{{ __('reports.flags_count', ['count' => $totalExceptionsCount]) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">
            {{ __('reports.alerts_breakdown', ['quarantine' => $quarantinedAudits->count(), 'low_stock' => $lowStockProducts->count()]) }}
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filter Bar --}}
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('reports.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('reports.movement_from') }}</label>
          <input type="date" name="date_from" value="{{ $dateFrom }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-6 col-md-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('reports.movement_to') }}</label>
          <input type="date" name="date_to" value="{{ $dateTo }}" class="form-control form-control-sm">
        </div>
        <div class="col-sm-7 col-md-4 mg-b-10 mg-sm-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('reports.warehouse_scope') }}</label>
          <select name="warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('reports.all_warehouses') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $warehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-5 col-md-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($warehouseId !== '')
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  {{-- 1. Valuation Table --}}
  <div class="card card-hover mg-b-20">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 6px 12px;">
      <h6 class="card-title mg-b-0">{{ __('reports.warehouse_summary_numbered') }}</h6>
      <span class="tx-numeric tx-semibold tx-brand-01 tx-13">
        {{ __('reports.total_trusted_summary', [
          'value' => \App\Support\StockoraFormat::currency($grandTrustedVal, 2),
          'qty' => \App\Support\StockoraFormat::number($grandTrustedQty, 2),
        ]) }}
      </span>
    </div>
    @if(empty($warehouseSummary))
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('reports.no_valuation_rows_title'),
          'description' => __('reports.no_valuation_rows_desc'),
          'icon' => 'layers',
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('reports.cols.warehouse') }}</th>
              <th class="text-right">{{ __('reports.cols.sku_count') }}</th>
              <th class="text-right">{{ __('reports.cols.total_qty') }}</th>
              <th class="text-right">{{ __('reports.cols.verified_val') }} ({{ __('app.currency_prefix') }})</th>
              <th class="text-right">{{ __('reports.cols.legacy_val') }} ({{ __('app.currency_prefix') }})</th>
              <th class="text-right">{{ __('reports.cols.mixed_val') }} ({{ __('app.currency_prefix') }})</th>
              <th class="text-right">{{ __('reports.cols.total_val') }} ({{ __('app.currency_prefix') }})</th>
            </tr>
          </thead>
          <tbody>
            @foreach($warehouseSummary as $sum)
              <tr>
                <td>
                  <span class="badge badge-light mg-r-4">{{ $sum['code'] }}</span>
                  <span class="tx-medium tx-color-01">{{ $sum['name'] }}</span>
                </td>
                <td class="text-right tx-numeric">{{ $sum['sku_count'] }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($sum['total_qty'], 4) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($sum['verified_val'], 2) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($sum['legacy_val'], 2) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($sum['mixed_val'], 2) }}</td>
                <td class="text-right tx-numeric tx-semibold">{{ \App\Support\StockoraFormat::currency($sum['total_value'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  {{-- 2 & 3. Movements Summary + Exceptions --}}
  <div class="row row-sm">
    <div class="col-lg-6 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('reports.period_movements_numbered') }}</h6>
          <span class="badge badge-light tx-11">{{ \App\Support\StockoraFormat::shortDate($dateFrom) }} &rarr; {{ \App\Support\StockoraFormat::shortDate($dateTo) }}</span>
        </div>
        @if(empty($movementSummary))
          <div class="card-body d-flex align-items-center justify-content-center">
            @include('partials.empty-state', [
              'title' => __('reports.no_period_movements_title'),
              'description' => __('reports.no_period_movements_desc'),
              'icon' => 'activity',
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('reports.cols.movement_type') }}</th>
                  <th>{{ __('reports.col_dir') }}</th>
                  <th class="text-right">{{ __('reports.col_tx') }}</th>
                  <th class="text-right">{{ __('reports.cols.total_qty') }}</th>
                  <th class="text-right">{{ __('reports.cols.total_cost') }} ({{ __('app.currency_prefix') }})</th>
                </tr>
              </thead>
              <tbody>
                @foreach($movementSummary as $ms)
                  <tr>
                    <td>@include('partials.status-badge', ['status' => $ms['type']])</td>
                    <td><span class="badge {{ $ms['direction'] === 'in' ? 'badge-success' : 'badge-warning' }}">{{ strtoupper($ms['direction']) }}</span></td>
                    <td class="text-right tx-numeric">{{ $ms['tx_count'] }}</td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($ms['total_qty'], 4) }}</td>
                    <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($ms['total_cost'], 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('reports.exceptions_numbered') }}</h6>
          @if(Route::has('reconciliation-audits.index') && $hasAuditPlanFeature && (Auth::user()?->canAccessModule('warehouse_stock', 'view') ?? false))
            <a href="{{ route('reconciliation-audits.index') }}" class="tx-12 tx-medium">{{ __('reports.full_audit_log_btn') }}</a>
          @endif
        </div>
        <div class="card-body tx-13">
          <div class="mg-b-16">
            <div class="d-flex justify-content-between align-items-center mg-b-8">
              <span class="tx-11 tx-uppercase tx-semibold tx-color-03">{{ __('reports.quarantined_skus_label') }}</span>
              <span class="badge {{ $quarantinedAudits->count() > 0 ? 'badge-danger' : 'badge-success' }}">{{ $quarantinedAudits->count() }}</span>
            </div>
            @forelse($quarantinedAudits as $qa)
              <div class="alert alert-danger py-2 px-3 tx-12 mg-b-8">
                <strong>{{ $qa->product->code ?? '-' }}</strong> &mdash; {{ $qa->blocking_reason }}
                <span class="d-block tx-11 mg-t-2">{{ __('reports.legacy_stock_inline', ['qty' => \App\Support\StockoraFormat::number($qa->legacy_product_stock, 4)]) }}</span>
              </div>
            @empty
              <p class="tx-12 tx-color-03 mg-b-0">{{ __('reports.no_quarantined_skus') }}</p>
            @endforelse
          </div>

          <div class="pt-3 border-top mg-b-16">
            <div class="d-flex justify-content-between align-items-center mg-b-8">
              <span class="tx-11 tx-uppercase tx-semibold tx-color-03">{{ __('reports.po_review_label') }}</span>
              <span class="badge {{ $flaggedPurchaseItems->count() > 0 ? 'badge-warning' : 'badge-success' }}">{{ $flaggedPurchaseItems->count() }}</span>
            </div>
            @if($flaggedPurchaseItems->isNotEmpty())
              <div class="list-group list-group-flush">
                @foreach($flaggedPurchaseItems as $pi)
                  <div class="stk-list-row d-flex justify-content-between align-items-center tx-12">
                    <div>
                      @if($canViewPo)
                        <a href="{{ route('purchases.show', $pi->purchase_id) }}" class="tx-medium">{{ $pi->purchase->purchase_number ?? 'PO #' . $pi->purchase_id }}</a>
                      @else
                        <span class="tx-medium">{{ $pi->purchase->purchase_number ?? 'PO #' . $pi->purchase_id }}</span>
                      @endif
                      <span class="tx-color-03">&bull; {{ $pi->product->code ?? '-' }}</span>
                    </div>
                    <span class="badge badge-warning">{{ $pi->conversion_status }} / {{ $pi->cost_breakdown_status }}</span>
                  </div>
                @endforeach
              </div>
            @else
              <p class="tx-12 tx-color-03 mg-b-0">{{ __('reports.all_po_verified') }}</p>
            @endif
          </div>

          <div class="pt-3 border-top">
            <div class="d-flex justify-content-between align-items-center mg-b-8">
              <span class="tx-11 tx-uppercase tx-semibold tx-color-03">{{ __('reports.low_stock_watchlist_label') }}</span>
              <span class="badge {{ $lowStockProducts->count() > 0 ? 'badge-warning' : 'badge-success' }}">{{ $lowStockProducts->count() }}</span>
            </div>
            @if($lowStockProducts->isNotEmpty())
              <div class="list-group list-group-flush">
                @foreach($lowStockProducts as $lp)
                  <div class="stk-list-row d-flex justify-content-between align-items-center tx-12">
                    @if($canViewProducts)
                      <a href="{{ route('products.show', $lp) }}" class="tx-medium tx-color-01">{{ $lp->code }} - {{ \Illuminate\Support\Str::limit($lp->name, 28) }}</a>
                    @else
                      <span class="tx-medium tx-color-01">{{ $lp->code }} - {{ \Illuminate\Support\Str::limit($lp->name, 28) }}</span>
                    @endif
                    <span class="tx-numeric">
                      <strong class="tx-danger">{{ \App\Support\StockoraFormat::number($lp->stock, 2) }}</strong>
                      <span class="tx-color-03">/ {{ __('reports.min_inline') }} {{ \App\Support\StockoraFormat::number($lp->min_stock, 2) }} {{ $lp->unit->symbol ?? '' }}</span>
                    </span>
                  </div>
                @endforeach
              </div>
            @else
              <p class="tx-12 tx-color-03 mg-b-0">{{ __('reports.all_skus_above_min') }}</p>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection
