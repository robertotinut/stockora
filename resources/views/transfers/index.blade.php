@extends('layouts.admin')

@section('title', __('operations.transfers_title'))
@section('page_title', __('operations.transfers_heading'))
@section('page_subtitle', __('operations.transfers_index_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_warehouse_stock']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('inventory.warehouses.index') }}">{{ __('operations.inventory_breadcrumb') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.transfers_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('stock_transfers', 'create'))
    <a href="{{ route('transfers.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('operations.new_transfer_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('transfers.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-4 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.search_doc_notes_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="TRF-...">
        </div>
        <div class="col-sm-6 col-md-3 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.from_warehouse') }}</label>
          <select name="from_warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_sites') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $fromWarehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-8 col-md-3 mg-b-10 mg-sm-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.to_warehouse') }}</label>
          <select name="to_warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_sites') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $toWarehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-4 col-md-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $fromWarehouseId !== '' || $toWarehouseId !== '')
            <a href="{{ route('transfers.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.transfers_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($transfers->total()) }}</span>
      </div>
    </div>

    @if($transfers->isEmpty())
      <div class="card-body">
        @php
          $isFiltered = $search !== '' || $fromWarehouseId !== '' || $toWarehouseId !== '';
          $emptyActionUrl = $isFiltered
              ? route('transfers.index')
              : (Auth::user()?->canAccessModule('stock_transfers', 'create') ? route('transfers.create') : null);
        @endphp
        @include('partials.empty-state', [
          'title' => __('operations.no_transfers_title'),
          'description' => __('operations.no_transfers_desc'),
          'icon' => 'repeat',
          'actionUrl' => $emptyActionUrl,
          'actionLabel' => $isFiltered ? __('app.reset_filters') : __('operations.new_transfer_btn'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('operations.transfer_number') }}</th>
              <th>{{ __('operations.col_route') }}</th>
              <th class="text-right">{{ __('operations.col_lines') }}</th>
              <th class="text-center">{{ __('app.status') }}</th>
              <th>{{ __('operations.col_posted_user') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($transfers as $trf)
              <tr>
                <td>
                  <a href="{{ route('transfers.show', $trf) }}" class="tx-medium tx-color-01">{{ $trf->transfer_number }}</a>
                  @if($trf->notes)
                    <div class="tx-11 tx-color-03">{{ \Illuminate\Support\Str::limit($trf->notes, 50) }}</div>
                  @endif
                </td>
                <td>
                  <span class="badge badge-light">{{ $trf->fromWarehouse->code ?? '-' }}</span>
                  <span class="mx-1 tx-color-03">&rarr;</span>
                  <span class="badge badge-light">{{ $trf->toWarehouse->code ?? '-' }}</span>
                </td>
                <td class="text-right tx-numeric">{{ __('operations.items_count_inline', ['count' => \App\Support\StockoraFormat::number($trf->items_count)]) }}</td>
                <td class="text-center">
                  @if($trf->reversalAdjustment)
                    @include('partials.status-badge', ['status' => 'REVERSED'])
                  @else
                    @include('partials.status-badge', ['status' => $trf->status])
                  @endif
                </td>
                <td class="tx-12">
                  <div>{{ $trf->posted_at ? \App\Support\StockoraFormat::dateTime($trf->posted_at) : '-' }}</div>
                  <div class="tx-color-03">{{ $trf->user->name ?? __('app.system') }}</div>
                </td>
                <td class="text-right">
                  <a href="{{ route('transfers.show', $trf) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($transfers->hasPages())
      <div class="card-footer">{{ $transfers->links() }}</div>
    @endif
  </div>
@endsection
