@extends('layouts.admin')

@section('title', __('products.units_title'))
@section('page_title', __('products.units_title'))
@section('page_subtitle', __('products.units_page_subtitle'))

@section('breadcrumb')
  <li class="breadcrumb-item active" aria-current="page">{{ __('products.units_title') }}</li>
@endsection

@section('actions')
  @if(Auth::user()?->canAccessModule('units', 'create'))
    <a href="{{ route('units.create') }}" class="btn btn-brand-01">
      <i data-feather="plus" class="svg-14"></i> {{ __('products.create_unit') }}
    </a>
  @endif
@endsection

@section('content')
  <div class="row row-sm">
    <div class="col-xl-6 mg-b-15 mg-xl-b-0">
      <div class="card card-hover h-100">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
          <div class="d-flex align-items-center" style="gap: 8px;">
            <h6 class="card-title mg-b-0">{{ __('products.units_catalog_title') }}</h6>
            <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($units->total()) }}</span>
          </div>
          <form action="{{ route('units.index') }}" method="GET" class="form-inline" style="gap: 6px;">
            <div class="input-group input-group-sm">
              <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('products.search_unit_placeholder') }}">
              <div class="input-group-append">
                <button type="submit" class="btn btn-white"><i data-feather="search" class="svg-14"></i></button>
              </div>
            </div>
            @if($search !== '')
              <a href="{{ route('units.index') }}" class="btn btn-sm btn-white">{{ __('app.reset') }}</a>
            @endif
          </form>
        </div>

        @if($units->isEmpty())
          <div class="card-body">
            @include('partials.empty-state', [
              'title' => __('products.no_units_title'),
              'description' => __('products.no_units_desc'),
              'icon' => 'sliders',
              'actionUrl' => route('units.create'),
              'actionLabel' => __('products.create_unit'),
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('products.unit_symbol') }}</th>
                  <th>{{ __('products.col_unit_name') }}</th>
                  <th class="text-right">{{ __('products.col_skus_count') }}</th>
                  <th class="text-right">{{ __('app.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($units as $unit)
                  <tr>
                    <td><span class="badge badge-primary">{{ $unit->symbol }}</span></td>
                    <td class="tx-medium tx-color-01">{{ $unit->name }}</td>
                    <td class="text-right tx-numeric">{{ \App\Support\StockoraFormat::number($unit->products_count) }}</td>
                    <td class="text-right text-nowrap">
                      @if(Auth::user()?->canAccessModule('units', 'edit'))
                        <a href="{{ route('units.edit', $unit) }}" class="btn btn-xs btn-white">{{ __('app.edit') }}</a>
                      @endif
                      @if(Auth::user()?->canAccessModule('units', 'delete'))
                        <form action="{{ route('units.destroy', $unit) }}" method="POST" class="d-inline"
                              data-confirm-title="{{ __('products.delete_unit_title') }}"
                              data-confirm="{{ __('products.delete_unit_confirm', ['name' => $unit->name, 'symbol' => $unit->symbol]) }}">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-xs btn-outline-danger mg-l-4">{{ __('app.delete') }}</button>
                        </form>
                      @endif
                      @if(!Auth::user()?->canAccessModule('units', 'edit') && !Auth::user()?->canAccessModule('units', 'delete'))
                        <span class="tx-12 tx-color-04">{{ __('app.read_only') }}</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif

        @if($units->hasPages())
          <div class="card-footer">{{ $units->links() }}</div>
        @endif
      </div>
    </div>

    <div class="col-xl-6 d-flex flex-column" style="gap: 15px;">
      @php
        $canManageConv = Auth::user()?->canAccessModule('units', 'manage');
        $planAllowsConv = $planFeatures['module_unit_conversions']['enabled'] ?? false;
      @endphp
      @if($canManageConv && $planAllowsConv)
        <div class="card card-hover">
          <div class="card-header">
            <h6 class="card-title mg-b-0">{{ __('products.new_conv_rule_title') }}</h6>
          </div>
          <div class="card-body">
            <form action="{{ route('unit-conversions.store') }}" method="POST">
              @csrf
              <div class="form-group">
                <label>{{ __('products.target_product_label') }} <small class="tx-color-03 tx-normal">{{ __('products.target_product_hint') }}</small></label>
                <select name="product_id" class="form-control">
                  <option value="">-- {{ __('products.global_scope') }} --</option>
                  @foreach($products as $prod)
                    <option value="{{ $prod->id }}" {{ old('product_id') == $prod->id ? 'selected' : '' }}>{{ $prod->code }} - {{ $prod->name }}</option>
                  @endforeach
                </select>
              </div>
              <div class="form-row">
                <div class="form-group col-md-4">
                  <label>{{ __('products.from_uom_short') }}</label>
                  <select name="from_unit_id" class="form-control" required>
                    @foreach($allUnits as $u)
                      <option value="{{ $u->id }}" {{ old('from_unit_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->symbol }})</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label>{{ __('products.to_base_uom_short') }}</label>
                  <select name="to_unit_id" class="form-control" required>
                    @foreach($allUnits as $u)
                      <option value="{{ $u->id }}" {{ old('to_unit_id') == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->symbol }})</option>
                    @endforeach
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label>{{ __('products.multiplier_label') }}</label>
                  <input type="number" step="0.00000001" min="0.00000001" name="factor" value="{{ old('factor', '12') }}" class="form-control tx-numeric" required>
                </div>
              </div>
              <div class="d-flex justify-content-end pt-2">
                <button type="submit" class="btn btn-sm btn-brand-01">{{ __('products.save_conv_btn') }}</button>
              </div>
            </form>
          </div>
        </div>
      @elseif($canManageConv && !$planAllowsConv)
        <div class="card border-warning">
          <div class="card-body py-3 px-4 d-flex flex-wrap justify-content-between align-items-center" style="gap: 10px;">
            <div class="tx-13">
              <i data-feather="lock" class="svg-14 text-warning mg-r-4"></i>
              {{ __('subscription.feature_locked_inline', ['feature' => __('subscription.features.module_unit_conversions')]) }}
            </div>
            <a href="{{ route('plans.index') }}" class="btn btn-xs btn-white">{{ __('subscription.compare_plans_btn') }}</a>
          </div>
        </div>
      @endif

      <div class="card card-hover flex-fill">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="card-title mg-b-0">{{ __('products.active_conv_title') }}</h6>
          <span class="badge badge-light tx-numeric">{{ \App\Support\StockoraFormat::number($conversions->count()) }}</span>
        </div>
        @if($conversions->isEmpty())
          <div class="card-body">
            @include('partials.empty-state', [
              'title' => __('products.no_conv_title'),
              'description' => __('products.no_conv_desc'),
              'icon' => 'repeat',
            ])
          </div>
        @else
          <div class="table-responsive">
            <table class="table table-hover mg-b-0">
              <thead>
                <tr>
                  <th>{{ __('products.col_scope') }}</th>
                  <th>{{ __('products.col_equation') }}</th>
                  <th class="text-right">{{ __('app.action') }}</th>
                </tr>
              </thead>
              <tbody>
                @foreach($conversions as $conv)
                  <tr>
                    <td>
                      @if($conv->product)
                        <span class="tx-medium tx-color-01">{{ $conv->product->code }}</span>
                        <div class="tx-11 tx-color-03">{{ $conv->product->name }}</div>
                      @else
                        <span class="badge badge-light">{{ __('products.global_badge') }}</span>
                      @endif
                    </td>
                    <td class="tx-numeric">
                      1 {{ $conv->fromUnit->symbol ?? $conv->fromUnit->name }} =
                      <strong>{{ rtrim(rtrim($conv->factor, '0'), '.') }}</strong>
                      {{ $conv->toUnit->symbol ?? $conv->toUnit->name }}
                    </td>
                    <td class="text-right">
                      @if($canManageConv && $planAllowsConv)
                        <form action="{{ route('unit-conversions.destroy', $conv) }}" method="POST"
                              data-confirm-title="{{ __('products.delete_conv_title') }}"
                              data-confirm="{{ __('products.delete_conv_confirm') }}">
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
        @endif
      </div>
    </div>
  </div>
@endsection
