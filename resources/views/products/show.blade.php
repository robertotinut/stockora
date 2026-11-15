@extends('layouts.admin')

@section('title', __('products.detail_page_title', ['code' => $product->code]))
@section('page_title', $product->name)
@section('page_subtitle', __('products.col_sku') . ' ' . $product->code . ' • ' . ($product->category->name ?? __('app.uncategorized')) . ' • ' . __('products.base_unit') . ': ' . ($product->unit->name ?? '-'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('products.index') }}">{{ __('products.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $product->code }}</li>
@endsection

@section('actions')
  @if(Route::has('purchases.create') && Auth::user()?->canAccessModule('purchase_orders', 'create') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
    <a href="{{ route('purchases.create', ['product_id' => $product->id]) }}" class="btn btn-white">
      <i data-feather="shopping-cart" class="svg-14"></i> {{ __('products.create_po_btn') }}
    </a>
  @endif
  @if(Auth::user()?->canAccessModule('products', 'edit'))
    <a href="{{ route('products.edit', $product) }}" class="btn btn-brand-01">
      <i data-feather="edit-2" class="svg-14"></i> {{ __('products.edit_title') }}
    </a>
  @endif
@endsection

@section('content')
  @if($product->legacy_reconciliation_status === 'CHAIN_BREAK')
    <div class="alert alert-danger mg-b-20" role="alert">
      <div class="d-flex align-items-start" style="gap: 10px;">
        <i data-feather="shield-off" class="svg-20 flex-shrink-0 mg-t-2"></i>
        <div>
          <h6 class="alert-heading mg-b-4">{{ __('products.quarantined_alert_title') }}</h6>
          <p class="mg-b-4 tx-13">{{ __('products.quarantined_alert_desc') }}</p>
          @if($product->reconciliationAudit)
            <div class="tx-12">
              <strong>{{ __('app.reason') }}:</strong> {{ $product->reconciliationAudit->blocking_reason ?? '-' }}
              | <strong>{{ __('products.recorded_legacy_stock') }}</strong> {{ \App\Support\StockoraFormat::quantity($product->reconciliationAudit->legacy_product_stock, 4) }}
            </div>
          @endif
        </div>
      </div>
    </div>
  @endif

  {{-- SKU Quick KPI Row --}}
  @php
    $isQuarantined = $product->legacy_reconciliation_status === 'CHAIN_BREAK';
    $isLow = !$isQuarantined && bccomp((string) $product->stock, (string) $product->min_stock, 4) <= 0;
  @endphp
  <div class="row row-sm mg-b-20">
    <div class="col-sm-6 col-xl-3 mg-b-15 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('products.total_stock') }}</div>
            <span class="stk-kpi-icon {{ $isLow ? 'stk-kpi-icon-red' : 'stk-kpi-icon-blue' }}"><i data-feather="box" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric {{ $isLow ? 'tx-danger' : '' }}">{{ \App\Support\StockoraFormat::quantity($product->stock, 2) }} <span class="tx-14 tx-normal tx-color-03">{{ $product->unit->symbol ?? '' }}</span></div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('products.min_threshold_inline') }} <span class="tx-numeric">{{ \App\Support\StockoraFormat::quantity($product->min_stock, 2) }} {{ $product->unit->symbol ?? '' }}</span></div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mg-b-15 mg-xl-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('products.mac') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-navy"><i data-feather="trending-up" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric">{{ \App\Support\StockoraFormat::currency($product->moving_average_cost, 2) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('products.ref_buy_inline') }} {{ \App\Support\StockoraFormat::currency($product->purchase_price, 2) }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3 mg-b-15 mg-sm-b-0">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('products.inventory_value') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-emerald"><i data-feather="layers" class="svg-16"></i></span>
          </div>
          <div class="stk-kpi-value tx-numeric tx-brand-01">{{ \App\Support\StockoraFormat::currency($product->inventory_value, 2) }}</div>
          <div class="tx-12 tx-color-03 mg-t-4">{{ __('products.warehouse_records_count', ['count' => \App\Support\StockoraFormat::number($product->productWarehouses->count())]) }}</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-3">
      <div class="card card-hover stk-kpi-card h-100">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mg-b-6">
            <div class="stk-kpi-label mg-b-0">{{ __('products.col_reconciliation') }}</div>
            <span class="stk-kpi-icon stk-kpi-icon-amber"><i data-feather="shield" class="svg-16"></i></span>
          </div>
          <div class="mg-t-4 mg-b-6">
            @include('partials.status-badge', ['status' => $product->legacy_reconciliation_status])
          </div>
          <div class="tx-12 tx-color-03">{{ __('products.ref_sell_inline') }} {{ \App\Support\StockoraFormat::currency($product->selling_price, 2) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="row row-sm mg-b-20">
    <div class="col-lg-4 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('products.sku_profile_title') }}</h6>
          <span class="badge badge-light">{{ $product->code }}</span>
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-0">
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.category') }}</td><td class="text-right tx-medium">{{ $product->category->name ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.base_unit') }}</td><td class="text-right tx-medium">{{ $product->unit->name ?? '-' }} ({{ $product->unit->symbol ?? '-' }})</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.operational_stock') }}</td><td class="text-right tx-numeric tx-semibold">{{ \App\Support\StockoraFormat::quantity($product->stock, 4) }} {{ $product->unit->symbol ?? '' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.min_stock') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::quantity($product->min_stock, 4) }} {{ $product->unit->symbol ?? '' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.mac') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($product->moving_average_cost, 2) }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.inventory_value') }}</td><td class="text-right tx-numeric tx-semibold tx-brand-01">{{ \App\Support\StockoraFormat::currency($product->inventory_value, 2) }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.purchase_price') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($product->purchase_price, 2) }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('products.selling_price') }}</td><td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::currency($product->selling_price, 2) }}</td></tr>
          </table>
          @if($product->description)
            <div class="mg-t-12 pt-3 border-top tx-12 tx-color-03">{{ $product->description }}</div>
          @endif
        </div>
      </div>
    </div>

    <div class="col-lg-8 d-flex flex-column" style="gap: 15px;">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('products.stock_by_warehouse_title') }}</h6>
        </div>
        @if($product->productWarehouses->isEmpty())
          <div class="card-body">
            @include('partials.empty-state', [
              'title' => __('products.no_wh_stock_title'),
              'description' => __('products.no_wh_stock_desc'),
              'icon' => 'layers',
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('products.col_warehouse') }}</th>
                  <th class="text-right">{{ __('products.col_stock') }}</th>
                  <th class="text-right">{{ __('products.col_mac_rp') }}</th>
                  <th class="text-right">{{ __('products.col_inventory_value_rp') }}</th>
                  <th class="text-center">{{ __('products.col_valuation_basis') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($product->productWarehouses as $pw)
                  <tr>
                    <td>
                      <div class="tx-medium">{{ $pw->warehouse->name ?? __('app.warehouse_fallback', ['id' => $pw->warehouse_id]) }}</div>
                      <div class="tx-11 tx-color-03">{{ $pw->warehouse->code ?? '' }}</div>
                    </td>
                    <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::quantity($pw->stock, 4) }} {{ $product->unit->symbol ?? '' }}</td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($pw->moving_average_cost, 2) }}</td>
                    <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::number($pw->inventory_value, 2) }}</td>
                    <td class="text-center">@include('partials.status-badge', ['status' => $pw->valuation_basis])</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      @php
        $canManageConv = Auth::user()?->canAccessModule('units', 'manage');
        $planAllowsConv = $planFeatures['module_unit_conversions']['enabled'] ?? false;
      @endphp
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('products.product_conversions_title', ['symbol' => $product->unit->symbol ?? '']) }}</h6>
        </div>
        <div class="card-body">
          @if($canManageConv && $planAllowsConv)
            <form action="{{ route('unit-conversions.store') }}" method="POST" class="form-row align-items-end mg-b-15">
              @csrf
              <input type="hidden" name="product_id" value="{{ $product->id }}">
              <input type="hidden" name="to_unit_id" value="{{ $product->unit_id }}">
              <div class="col-md-5 mg-b-10 mg-md-b-0">
                <label class="tx-11 tx-uppercase tx-medium tx-color-03">{{ __('products.from_uom_label') }}</label>
                <select name="from_unit_id" class="form-control form-control-sm" required>
                  <option value="">{{ __('products.select_unit') }}</option>
                  @foreach($units->where('id', '!=', $product->unit_id) as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->symbol }})</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-4 mg-b-10 mg-md-b-0">
                <label class="tx-11 tx-uppercase tx-medium tx-color-03">{{ __('products.factor_to_label', ['symbol' => $product->unit->symbol ?? __('app.unit')]) }}</label>
                <input type="number" step="0.00000001" min="0.00000001" name="factor" class="form-control form-control-sm tx-numeric" placeholder="12" required>
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-brand-01 btn-block">{{ __('products.add_rule_btn') }}</button>
              </div>
            </form>
          @elseif($canManageConv && !$planAllowsConv)
            <div class="alert alert-light border tx-12 d-flex flex-wrap justify-content-between align-items-center mg-b-15" style="gap: 8px;">
              <span><i data-feather="lock" class="svg-13 text-warning mg-r-4"></i> {{ __('subscription.feature_locked_inline', ['feature' => __('subscription.features.module_unit_conversions')]) }}</span>
              <a href="{{ route('plans.index') }}" class="btn btn-xs btn-white">{{ __('subscription.compare_plans_btn') }}</a>
            </div>
          @endif

          @if($product->unitConversions->isNotEmpty())
            <div class="table-responsive">
              <table class="table table-sm mg-b-0">
                <thead>
                  <tr>
                    <th>{{ __('products.col_conversion_rule') }}</th>
                    <th class="text-right">{{ __('app.action') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($product->unitConversions as $conv)
                    <tr>
                      <td class="tx-numeric">
                        1 {{ $conv->fromUnit->symbol ?? $conv->fromUnit->name }} =
                        <strong>{{ rtrim(rtrim((string) $conv->factor, '0'), '.') }}</strong>
                        {{ $conv->toUnit->symbol ?? $conv->toUnit->name }}
                      </td>
                      <td class="text-right">
                        @if($canManageConv && $planAllowsConv)
                          <form action="{{ route('unit-conversions.destroy', $conv) }}" method="POST"
                                data-confirm-title="{{ __('products.delete_conv_title') }}"
                                data-confirm="{{ __('products.delete_conv_product_confirm') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-outline-danger">{{ __('app.remove') }}</button>
                          </form>
                        @else
                          <span class="tx-12 tx-color-04">{{ __('app.read_only') }}</span>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @else
            <p class="tx-12 tx-color-03 mg-b-0">{{ __('products.no_product_conversions') }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="card-title mg-b-0">{{ __('products.movement_history_title') }}</h6>
      <span class="badge badge-light tx-numeric">{{ __('products.entries_badge', ['count' => \App\Support\StockoraFormat::number($movements->total())]) }}</span>
    </div>
    @if($movements->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('products.no_movements_title'),
          'description' => __('products.no_movements_desc'),
          'icon' => 'activity',
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('products.col_timestamp') }}</th>
              <th>{{ __('products.col_warehouse') }}</th>
              <th>{{ __('products.col_type_direction') }}</th>
              <th class="text-right">{{ __('products.col_qty') }}</th>
              <th class="text-right">{{ __('products.col_unit_cost') }}</th>
              <th class="text-right">{{ __('products.col_total_cost') }}</th>
              <th class="text-right">{{ __('products.col_running_balance') }}</th>
              <th>{{ __('products.col_user_notes') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($movements as $mov)
              <tr>
                <td class="tx-12 tx-color-03 text-nowrap">{{ \App\Support\StockoraFormat::dateTime($mov->created_at) }}</td>
                <td><span class="badge badge-light">{{ $mov->warehouse->code ?? '-' }}</span></td>
                <td>
                  @include('partials.status-badge', ['status' => $mov->type])
                  <span class="badge {{ $mov->direction === 'in' ? 'badge-success' : 'badge-warning' }}">{{ strtoupper($mov->direction) }}</span>
                </td>
                <td class="text-right tx-numeric tx-semibold {{ $mov->direction === 'in' ? 'tx-success' : 'tx-danger' }}">
                  {{ $mov->direction === 'in' ? '+' : '-' }}{{ \App\Support\StockoraFormat::quantity($mov->quantity, 4) }}
                </td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mov->unit_cost, 2) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($mov->total_cost, 2) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::quantity($mov->running_stock_after, 4) }}</td>
                <td class="tx-12">
                  <div>{{ $mov->user->name ?? __('app.system') }}</div>
                  @if($mov->notes)
                    <div class="tx-color-03">{{ \Illuminate\Support\Str::limit($mov->notes, 60) }}</div>
                  @endif
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
