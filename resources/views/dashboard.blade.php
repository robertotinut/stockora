@extends('layouts.admin')

@php
  $dashFeatures = $planEntitlements['features'] ?? [];
  $dashPlanOn = static fn (string $k): bool => (bool) ($dashFeatures[$k]['enabled'] ?? false);
@endphp

@section('title', __('dashboard.page_title'))
@section('page_title', __('dashboard.page_heading'))
@section('page_subtitle', __('dashboard.page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('dashboard.page_title') }}</li>
@endsection

@section('actions')
  @if(Route::has('reports.index') && $dashPlanOn('module_inventory_reports') && Auth::user()?->canAccessModule('inventory_reports', 'view'))
    <a href="{{ route('reports.index') }}" class="btn btn-white">
      <i data-feather="bar-chart-2" class="svg-14"></i> {{ __('dashboard.reports_btn') }}
    </a>
  @endif
  @if(Route::has('purchases.create') && $dashPlanOn('module_purchase_orders') && Auth::user()?->canAccessModule('purchase_orders', 'create'))
    <a href="{{ route('purchases.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('dashboard.create_po_btn') }}
    </a>
  @endif
@endsection

@section('content')
  @include('partials.billing-reminder-banner', [
    'reminder' => $billingReminder ?? null,
    'company' => $activeCompany ?? null,
    'resolvedPlanName' => $planEntitlements['plan']['name'] ?? null,
  ])

  @if($quarantinedCount > 0 || $purchaseItemsNeedingReview > 0)
    <div class="alert alert-warning mg-b-20" role="alert">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center" style="gap: 10px 18px;">
        <div class="d-flex align-items-start" style="gap: 10px;">
          <i data-feather="shield" class="svg-18 flex-shrink-0 mg-t-2"></i>
          <div>
            <strong class="d-block mg-b-2">{{ __('dashboard.legacy_notice_title') }}</strong>
            <div class="tx-13">
              @if($quarantinedCount > 0)
                <span class="mg-r-15">
                  {{ __('dashboard.legacy_quarantined_text', ['count' => \App\Support\StockoraFormat::number($quarantinedCount), 'qty' => \App\Support\StockoraFormat::quantity($quarantinedLegacyStock, 2)]) }}
                </span>
              @endif
              @if($purchaseItemsNeedingReview > 0)
                <span>
                  {{ __('dashboard.legacy_flagged_po_text', ['count' => \App\Support\StockoraFormat::number($purchaseItemsNeedingReview)]) }}
                </span>
              @endif
            </div>
          </div>
        </div>
        <div class="flex-shrink-0">
          @if(Route::has('reconciliation-audits.index') && $dashPlanOn('module_reconciliation_audits') && Auth::user()?->canAccessModule('warehouse_stock', 'manage'))
            <a href="{{ route('reconciliation-audits.index') }}" class="btn btn-xs btn-warning">{{ __('dashboard.open_audit_btn') }}</a>
          @elseif(Route::has('products.index') && $dashPlanOn('module_products') && Auth::user()?->canAccessModule('products', 'view'))
            <a href="{{ route('products.index', ['stock_filter' => 'quarantined']) }}" class="btn btn-xs btn-warning">{{ __('dashboard.view_quarantined_btn') }}</a>
          @endif
        </div>
      </div>
    </div>
  @endif

  @php
    $canQuickProduct = Route::has('products.create') && $dashPlanOn('module_products') && (Auth::user()?->canAccessModule('products', 'create') ?? false);
    $canQuickPurchase = Route::has('purchases.create') && $dashPlanOn('module_purchase_orders') && (Auth::user()?->canAccessModule('purchase_orders', 'create') ?? false);
    $canQuickGrn = Route::has('grns.create') && $dashPlanOn('module_grn') && (Auth::user()?->canAccessModule('grn', 'create') ?? false);
    $canQuickTransfer = Route::has('transfers.create') && $dashPlanOn('module_stock_transfers') && (Auth::user()?->canAccessModule('stock_transfers', 'create') ?? false);
  @endphp

  @if($canQuickProduct || $canQuickPurchase || $canQuickGrn || $canQuickTransfer)
    {{-- Quick Actions Strip --}}
    <div class="stk-quick-bar mg-b-20">
      @if($canQuickProduct)
        <a href="{{ route('products.create') }}" class="stk-quick-tile">
          <span class="stk-quick-tile-icon"><i data-feather="package" class="svg-16"></i></span>
          <span>
            <span class="stk-quick-tile-title d-block">{{ __('dashboard.quick_add_product') }}</span>
            <span class="stk-quick-tile-sub d-block">{{ __('dashboard.quick_add_product_sub') }}</span>
          </span>
        </a>
      @endif
      @if($canQuickPurchase)
        <a href="{{ route('purchases.create') }}" class="stk-quick-tile">
          <span class="stk-quick-tile-icon"><i data-feather="shopping-cart" class="svg-16"></i></span>
          <span>
            <span class="stk-quick-tile-title d-block">{{ __('dashboard.quick_create_po') }}</span>
            <span class="stk-quick-tile-sub d-block">{{ __('dashboard.quick_create_po_sub') }}</span>
          </span>
        </a>
      @endif
      @if($canQuickGrn)
        <a href="{{ route('grns.create') }}" class="stk-quick-tile">
          <span class="stk-quick-tile-icon"><i data-feather="check-square" class="svg-16"></i></span>
          <span>
            <span class="stk-quick-tile-title d-block">{{ __('dashboard.quick_receive_grn') }}</span>
            <span class="stk-quick-tile-sub d-block">{{ __('dashboard.quick_receive_grn_sub') }}</span>
          </span>
        </a>
      @endif
      @if($canQuickTransfer)
        <a href="{{ route('transfers.create') }}" class="stk-quick-tile">
          <span class="stk-quick-tile-icon"><i data-feather="repeat" class="svg-16"></i></span>
          <span>
            <span class="stk-quick-tile-title d-block">{{ __('dashboard.quick_transfer') }}</span>
            <span class="stk-quick-tile-sub d-block">{{ __('dashboard.quick_transfer_sub') }}</span>
          </span>
        </a>
      @endif
    </div>
  @endif

  {{-- Primary KPI Row --}}
  <div class="row row-sm mg-b-20">
    <div class="col-sm-6 col-xl-3 mg-b-15 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mg-b-8">
              <div class="stk-kpi-label mg-b-0">{{ __('dashboard.kpi_inventory_value') }}</div>
              <span class="stk-kpi-icon stk-kpi-icon-blue"><i data-feather="layers" class="svg-18"></i></span>
            </div>
            <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::currency($stockValue, 2) }}</div>
            <div class="tx-12 tx-color-03 mg-t-4">
              {!! __('dashboard.kpi_verified_units', ['qty' => '<span class="tx-numeric tx-medium tx-color-01">' . e(\App\Support\StockoraFormat::quantity($totalUnits, 2)) . '</span>']) !!}
            </div>
          </div>
          <div class="stk-kpi-foot d-flex justify-content-between align-items-center">
            <span>{{ __('dashboard.kpi_po_spend') }} <strong class="tx-numeric tx-color-01">{{ \App\Support\StockoraFormat::currency($totalSpend, 0) }}</strong></span>
            <span class="badge badge-success">{{ __('dashboard.kpi_healthy_pct', ['pct' => $healthyPct]) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mg-b-15 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mg-b-8">
              <div class="stk-kpi-label mg-b-0">{{ __('dashboard.kpi_total_products') }}</div>
              <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="box" class="svg-18"></i></span>
            </div>
            <div class="stk-kpi-value tx-numeric">{{ __('dashboard.kpi_sku_unit', ['count' => \App\Support\StockoraFormat::number($totalProducts)]) }}</div>
            <div class="stk-progress-track mg-t-10 mg-b-6">
              <div class="stk-progress-fill bg-primary" style="width: {{ min(100, (int) $healthyPct) }}%;"></div>
            </div>
          </div>
          <div class="stk-kpi-foot d-flex justify-content-between align-items-center">
            <span>{{ __('dashboard.kpi_above_min', ['in' => \App\Support\StockoraFormat::number($inStockCount), 'total' => \App\Support\StockoraFormat::number($totalProducts)]) }}</span>
            @if(Route::has('products.index') && $dashPlanOn('module_products') && Auth::user()?->canAccessModule('products', 'view'))
              <a href="{{ route('products.index') }}" class="tx-12 tx-medium">{{ __('dashboard.kpi_catalog') }}</a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mg-b-15 mg-sm-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mg-b-8">
              <div class="stk-kpi-label mg-b-0">{{ __('dashboard.kpi_low_stock') }}</div>
              <span class="stk-kpi-icon {{ $lowStockCount > 0 ? 'stk-kpi-icon-red' : 'stk-kpi-icon-emerald' }}">
                <i data-feather="alert-triangle" class="svg-18"></i>
              </span>
            </div>
            <div class="stk-kpi-value tx-numeric {{ $lowStockCount > 0 ? 'tx-danger' : 'tx-success' }}">
              {{ __('dashboard.kpi_sku_unit', ['count' => \App\Support\StockoraFormat::number($lowStockCount)]) }}
            </div>
            <div class="tx-12 tx-color-03 mg-t-4">
              {{ __('dashboard.kpi_quarantined_label') }} <strong class="tx-numeric tx-color-01">{{ \App\Support\StockoraFormat::number($quarantinedCount) }}</strong> SKU
            </div>
          </div>
          <div class="stk-kpi-foot d-flex justify-content-between align-items-center">
            <span>{{ $lowStockCount > 0 ? __('dashboard.kpi_reorder_attention') : __('dashboard.kpi_all_stocked') }}</span>
            @if(Route::has('products.index') && $dashPlanOn('module_products') && Auth::user()?->canAccessModule('products', 'view'))
              <a href="{{ route('products.index', ['stock_filter' => 'low']) }}" class="tx-12 tx-medium">{{ __('dashboard.kpi_review') }}</a>
            @endif
          </div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mg-b-8">
              <div class="stk-kpi-label mg-b-0">{{ __('dashboard.kpi_active_warehouses') }}</div>
              <span class="stk-kpi-icon stk-kpi-icon-amber"><i data-feather="map-pin" class="svg-18"></i></span>
            </div>
            <div class="stk-kpi-value tx-numeric">{{ __('dashboard.kpi_sites_unit', ['count' => \App\Support\StockoraFormat::number($totalWarehouses)]) }}</div>
            <div class="tx-12 tx-color-03 mg-t-4">
              {{ __('dashboard.kpi_open_po_suppliers', ['po' => \App\Support\StockoraFormat::number($pendingPurchasesCount), 'suppliers' => \App\Support\StockoraFormat::number($totalSuppliers)]) }}
            </div>
          </div>
          <div class="stk-kpi-foot d-flex justify-content-between align-items-center">
            <span>{{ __('dashboard.kpi_total_po_received', ['total' => \App\Support\StockoraFormat::number($totalPurchases), 'pct' => $receivedPct]) }}</span>
            @if(Route::has('inventory.warehouses.index') && $dashPlanOn('module_stock_monitoring') && Auth::user()?->canAccessModule('warehouse_stock', 'view'))
              <a href="{{ route('inventory.warehouses.index') }}" class="tx-12 tx-medium">{{ __('dashboard.kpi_sites_link') }}</a>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Subscription & Resource Usage Widget --}}
  @if(!empty($subscriptionUsage))
    @php
      $planMeta = $subscriptionUsage['plan'] ?? null;
      $hasLockedFeatures = collect($planEntitlements['features'] ?? [])->contains(fn ($f) => !($f['enabled'] ?? false));
    @endphp
    <div class="card card-hover mg-b-20" id="subscriptionUsageWidget">
      <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
        <div>
          <div class="d-flex align-items-center flex-wrap" style="gap: 8px;">
            <h6 class="card-title mg-b-0">{{ __('subscription.widget_title') }}</h6>
            <span class="badge badge-{{ $planMeta ? 'primary' : 'danger' }}">
              {{ __('subscription.current_plan') }}: {{ $planMeta['name'] ?? __('subscription.no_plan') }}
            </span>
          </div>
          <div class="tx-12 tx-color-03 mg-t-2">{{ __('subscription.widget_subtitle') }}</div>
        </div>
        <div class="d-flex flex-wrap" style="gap: 6px;">
          @if(Route::has('subscription.index') && isset($activeCompany) && Auth::user() && app(\App\Services\RbacService::class)->canManageCompanyBilling(Auth::user(), (int) $activeCompany->id, false))
            <a href="{{ route('subscription.index') }}" class="btn btn-xs btn-brand-01">
              <i data-feather="credit-card" class="svg-13 mg-r-4"></i> {{ __('navigation.subscription_billing') }}
            </a>
          @endif
          @if(Route::has('plans.index'))
            <a href="{{ route('plans.index') }}" class="btn btn-xs btn-white">
              <i data-feather="layers" class="svg-13 mg-r-4"></i> {{ __('subscription.compare_plans_btn') }}
            </a>
          @endif
        </div>
      </div>
      <div class="card-body">
        <div class="row row-sm">
          @foreach(($subscriptionUsage['items'] ?? []) as $qKey => $qItem)
            @php
              $qStatus = $qItem['status'] ?? 'ok';
              $badgeClass = match ($qStatus) {
                  'reached' => 'danger',
                  'warning' => 'warning',
                  'unlimited' => 'primary',
                  default => 'success',
              };
              $barClass = match ($qStatus) {
                  'reached' => 'bg-danger',
                  'warning' => 'bg-warning',
                  default => 'bg-primary',
              };
              $pctVal = $qItem['percentage'] ?? 0;
            @endphp
            <div class="col-md-6 col-xl-4 mg-b-15">
              <div class="border rounded pd-12 h-100 d-flex flex-column justify-content-between" style="background-color: #f8fafc;">
                <div>
                  <div class="d-flex justify-content-between align-items-start mg-b-6" style="gap: 8px;">
                    <div>
                      <div class="tx-13 tx-semibold tx-color-01">{{ __('subscription.limits.' . $qKey) }}</div>
                      <span class="tx-11 tx-color-03">{{ __('subscription.basis_' . ($qItem['basis'] ?? 'lifetime')) }}</span>
                    </div>
                    <span class="badge badge-{{ $badgeClass }}">{{ __('subscription.status.' . $qStatus) }}</span>
                  </div>

                  <div class="d-flex justify-content-between align-items-baseline mg-b-6">
                    @if(!empty($qItem['is_unlimited']))
                      <span class="tx-14 tx-bold tx-color-01 tx-numeric">
                        {{ \App\Support\StockoraFormat::number($qItem['usage']) }} / <span class="tx-primary">{{ __('subscription.unlimited') }}</span>
                      </span>
                    @else
                      <span class="tx-14 tx-bold tx-color-01 tx-numeric">
                        {{ \App\Support\StockoraFormat::number($qItem['usage']) }} / {{ \App\Support\StockoraFormat::number($qItem['limit']) }}
                      </span>
                      <span class="tx-12 tx-semibold tx-numeric {{ $qStatus === 'reached' ? 'tx-danger' : ($qStatus === 'warning' ? 'tx-warning' : 'tx-color-03') }}">
                        {{ $pctVal }}%
                      </span>
                    @endif
                  </div>
                </div>

                <div>
                  @if(empty($qItem['is_unlimited']))
                    <div class="stk-progress-track mg-b-4">
                      <div class="stk-progress-fill {{ $barClass }}" style="width: {{ max($qItem['usage'] > 0 ? 4 : 0, min(100, (int) $pctVal)) }}%;"></div>
                    </div>
                  @endif
                  @if(!empty($qItem['pending_reservations']))
                    <div class="tx-11 tx-color-03">
                      {{ __('subscription.pending_reservations_note', ['count' => \App\Support\StockoraFormat::number($qItem['pending_reservations'])]) }}
                    </div>
                  @endif
                </div>
              </div>
            </div>
          @endforeach
        </div>

        @if(!empty($subscriptionUsage['has_warnings']) || $hasLockedFeatures || !$planMeta)
          <div class="alert {{ !empty($subscriptionUsage['has_reached']) || !$planMeta ? 'alert-warning' : 'alert-light border' }} mg-t-5 mg-b-0 d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center" style="gap: 10px 16px;">
            <div class="tx-12">
              <strong class="d-block tx-color-01">{{ __('subscription.upgrade_prompt_title') }}</strong>
              <span class="tx-color-02">{{ __('subscription.upgrade_prompt_body') }}</span>
              @if(!empty($supportConfig['email']) || !empty($supportConfig['whatsapp']))
                <span class="d-block mg-t-2 tx-color-03">
                  {{ __('subscription.banner_contact', ['email' => $supportConfig['email'] ?? '', 'whatsapp' => $supportConfig['whatsapp'] ?? '']) }}
                </span>
              @endif
            </div>
            @if(Route::has('plans.index'))
              <a href="{{ route('plans.index') }}" class="btn btn-xs btn-brand-01 flex-shrink-0">{{ __('subscription.view_upgrade_options') }}</a>
            @endif
          </div>
        @endif
      </div>
    </div>
  @endif

  {{-- Analytics & Valuation Row --}}
  <div class="row row-sm mg-b-20">
    <div class="col-lg-8 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px 12px;">
          <div>
            <h6 class="card-title mg-b-2">{{ __('dashboard.chart_title') }}</h6>
            <div class="tx-12 tx-color-03 d-flex flex-wrap align-items-center" style="gap: 14px;">
              <span>
                <span class="stk-legend-dot" style="background-color: #059669;"></span>
                {{ __('dashboard.chart_stock_in') }} <strong class="tx-numeric tx-color-01">{{ \App\Support\StockoraFormat::quantity($movedIn, 2) }}</strong>
              </span>
              <span>
                <span class="stk-legend-dot" style="background-color: #dc2626;"></span>
                {{ __('dashboard.chart_stock_out') }} <strong class="tx-numeric tx-color-01">{{ \App\Support\StockoraFormat::quantity($movedOut, 2) }}</strong>
              </span>
            </div>
          </div>
          @if(Route::has('stock-movements.index') && $dashPlanOn('module_stock_movements') && Auth::user()?->canAccessModule('stock_movements', 'view'))
            <a href="{{ route('stock-movements.index') }}" class="btn btn-xs btn-white">{{ __('dashboard.full_ledger') }}</a>
          @endif
        </div>
        <div class="card-body">
          <div id="flotMovement" style="height: 270px; width: 100%;"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-4 d-flex flex-column" style="gap: 15px;">
      {{-- Inventory Valuation Overview --}}
      <div class="card card-hover">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('dashboard.valuation_breakdown') }}</h6>
          @if(Route::has('reports.index') && $dashPlanOn('module_inventory_reports') && Auth::user()?->canAccessModule('inventory_reports', 'view'))
            <a href="{{ route('reports.index') }}" class="tx-12 tx-medium">{{ __('dashboard.report_link') }}</a>
          @endif
        </div>
        <div class="card-body tx-13">
          @php
            $valDenom = max((float) $stockValue, 1);
            $pctVerified = min(100, round((float) $verifiedAcquisitionValue / $valDenom * 100, 1));
            $pctLegacy = min(100, round((float) $estimatedLegacyValue / $valDenom * 100, 1));
            $pctMixed = min(100, round((float) $mixedValuationValue / $valDenom * 100, 1));
          @endphp
          <div class="mg-b-12">
            <div class="d-flex justify-content-between align-items-center mg-b-4">
              <span class="badge badge-success">VERIFIED_ACQUISITION</span>
              <span class="tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($verifiedAcquisitionValue, 2) }}</span>
            </div>
            <div class="stk-progress-track">
              <div class="stk-progress-fill" style="width: {{ (float) $verifiedAcquisitionValue > 0 ? max(4, $pctVerified) : 0 }}%; background-color: #059669;"></div>
            </div>
          </div>

          <div class="mg-b-12">
            <div class="d-flex justify-content-between align-items-center mg-b-4">
              <span class="badge badge-primary">ESTIMATED_LEGACY</span>
              <span class="tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($estimatedLegacyValue, 2) }}</span>
            </div>
            <div class="stk-progress-track">
              <div class="stk-progress-fill" style="width: {{ (float) $estimatedLegacyValue > 0 ? max(4, $pctLegacy) : 0 }}%; background-color: #2563eb;"></div>
            </div>
          </div>

          <div class="mg-b-12">
            <div class="d-flex justify-content-between align-items-center mg-b-4">
              <span class="badge badge-info">MIXED_BASELINE</span>
              <span class="tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($mixedValuationValue, 2) }}</span>
            </div>
            <div class="stk-progress-track">
              <div class="stk-progress-fill" style="width: {{ (float) $mixedValuationValue > 0 ? max(4, $pctMixed) : 0 }}%; background-color: #0284c7;"></div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center pd-t-10 border-top tx-semibold">
            <span>{{ __('dashboard.total_operational_value') }}</span>
            <span class="tx-numeric tx-brand-01">{{ \App\Support\StockoraFormat::currency($stockValue, 2) }}</span>
          </div>
        </div>
      </div>

      {{-- Stock Distribution by Warehouse --}}
      <div class="card card-hover flex-fill">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('dashboard.warehouse_distribution') }}</h6>
          @if(Route::has('inventory.warehouses.index') && $dashPlanOn('module_stock_monitoring') && Auth::user()?->canAccessModule('warehouse_stock', 'view'))
            <a href="{{ route('inventory.warehouses.index') }}" class="tx-12 tx-medium">{{ __('dashboard.view_all') }}</a>
          @endif
        </div>
        <div class="card-body pd-y-12">
          @forelse($warehouseDistribution->take(4) as $whRow)
            <div class="stk-list-row">
              <div class="d-flex justify-content-between align-items-center mg-b-4">
                <div class="tx-13">
                  <span class="badge badge-light mg-r-4">{{ $whRow['code'] }}</span>
                  <span class="tx-medium tx-color-01">{{ \Illuminate\Support\Str::limit($whRow['name'], 20) }}</span>
                </div>
                <span class="tx-12 tx-numeric tx-semibold">{{ \App\Support\StockoraFormat::number((float) $whRow['share_pct'], 1) }}%</span>
              </div>
              <div class="stk-progress-track mg-b-4">
                <div class="stk-progress-fill bg-primary" style="width: {{ (float) $whRow['share_pct'] > 0 ? max(4, min(100, (float) $whRow['share_pct'])) : 0 }}%;"></div>
              </div>
              <div class="d-flex justify-content-between tx-11 tx-color-03">
                <span class="tx-numeric">{{ __('dashboard.units_sku_summary', ['units' => \App\Support\StockoraFormat::quantity($whRow['trusted_units'], 2), 'skus' => \App\Support\StockoraFormat::number($whRow['sku_count'])]) }}</span>
                <span class="tx-numeric">{{ \App\Support\StockoraFormat::currency($whRow['trusted_value'], 0) }}</span>
              </div>
            </div>
          @empty
            @include('partials.empty-state', [
              'title' => __('dashboard.no_warehouses_title'),
              'description' => __('dashboard.no_warehouses_desc'),
              'icon' => 'map-pin',
              'actionUrl' => Route::has('warehouses.create') && $dashPlanOn('module_warehouses') && Auth::user()?->canAccessModule('warehouses', 'create') ? route('warehouses.create') : null,
              'actionLabel' => __('dashboard.add_warehouse_btn'),
            ])
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- Operational Overview: Row 1 --}}
  <div class="row row-sm mg-b-20">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('dashboard.low_stock_title') }}</h6>
          @if(Route::has('products.index') && $dashPlanOn('module_products') && Auth::user()?->canAccessModule('products', 'view'))
            <a href="{{ route('products.index', ['stock_filter' => 'low']) }}" class="tx-12 tx-medium">{{ __('dashboard.view_all_low_stock') }}</a>
          @endif
        </div>
        @if($lowStockProducts->isEmpty())
          <div class="card-body d-flex align-items-center justify-content-center">
            @include('partials.empty-state', [
              'title' => __('dashboard.healthy_coverage_title'),
              'description' => __('dashboard.all_products_safe'),
              'icon' => 'check-circle',
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('dashboard.cols.product') }}</th>
                  <th class="text-right">{{ __('dashboard.cols.stock') }}</th>
                  <th class="text-right">{{ __('dashboard.cols.min') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($lowStockProducts as $lp)
                  <tr>
                    <td>
                      @if(Route::has('products.show') && $dashPlanOn('module_products') && Auth::user()?->canAccessModule('products', 'view'))
                        <a href="{{ route('products.show', $lp) }}" class="tx-medium tx-color-01">{{ $lp->name }}</a>
                      @else
                        <span class="tx-medium">{{ $lp->name }}</span>
                      @endif
                      <div class="tx-11 tx-color-03">{{ $lp->code }} &bull; {{ $lp->category->name ?? '-' }}</div>
                    </td>
                    <td class="text-right tx-numeric tx-danger tx-semibold">{{ \App\Support\StockoraFormat::quantity($lp->stock, 2) }}</td>
                    <td class="text-right tx-numeric tx-color-03">{{ \App\Support\StockoraFormat::quantity($lp->min_stock, 2) }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('dashboard.recent_pos_title') }}</h6>
          @if(Route::has('purchases.index') && $dashPlanOn('module_purchase_orders') && Auth::user()?->canAccessModule('purchase_orders', 'view'))
            <a href="{{ route('purchases.index') }}" class="tx-12 tx-medium">{{ __('dashboard.all_pos') }}</a>
          @endif
        </div>
        @if($recentPurchases->isEmpty())
          <div class="card-body d-flex align-items-center justify-content-center">
            @include('partials.empty-state', [
              'title' => __('dashboard.no_pos_title'),
              'description' => __('dashboard.no_pos_recorded'),
              'icon' => 'shopping-cart',
              'actionUrl' => Route::has('purchases.create') && $dashPlanOn('module_purchase_orders') && Auth::user()?->canAccessModule('purchase_orders', 'create') ? route('purchases.create') : null,
              'actionLabel' => __('dashboard.create_po_btn'),
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('dashboard.cols.po_number') }}</th>
                  <th>{{ __('dashboard.cols.supplier') }}</th>
                  <th class="text-right">{{ __('dashboard.cols.total_rp') }}</th>
                  <th class="text-center">{{ __('dashboard.cols.status') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentPurchases as $po)
                  <tr>
                    <td class="tx-medium">
                      @if(Route::has('purchases.show') && $dashPlanOn('module_purchase_orders') && Auth::user()?->canAccessModule('purchase_orders', 'view'))
                        <a href="{{ route('purchases.show', $po) }}">{{ $po->purchase_number }}</a>
                      @else
                        {{ $po->purchase_number }}
                      @endif
                    </td>
                    <td>{{ $po->supplier->name ?? '-' }}</td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($po->total_amount, 2) }}</td>
                    <td class="text-center">
                      @include('partials.status-badge', ['status' => $po->status])
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>

  {{-- Operational Overview: Row 2 --}}
  <div class="row row-sm">
    <div class="col-lg-5 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('dashboard.recent_grns_title') }}</h6>
          @if(Route::has('grns.index') && $dashPlanOn('module_grn') && Auth::user()?->canAccessModule('grn', 'view'))
            <a href="{{ route('grns.index') }}" class="tx-12 tx-medium">{{ __('dashboard.all_grns') }}</a>
          @endif
        </div>
        @if($recentReceipts->isEmpty())
          <div class="card-body d-flex align-items-center justify-content-center">
            @include('partials.empty-state', [
              'title' => __('dashboard.no_grns_title'),
              'description' => __('dashboard.no_grns_recorded'),
              'icon' => 'check-square',
              'actionUrl' => Route::has('grns.create') && $dashPlanOn('module_grn') && Auth::user()?->canAccessModule('grn', 'create') ? route('grns.create') : null,
              'actionLabel' => __('dashboard.quick_receive_grn'),
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('dashboard.cols.grn') }}</th>
                  <th>{{ __('dashboard.cols.warehouse') }}</th>
                  <th class="text-right">{{ __('dashboard.cols.capitalized') }}</th>
                  <th class="text-center">{{ __('dashboard.cols.status') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentReceipts as $grn)
                  <tr>
                    <td>
                      @if(Route::has('grns.show') && $dashPlanOn('module_grn') && Auth::user()?->canAccessModule('grn', 'view'))
                        <a href="{{ route('grns.show', $grn) }}" class="tx-medium">{{ $grn->receipt_number }}</a>
                      @else
                        <span class="tx-medium">{{ $grn->receipt_number }}</span>
                      @endif
                      <div class="tx-11 tx-color-03">{{ $grn->purchase->purchase_number ?? '-' }} &bull; {{ \Illuminate\Support\Str::limit($grn->purchase->supplier->name ?? '-', 18) }}</div>
                    </td>
                    <td class="tx-12">{{ $grn->warehouse->code ?? '-' }}</td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($grn->total_capitalized_cost, 0) }}</td>
                    <td class="text-center">
                      @include('partials.status-badge', ['status' => $grn->status])
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('dashboard.recent_movements_title') }}</h6>
          @if(Route::has('stock-movements.index') && $dashPlanOn('module_stock_movements') && Auth::user()?->canAccessModule('stock_movements', 'view'))
            <a href="{{ route('stock-movements.index') }}" class="tx-12 tx-medium">{{ __('dashboard.all_movements') }}</a>
          @endif
        </div>
        @if($recentMovements->isEmpty())
          <div class="card-body d-flex align-items-center justify-content-center">
            @include('partials.empty-state', [
              'title' => __('dashboard.no_movements_title'),
              'description' => __('dashboard.no_movements_recorded'),
              'icon' => 'activity',
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-sm table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('dashboard.cols.product') }}</th>
                  <th>{{ __('dashboard.cols.warehouse') }}</th>
                  <th>{{ __('dashboard.cols.type') }}</th>
                  <th class="text-right">{{ __('dashboard.cols.qty') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($recentMovements as $mv)
                  <tr>
                    <td>
                      <span class="tx-medium">{{ $mv->product->code ?? '-' }}</span>
                      <span class="tx-11 tx-color-03 d-block">{{ \Illuminate\Support\Str::limit($mv->product->name ?? '', 28) }}</span>
                    </td>
                    <td class="tx-12">{{ $mv->warehouse->code ?? '-' }}</td>
                    <td>@include('partials.status-badge', ['status' => $mv->type])</td>
                    <td class="text-right tx-numeric tx-semibold {{ $mv->direction === 'in' ? 'tx-success' : 'tx-danger' }}">
                      {{ $mv->direction === 'in' ? '+' : '-' }}{{ \App\Support\StockoraFormat::quantity($mv->quantity, 2) }}
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>
@endsection

@push('vendor-scripts')
  <script src="{{ asset('lib/jquery.flot/jquery.flot.js') }}"></script>
  <script src="{{ asset('lib/jquery.flot/jquery.flot.resize.js') }}"></script>
@endpush

@push('scripts')
<script>
$(function () {
  var chartIn = @json($chartIn);
  var chartOut = @json($chartOut);
  var chartTicks = @json($chartTicks);

  if ($('#flotMovement').length && $.plot) {
    $.plot('#flotMovement', [
      {
        data: chartIn,
        label: @json(rtrim(__('dashboard.chart_stock_in'), ':')),
        color: '#059669',
        lines: { show: true, fill: 0.14, lineWidth: 2.2 },
        points: { show: true, radius: 3, fillColor: '#ffffff' }
      },
      {
        data: chartOut,
        label: @json(rtrim(__('dashboard.chart_stock_out'), ':')),
        color: '#dc2626',
        lines: { show: true, fill: 0.08, lineWidth: 2.2 },
        points: { show: true, radius: 3, fillColor: '#ffffff' }
      }
    ], {
      series: { shadowSize: 0 },
      grid: { borderWidth: 0, hoverable: true, labelMargin: 10 },
      yaxis: { min: 0, tickColor: 'rgba(148,163,184,0.22)', font: { size: 11, color: '#64748b' } },
      xaxis: { ticks: chartTicks, tickColor: 'transparent', font: { size: 11, color: '#64748b' } },
      legend: { show: false }
    });
  }
});
</script>
@endpush
