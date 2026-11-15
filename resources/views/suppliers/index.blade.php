@extends('layouts.admin')

@section('title', __('suppliers.page_title'))
@section('page_title', __('suppliers.directory_title'))
@section('page_subtitle', __('suppliers.directory_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('suppliers.page_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('suppliers', 'create'))
    <a href="{{ route('suppliers.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('suppliers.add_supplier_btn') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('suppliers.registered_suppliers') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($suppliers->total()) }}</span>
      </div>
      <form action="{{ route('suppliers.index') }}" method="GET" class="form-inline" style="gap: 6px;">
        <div class="input-group input-group-sm">
          <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('suppliers.search_placeholder') }}">
          <div class="input-group-append">
            <button type="submit" class="btn btn-white"><i data-feather="search" class="svg-14"></i></button>
          </div>
        </div>
        @if($search !== '')
          <a href="{{ route('suppliers.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
        @endif
      </form>
    </div>

    @if($suppliers->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('suppliers.no_suppliers'),
          'description' => __('suppliers.no_suppliers_desc'),
          'icon' => 'truck',
          'actionUrl' => $search !== '' ? route('suppliers.index') : route('suppliers.create'),
          'actionLabel' => $search !== '' ? __('app.reset_search') : __('suppliers.add_supplier_btn'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('app.code') }}</th>
              <th>{{ __('suppliers.name') }}</th>
              <th>{{ __('suppliers.col_pic') }}</th>
              <th>{{ __('suppliers.col_phone_email') }}</th>
              <th class="text-right">{{ __('suppliers.col_total_po') }}</th>
              <th class="text-right">{{ __('suppliers.col_purchase_spend') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($suppliers as $supplier)
              <tr>
                <td><span class="badge badge-light">{{ $supplier->code ?? '-' }}</span></td>
                <td>
                  <a href="{{ route('suppliers.show', $supplier) }}" class="tx-medium tx-color-01">{{ $supplier->name }}</a>
                </td>
                <td>{{ $supplier->contact_person ?? '-' }}</td>
                <td class="tx-12 tx-color-03">
                  <div>{{ $supplier->phone ?? '-' }}</div>
                  @if($supplier->email)
                    <div>{{ $supplier->email }}</div>
                  @endif
                </td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($supplier->purchases_count) }}</td>
                <td class="text-right tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($supplier->active_purchases_sum ?? 0, 0) }}</td>
                <td class="text-right text-nowrap">
                  <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-xs btn-white">{{ __('app.detail') }}</a>
                  @if(Auth::user()?->canAccessModule('suppliers', 'edit'))
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-xs btn-white mg-l-4">{{ __('app.edit') }}</a>
                  @endif
                  @if(Auth::user()?->canAccessModule('suppliers', 'delete'))
                    <form action="{{ route('suppliers.destroy', $supplier) }}" method="POST" class="d-inline"
                          data-confirm-title="{{ __('suppliers.delete_sup_title') }}"
                          data-confirm="{{ __('suppliers.delete_sup_confirm', ['name' => $supplier->name]) }}">
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

    @if($suppliers->hasPages())
      <div class="card-footer">{{ $suppliers->links() }}</div>
    @endif
  </div>
@endsection
