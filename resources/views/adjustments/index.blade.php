@extends('layouts.admin')

@section('title', __('operations.adjustments_title'))
@section('page_title', __('operations.adjustments_index_heading'))
@section('page_subtitle', __('operations.adjustments_index_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_warehouse_stock']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('inventory.warehouses.index') }}">{{ __('operations.inventory_breadcrumb') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.adjustments_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('stock_adjustments', 'create'))
    <a href="{{ route('adjustments.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('operations.new_adjustment_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('adjustments.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.search_num_reason_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="{{ __('operations.search_num_reason_placeholder') }}">
        </div>
        <div class="col-sm-6 col-md-4 col-xl-3 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.warehouse_label') }}</label>
          <select name="warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_warehouses') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $warehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-sm-6 col-md-4 col-xl-2 mg-b-10 mg-xl-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('app.status') }}</label>
          <select name="status" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_statuses') }}</option>
            <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>{{ __('operations.status_draft') }}</option>
            <option value="posted" {{ $status === 'posted' ? 'selected' : '' }}>{{ __('operations.status_posted') }}</option>
          </select>
        </div>
        <div class="col-sm-6 col-md-8 col-xl-2 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.doc_type_label') }}</label>
          <select name="doc_kind" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_types') }}</option>
            <option value="manual" {{ $docKind === 'manual' ? 'selected' : '' }}>{{ __('operations.manual_opname') }}</option>
            <option value="reversal" {{ $docKind === 'reversal' ? 'selected' : '' }}>{{ __('operations.reversal_entry') }}</option>
          </select>
        </div>
        <div class="col-12 col-md-4 col-xl-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $warehouseId !== '' || $status !== '' || $docKind !== '')
            <a href="{{ route('adjustments.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.adjustments_card_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($adjustments->total()) }}</span>
      </div>
    </div>

    @if($adjustments->isEmpty())
      <div class="card-body">
        @php
          $isFiltered = $search !== '' || $warehouseId !== '' || $status !== '' || $docKind !== '';
          $emptyActionUrl = $isFiltered
              ? route('adjustments.index')
              : (Auth::user()?->canAccessModule('stock_adjustments', 'create') ? route('adjustments.create') : null);
        @endphp
        @include('partials.empty-state', [
          'title' => __('operations.no_adjustments_title'),
          'description' => __('operations.no_adjustments_desc'),
          'icon' => 'edit-3',
          'actionUrl' => $emptyActionUrl,
          'actionLabel' => $isFiltered ? __('app.reset_filters') : __('operations.new_adjustment_btn'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('operations.col_doc_no') }}</th>
              <th>{{ __('operations.warehouse_label') }}</th>
              <th>{{ __('app.reason') }}</th>
              <th class="text-right">{{ __('operations.col_lines') }}</th>
              <th class="text-center">{{ __('app.status') }}</th>
              <th>{{ __('operations.col_timestamp_user') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($adjustments as $adj)
              <tr>
                <td>
                  <a href="{{ route('adjustments.show', $adj) }}" class="tx-medium tx-color-01">{{ $adj->adjustment_number }}</a>
                  @if($adj->reverses_type)
                    <div class="mg-t-2"><span class="badge badge-info">{{ __('operations.reverses_badge', ['type' => $adj->reverses_type, 'id' => $adj->reverses_id]) }}</span></div>
                  @endif
                </td>
                <td><span class="badge badge-light">{{ $adj->warehouse->code ?? '-' }}</span></td>
                <td class="tx-12">{{ \Illuminate\Support\Str::limit($adj->reason ?? '-', 55) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($adj->items_count) }}</td>
                <td class="text-center">
                  @if($adj->reversalAdjustment)
                    @include('partials.status-badge', ['status' => 'REVERSED'])
                  @else
                    @include('partials.status-badge', ['status' => $adj->status])
                  @endif
                </td>
                <td class="tx-12">
                  <div>{{ $adj->posted_at ? \App\Support\StockoraFormat::dateTime($adj->posted_at) : ($adj->created_at ? \App\Support\StockoraFormat::dateTime($adj->created_at) : '-') }}</div>
                  <div class="tx-color-03">{{ $adj->user->name ?? __('app.system') }}</div>
                </td>
                <td class="text-right text-nowrap">
                  <a href="{{ route('adjustments.show', $adj) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                  @if($adj->status === 'draft' && Auth::user()?->canAccessModule('stock_adjustments', 'edit'))
                    <a href="{{ route('adjustments.edit', $adj) }}" class="btn btn-xs btn-brand-01 mg-l-4">{{ __('operations.edit_draft_btn') }}</a>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($adjustments->hasPages())
      <div class="card-footer">{{ $adjustments->links() }}</div>
    @endif
  </div>
@endsection
