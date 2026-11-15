@extends('layouts.admin')

@section('title', __('warehouses.page_title'))
@section('page_title', __('warehouses.sites_title'))
@section('page_subtitle', __('warehouses.sites_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('warehouses.page_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_stock_monitoring']['enabled'] ?? false))
    <a href="{{ route('inventory.warehouses.index') }}" class="btn btn-white">
      <i data-feather="layers" class="svg-14"></i> {{ __('warehouses.stock_overview_btn') }}
    </a>
  @endif
  @if(Auth::user()?->canAccessModule('warehouses', 'create'))
    <a href="{{ route('warehouses.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('warehouses.add_warehouse_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('warehouses.registered_warehouses') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($warehouses->total()) }}</span>
      </div>
      <form action="{{ route('warehouses.index') }}" method="GET" class="form-inline" style="gap: 6px;">
        <div class="input-group input-group-sm">
          <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('warehouses.search_placeholder') }}">
          <div class="input-group-append">
            <button type="submit" class="btn btn-white"><i data-feather="search" class="svg-14"></i></button>
          </div>
        </div>
        @if($search !== '')
          <a href="{{ route('warehouses.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
        @endif
      </form>
    </div>

    @if($warehouses->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('warehouses.no_warehouses'),
          'description' => __('warehouses.no_warehouses_desc'),
          'icon' => 'home',
          'actionUrl' => $search !== '' ? route('warehouses.index') : route('warehouses.create'),
          'actionLabel' => $search !== '' ? __('app.reset_search') : __('warehouses.add_warehouse_btn'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('app.code') }}</th>
              <th>{{ __('warehouses.name') }}</th>
              <th class="text-center">{{ __('app.status') }}</th>
              <th class="text-right">{{ __('warehouses.active_skus') }}</th>
              <th class="text-right">{{ __('warehouses.trusted_units') }}</th>
              <th class="text-right">{{ __('warehouses.col_valuation_rp') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($warehouses as $warehouse)
              <tr>
                <td><span class="badge badge-light tx-12">{{ $warehouse->code }}</span></td>
                <td>
                  <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                    <span class="tx-medium tx-color-01">{{ $warehouse->name }}</span>
                    @if($warehouse->is_default)
                      <span class="badge badge-primary">{{ __('warehouses.default_badge') }}</span>
                    @endif
                  </div>
                </td>
                <td class="text-center">
                  @include('partials.status-badge', ['status' => $warehouse->is_active ? 'ACTIVE' : 'INACTIVE'])
                </td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($warehouse->active_sku_count) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::quantity($warehouse->trusted_stock_sum ?? 0, 2) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($warehouse->trusted_value_sum ?? 0, 2) }}</td>
                <td class="text-right text-nowrap">
                  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_stock_monitoring']['enabled'] ?? false))
                    <a href="{{ route('inventory.warehouses.index', ['warehouse_id' => $warehouse->id]) }}" class="btn btn-xs btn-white">{{ __('warehouses.view_stock_btn') }}</a>
                  @endif
                  @if(Auth::user()?->canAccessModule('warehouses', 'edit'))
                    <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-xs btn-white mg-l-4">{{ __('app.edit') }}</a>
                  @endif
                  @if(!$warehouse->is_default && Auth::user()?->canAccessModule('warehouses', 'delete'))
                    <form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST" class="d-inline"
                          data-confirm-title="{{ __('warehouses.delete_wh_title') }}"
                          data-confirm="{{ __('warehouses.delete_wh_confirm', ['code' => $warehouse->code, 'name' => $warehouse->name]) }}">
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

    @if($warehouses->hasPages())
      <div class="card-footer">{{ $warehouses->links() }}</div>
    @endif
  </div>
@endsection
