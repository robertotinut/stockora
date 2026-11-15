@extends('layouts.admin')

@section('title', $product->exists ? __('products.edit_title') : __('products.create_title'))
@section('page_title', $product->exists ? __('products.edit_heading', ['code' => $product->code]) : __('products.create_heading'))
@section('page_subtitle', $product->exists ? __('products.edit_subtitle') : __('products.create_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item"><a href="{{ route('products.index') }}">{{ __('products.page_title') }}</a></li>
  @if($product->exists)
    <li class="breadcrumb-item"><a href="{{ route('products.show', $product) }}">{{ $product->code }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ __('app.edit') }}</li>
  @else
    <li class="breadcrumb-item active" aria-current="page">{{ __('products.create_title') }}</li>
  @endif
@endsection

@section('content')
  @if(!$product->exists)
    @include('partials.quota-banner', ['limitKey' => 'max_products'])
  @endif

  <div class="row row-sm">
    <div class="col-lg-8 mg-b-20 mg-lg-b-0">
      <div class="card card-hover">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('products.specs_title') }}</h6>
        </div>
        <div class="card-body">
          @php $lockedUnit = $hasHistory ?? false; @endphp
          <form action="{{ $product->exists ? route('products.update', $product) : route('products.store') }}" method="POST">
            @csrf
            @if($product->exists)
              @method('PUT')
            @endif

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ __('products.sku_code') }} <span class="tx-danger">*</span></label>
                <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $product->code) }}" placeholder="PRD-001" required>
                @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-8">
                <label>{{ __('products.product_name') }} <span class="tx-danger">*</span></label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}" placeholder="e.g. A4 Copy Paper 80gsm" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>{{ __('app.category') }} <span class="tx-danger">*</span></label>
                <select name="category_id" class="form-control @error('category_id') is-invalid @enderror" required>
                  <option value="">{{ __('products.select_category') }}</option>
                  @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ (string) old('category_id', $product->category_id) === (string) $category->id ? 'selected' : '' }}>
                      {{ $category->name }}
                    </option>
                  @endforeach
                </select>
                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-6">
                <label>{{ __('products.base_unit') }} <span class="tx-danger">*</span></label>
                @if($lockedUnit)
                  <input type="hidden" name="unit_id" value="{{ $product->unit_id }}">
                @endif
                <select name="unit_id" class="form-control @error('unit_id') is-invalid @enderror" {{ $lockedUnit ? 'disabled' : 'required' }}>
                  <option value="">{{ __('products.select_base_unit') }}</option>
                  @foreach($units as $unit)
                    <option value="{{ $unit->id }}" {{ (string) old('unit_id', $product->unit_id) === (string) $unit->id ? 'selected' : '' }}>
                      {{ $unit->name }} ({{ $unit->symbol }})
                    </option>
                  @endforeach
                </select>
                @if($lockedUnit)
                  <small class="form-text text-muted">{{ __('products.unit_locked_note') }}</small>
                @endif
                @error('unit_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>{{ __('products.ref_purchase_price_rp') }} <span class="tx-danger">*</span></label>
                <input type="number" step="0.000001" min="0" name="purchase_price" class="form-control tx-numeric @error('purchase_price') is-invalid @enderror" value="{{ old('purchase_price', rtrim(rtrim((string) $product->purchase_price, '0'), '.') ?: '0') }}" required>
                @error('purchase_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-4">
                <label>{{ __('products.selling_price_rp') }} <span class="tx-danger">*</span></label>
                <input type="number" step="0.000001" min="0" name="selling_price" class="form-control tx-numeric @error('selling_price') is-invalid @enderror" value="{{ old('selling_price', rtrim(rtrim((string) $product->selling_price, '0'), '.') ?: '0') }}" required>
                @error('selling_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
              <div class="form-group col-md-4">
                <label>{{ __('products.min_stock_level') }} <span class="tx-danger">*</span></label>
                <input type="number" step="0.0001" min="0" name="min_stock" class="form-control tx-numeric @error('min_stock') is-invalid @enderror" value="{{ old('min_stock', rtrim(rtrim((string) $product->min_stock, '0'), '.') ?: '0') }}" required>
                @error('min_stock')<div class="invalid-feedback">{{ $message }}</div>@enderror
              </div>
            </div>

            <div class="form-group mg-b-0">
              <label>{{ __('products.notes_desc_label') }}</label>
              <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="{{ __('products.desc_placeholder') }}">{{ old('description', $product->description) }}</textarea>
              @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex justify-content-end mg-t-20 pt-3 border-top" style="gap: 8px;">
              <a href="{{ $product->exists ? route('products.show', $product) : route('products.index') }}" class="btn btn-white">{{ __('app.cancel') }}</a>
              <button type="submit" class="btn btn-brand-01">{{ $product->exists ? __('app.save_changes') : __('products.create_product_btn') }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card card-hover mg-b-15">
        <div class="card-header">
          <h6 class="card-title mg-b-0">{{ __('products.control_panel_title') }}</h6>
        </div>
        <div class="card-body tx-13">
          <p class="tx-color-03 mg-b-12">
            {{ __('products.control_panel_desc') }}
          </p>
          @if($product->exists)
            <div class="pt-3 border-top">
              <div class="d-flex justify-content-between mg-b-8">
                <span class="tx-color-03">{{ __('products.operational_stock') }}</span>
                <span class="tx-numeric tx-semibold">{{ \App\Support\StockoraFormat::quantity($product->stock, 4) }} {{ $product->unit->symbol ?? '' }}</span>
              </div>
              <div class="d-flex justify-content-between mg-b-8">
                <span class="tx-color-03">{{ __('products.current_mac') }}</span>
                <span class="tx-numeric tx-medium">{{ \App\Support\StockoraFormat::currency($product->moving_average_cost, 2) }}</span>
              </div>
              <div class="d-flex justify-content-between align-items-center">
                <span class="tx-color-03">{{ __('products.col_reconciliation') }}</span>
                @include('partials.status-badge', ['status' => $product->legacy_reconciliation_status])
              </div>
            </div>
          @else
            <div class="alert alert-info tx-12 mg-b-0">
              {{ __('products.new_product_hint') }}
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
@endsection
