@extends('layouts.admin')

@section('title', __('products.categories_nav'))
@section('page_title', __('products.categories_title'))
@section('page_subtitle', __('products.categories_page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('products.categories_nav') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('categories', 'create'))
    <a href="{{ route('categories.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('products.create_category') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="card card-hover">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
      <div class="d-flex align-items-center" style="gap: 8px;">
        <h6 class="card-title mg-b-0">{{ __('products.categories_nav') }}</h6>
        <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($categories->total()) }}</span>
      </div>
      <form action="{{ route('categories.index') }}" method="GET" class="form-inline" style="gap: 6px;">
        <div class="input-group input-group-sm">
          <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('products.search_cat_placeholder') }}">
          <div class="input-group-append">
            <button type="submit" class="btn btn-white"><i data-feather="search" class="svg-14"></i></button>
          </div>
        </div>
        @if($search !== '')
          <a href="{{ route('categories.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
        @endif
      </form>
    </div>

    @if($categories->isEmpty())
      <div class="card-body">
        @include('partials.empty-state', [
          'title' => __('products.no_cats_title'),
          'description' => __('products.no_cats_desc'),
          'icon' => 'tag',
          'actionUrl' => $search !== '' ? route('categories.index') : route('categories.create'),
          'actionLabel' => $search !== '' ? __('app.reset_search') : __('products.create_category'),
        ])
      </div>
    @else
      <div class="table-responsive">
        <table class="table table-hover mg-b-0">
          <thead>
            <tr>
              <th>{{ __('app.code') }}</th>
              <th>{{ __('products.col_category_name') }}</th>
              <th>{{ __('app.description') }}</th>
              <th class="text-right">{{ __('products.col_products_count') }}</th>
              <th class="text-right">{{ __('app.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach($categories as $category)
              <tr>
                <td class="tx-medium"><span class="badge badge-light">{{ $category->code ?? '-' }}</span></td>
                <td class="tx-medium tx-color-01">{{ $category->name }}</td>
                <td class="tx-color-03">{{ \Illuminate\Support\Str::limit($category->description ?? '-', 70) }}</td>
                <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($category->products_count) }}</td>
                <td class="text-right text-nowrap">
                  @if(Auth::user()?->canAccessModule('categories', 'edit'))
                    <a href="{{ route('categories.edit', $category) }}" class="btn btn-xs btn-white">{{ __('app.edit') }}</a>
                  @endif
                  @if(Auth::user()?->canAccessModule('categories', 'delete'))
                    <form action="{{ route('categories.destroy', $category) }}" method="POST" class="d-inline"
                          data-confirm-title="{{ __('products.delete_cat_title') }}"
                          data-confirm="{{ __('products.delete_cat_confirm', ['name' => $category->name]) }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-xs btn-outline-danger mg-l-4">{{ __('app.delete') }}</button>
                    </form>
                  @endif
                  @if(!Auth::user()?->canAccessModule('categories', 'edit') && !Auth::user()?->canAccessModule('categories', 'delete'))
                    <span class="tx-12 tx-color-04">{{ __('app.read_only') }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if($categories->hasPages())
      <div class="card-footer">
        {{ $categories->links() }}
      </div>
    @endif
  </div>
@endsection
