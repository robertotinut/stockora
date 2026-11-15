@extends('layouts.admin')

@section('title', __('suppliers.detail_title'))
@section('page_title', $supplier->name)
@section('page_subtitle', __('suppliers.detail_subtitle', ['code' => $supplier->code ?? '-']))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">{{ __('suppliers.page_title') }}</a></li>
  <li class="breadcrumb-item active" aria-current="page">{{ $supplier->code }}</li>
@endsection

@section('actions')
  @if(Route::has('purchases.create') && Auth::user()?->canAccessModule('purchase_orders', 'create') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
    <a href="{{ route('purchases.create') }}" class="btn btn-white">
      <i data-feather="plus" class="svg-14"></i> {{ __('suppliers.new_po_btn') }}
    </a>
  @endif
  @if(Auth::user()?->canAccessModule('suppliers', 'edit'))
    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-brand-01">
      <i data-feather="edit-2" class="svg-14"></i> {{ __('suppliers.edit_title') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm">
    <div class="col-lg-4 mg-b-15 mg-lg-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('suppliers.vendor_profile_title') }}</h6>
          <span class="badge badge-light">{{ $supplier->code }}</span>
        </div>
        <div class="card-body tx-13">
          <table class="table table-sm table-borderless mg-b-0">
            <tr><td class="tx-color-03 pd-l-0">{{ __('suppliers.pic_short') }}</td><td class="text-right tx-medium">{{ $supplier->contact_person ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.phone') }}</td><td class="text-right tx-medium">{{ $supplier->phone ?? '-' }}</td></tr>
            <tr><td class="tx-color-03 pd-l-0">{{ __('app.email') }}</td><td class="text-right tx-medium">{{ $supplier->email ?? '-' }}</td></tr>
          </table>
          <div class="mg-t-12 pt-3 border-top">
            <div class="tx-11 tx-uppercase tx-semibold tx-color-03 mg-b-4">{{ __('app.address') }}</div>
            <div class="tx-color-02">{{ $supplier->address ?: '-' }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-8">
      <div class="card card-hover h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('suppliers.po_history_title') }}</h6>
          <span class="badge badge-light tx-numeric">{{ __('suppliers.po_count_badge', ['count' => \App\Support\StockoraFormat::number($purchases->total())]) }}</span>
        </div>
        @if($purchases->isEmpty())
          <div class="card-body">
            @include('partials.empty-state', [
              'title' => __('suppliers.no_pos_title'),
              'description' => __('suppliers.no_pos_desc'),
              'icon' => 'shopping-cart',
              'actionUrl' => (Route::has('purchases.create') && Auth::user()?->canAccessModule('purchase_orders', 'create') && ($planFeatures['module_purchase_orders']['enabled'] ?? false)) ? route('purchases.create') : null,
              'actionLabel' => __('suppliers.create_po_btn'),
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('suppliers.col_po_number') }}</th>
                  <th>{{ __('app.date') }}</th>
                  <th class="text-right">{{ __('suppliers.col_total_rp') }}</th>
                  <th class="text-center">{{ __('app.status') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($purchases as $po)
                  <tr>
                    <td class="tx-medium">
                      @if(Route::has('purchases.show') && ($planFeatures['module_purchase_orders']['enabled'] ?? false))
                        <a href="{{ route('purchases.show', $po) }}">{{ $po->purchase_number }}</a>
                      @else
                        {{ $po->purchase_number }}
                      @endif
                    </td>
                    <td class="tx-color-03">{{ \App\Support\StockoraFormat::shortDate($po->date) }}</td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($po->total_amount, 2) }}</td>
                    <td class="text-center">@include('partials.status-badge', ['status' => $po->status])</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        @if($purchases->hasPages())
          <div class="card-footer">{{ $purchases->links() }}</div>
        @endif
      </div>
    </div>
  </div>
@endsection
