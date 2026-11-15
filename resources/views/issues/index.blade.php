@extends('layouts.admin')

@section('title', __('operations.issues_title'))
@section('page_title', __('operations.issues_title'))
@section('page_subtitle', __('operations.issues_index_subtitle'))

@section('breadcrumb')
  @if(Auth::user()?->canAccessModule('warehouse_stock', 'view') && ($planFeatures['module_warehouse_stock']['enabled'] ?? false))
    <li class="breadcrumb-item"><a href="{{ route('inventory.warehouses.index') }}">{{ __('operations.inventory_breadcrumb') }}</a></li>
  @endif
  <li class="breadcrumb-item active" aria-current="page">{{ __('operations.issues_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('stock_issues', 'create'))
    <a href="{{ route('issues.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('operations.new_issue_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card stk-filter-card mg-b-20">
    <div class="card-body">
      <form action="{{ route('issues.index') }}" method="GET" class="row row-xs align-items-end">
        <div class="col-sm-6 col-md-5 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.search_doc_notes_label') }}</label>
          <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="ISS-...">
        </div>
        <div class="col-sm-6 col-md-5 mg-b-10 mg-md-b-0">
          <label class="stk-field-label tx-11 tx-uppercase tx-color-03 d-block">{{ __('operations.warehouse_label') }}</label>
          <select name="warehouse_id" class="form-control form-control-sm">
            <option value="">{{ __('operations.all_warehouses') }}</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ (string) $warehouseId === (string) $wh->id ? 'selected' : '' }}>{{ $wh->code }} - {{ $wh->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12 col-md-2 d-flex" style="gap: 6px;">
          <button type="submit" class="btn btn-sm btn-brand-01 flex-fill">{{ __('app.apply') }}</button>
          @if($search !== '' || $warehouseId !== '')
            <a href="{{ route('issues.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
          @endif
        </div>
      </form>
    </div>
  </div>

  <div class="card card-hover">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('operations.issues_card_title') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($issues->total()) }}</span>
      </div>
    </div>

    @if($issues->isEmpty())
      <div class="card-body">
        @php
          $isFiltered = $search !== '' || $warehouseId !== '';
          $emptyActionUrl = $isFiltered
              ? route('issues.index')
              : (Auth::user()?->canAccessModule('stock_issues', 'create') ? route('issues.create') : null);
        @endphp
        @include('partials.empty-state', [
          'title' => __('operations.no_issues_title'),
          'description' => __('operations.no_issues_desc'),
          'icon' => 'arrow-up-right',
          'actionUrl' => $emptyActionUrl,
          'actionLabel' => $isFiltered ? __('app.reset_filters') : __('operations.new_issue_btn'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('operations.issue_number') }}</th>
              <th>{{ __('operations.warehouse_label') }}</th>
              <th class="text-right">{{ __('operations.col_lines') }}</th>
              <th class="text-center">{{ __('app.status') }}</th>
              <th>{{ __('operations.col_posted_user') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($issues as $iss)
              <tr>
                <td>
                  <a href="{{ route('issues.show', $iss) }}" class="tx-medium tx-color-01">{{ $iss->issue_number }}</a>
                  @if($iss->notes)
                    <div class="tx-11 tx-color-03">{{ \Illuminate\Support\Str::limit($iss->notes, 60) }}</div>
                  @endif
                </td>
                <td><span class="badge badge-light">{{ $iss->warehouse->code ?? '-' }}</span></td>
                <td class="text-right tx-numeric">{{ __('operations.items_count_inline', ['count' => \App\Support\StockoraFormat::number($iss->items_count)]) }}</td>
                <td class="text-center">
                  @if($iss->reversalAdjustment)
                    @include('partials.status-badge', ['status' => 'REVERSED'])
                  @else
                    @include('partials.status-badge', ['status' => $iss->status])
                  @endif
                </td>
                <td class="tx-12">
                  <div>{{ $iss->posted_at ? \App\Support\StockoraFormat::dateTime($iss->posted_at) : '-' }}</div>
                  <div class="tx-color-03">{{ $iss->user->name ?? __('app.system') }}</div>
                </td>
                <td class="text-right">
                  <a href="{{ route('issues.show', $iss) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($issues->hasPages())
      <div class="card-footer">{{ $issues->links() }}</div>
    @endif
  </div>
@endsection
