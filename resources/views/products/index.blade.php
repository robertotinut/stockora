@extends('layouts.admin')

@section('title', __('products.page_title'))
@section('page_title', __('products.catalog_page_title'))
@section('page_subtitle', __('products.catalog_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('products.page_title') }}</li>
@endsection

@section('actions')
  @if(Route::has('inventory.warehouses.index') && Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_stock_monitoring']['enabled'] ?? false))
    <a href="{{ route('inventory.warehouses.index') }}" class="btn btn-white">
      <i data-feather="layers" class="svg-14"></i> {{ __('products.stock_overview_btn') }}
    </a>
  @endif
  @if(Auth::user()?->canAccessModule('products', 'create'))
    <a href="{{ route('products.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('products.create_title') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('products.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('products.search_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('products.search_code_name_placeholder') }}">
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('app.category') }}</label>
          <select name="category_id" class="form-control form-control-sm">
            <option value="">{{ __('products.all_categories') }}</option>
            @foreach($categories as $cat)
              <option value="{{ $cat->id }}" {{ (string) $categoryId === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('products.reconciliation_status') }}</label>
          <select name="recon_status" class="form-control form-control-sm">
            <option value="">{{ __('products.all_statuses') }}</option>
            @foreach(['HISTORICALLY_VERIFIED', 'CHAIN_CONSISTENT', 'BASELINE_INITIALIZATION', 'CHAIN_BREAK', 'PENDING'] as $status)
              <option value="{{ $status }}" {{ $reconStatus === $status ? 'selected' : '' }}>{{ $status }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-8 col-xl-2 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('products.stock_level_label') }}</label>
          <select name="stock_filter" class="form-control form-control-sm">
            <option value="">{{ __('products.all_levels') }}</option>
            <option value="low" {{ $stockFilter === 'low' ? 'selected' : '' }}>{{ __('products.low_stock_short') }}</option>
            <option value="zero" {{ $stockFilter === 'zero' ? 'selected' : '' }}>{{ __('products.zero_stock_short') }}</option>
            <option value="quarantined" {{ $stockFilter === 'quarantined' ? 'selected' : '' }}>{{ __('products.quarantined_short') }}</option>
          </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $categoryId !== '' || $reconStatus !== '' || $stockFilter !== '')
            <a href="{{ route('products.index') }}" class="btn btn-sm btn-white" title="{{ __('app.reset_filters') }}">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('products.directory_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ __('products.sku_count_badge', ['count' => \App\Support\StockoraFormat::number($products->total())]) }}</span>
      </div>
      @if($search !== '' || $categoryId !== '' || $reconStatus !== '' || $stockFilter !== '')
        <span class="tx-12 tx-color-03">{{ __('app.filtered_active') }}</span>
      @endif
    </div>

    @if($products->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('products.no_products'),
          'description' => __('products.no_products_desc'),
          'icon' => 'package',
          'actionUrl' => ($search !== '' || $categoryId !== '' || $reconStatus !== '' || $stockFilter !== '') ? route('products.index') : (Auth::user()?->canAccessModule('products', 'create') ? route('products.create') : null),
          'actionLabel' => ($search !== '' || $categoryId !== '' || $reconStatus !== '' || $stockFilter !== '') ? __('app.reset_filters') : __('products.create_title'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('products.col_sku') }}</th>
              <th>{{ __('products.col_product') }}</th>
              <th>{{ __('app.category') }}</th>
              <th class="text-right">{{ __('products.col_mac_rp') }}</th>
              <th class="text-right">{{ __('products.col_operational_stock') }}</th>
              <th class="text-right">{{ __('products.col_inventory_value_rp') }}</th>
              <th class="text-center">{{ __('products.col_reconciliation') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($products as $product)
              @php
                $isQuarantined = $product->legacy_reconciliation_status === 'CHAIN_BREAK';
                $isLow = !$isQuarantined && bccomp((string) $product->stock, (string) $product->min_stock, 4) <= 0;
              @endphp
              <tr class="{{ $isQuarantined ? 'bg-warning-light' : '' }}">
                <td class="tx-medium">
                  <a href="{{ route('products.show', $product) }}" class="badge badge-light tx-12">{{ $product->code }}</a>
                </td>
                <td>
                  <a href="{{ route('products.show', $product) }}" class="tx-medium tx-color-01 d-block">{{ $product->name }}</a>
                  <span class="tx-11 tx-color-03">{{ __('products.base_unit_inline', ['name' => $product->unit->name ?? '-', 'symbol' => $product->unit->symbol ?? '-']) }}</span>
                </td>
                <td>{{ $product->category->name ?? '-' }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($product->moving_average_cost, 2) }}</td>
                <td class="text-right tx-numeric">
                  <span class="{{ $isLow ? 'tx-danger tx-semibold' : 'tx-medium' }}">{{ \App\Support\StockoraFormat::quantity($product->stock, 2) }}</span>
                  <span class="tx-11 tx-color-03">{{ $product->unit->symbol ?? '' }}</span>
                  @if($isQuarantined)
                    <div class="mg-t-2"><span class="badge badge-danger">{{ __('products.quarantined_short') }}</span></div>
                  @elseif($isLow)
                    <div class="mg-t-2"><span class="badge badge-warning">{{ __('products.low_min_badge', ['min' => \App\Support\StockoraFormat::number($product->min_stock, 0)]) }}</span></div>
                  @endif
                </td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::number($product->inventory_value, 2) }}</td>
                <td class="text-center">
                  @include('partials.status-badge', ['status' => $product->legacy_reconciliation_status])
                </td>
                <td class="text-right text-nowrap">
                  <a href="{{ route('products.show', $product) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                  @if(Auth::user()?->canAccessModule('products', 'edit'))
                    <a href="{{ route('products.edit', $product) }}" class="btn btn-xs btn-white mg-l-4">{{ __('app.edit') }}</a>
                  @endif
                  @if(Auth::user()?->canAccessModule('products', 'delete'))
                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline"
                          data-confirm-title="{{ __('products.delete_product_title') }}"
                          data-confirm="{{ __('products.delete_product_confirm', ['code' => $product->code, 'name' => $product->name]) }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-xs btn-outline-danger mg-l-4">{{ __('app.delete') }}</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($products->hasPages())
      <div class="card-footer">
        {{ $products->links() }}
      </div>
    @endif
  </div>
@endsection
