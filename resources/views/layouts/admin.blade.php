<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/img/favicon.png') }}">
    <title>@yield('title', __('navigation.dashboard')) - {{ __('app.brand_name') }}</title>

    <link href="{{ asset('lib/@fortawesome/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('lib/ionicons/css/ionicons.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/cassie.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/stockora.css') }}">
    @stack('styles')
  </head>
  <body>

    <div class="stk-sidebar-backdrop" id="stkSidebarBackdrop"></div>

    <aside class="sidebar">
      <div class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="stk-brand-lockup">
          <span class="stk-brand-icon" aria-hidden="true">
            @if(isset($activeCompany) && $activeCompany->logoUrl())
              <img src="{{ $activeCompany->logoUrl() }}" alt="{{ $activeCompany->name }}" class="stk-brand-logo-img">
            @else
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                <line x1="12" y1="22.08" x2="12" y2="12"/>
              </svg>
            @endif
          </span>
          <span>
            <span class="stk-brand-wordmark d-block">stockora</span>
            <small class="stk-brand-tagline d-block" title="{{ $activeCompany->name ?? 'Manage Stock. Control Growth.' }}">
              {{ $activeCompany ? \Illuminate\Support\Str::limit($activeCompany->name, 22) : 'Manage Stock. Control Growth.' }}
            </small>
          </span>
        </a>
      </div>

      <div id="dpSidebarBody" class="sidebar-body">
        @php
          $authUser = Auth::user();
          $featuresMap = $planFeatures ?? [];
          $planOn = static fn (string $key): bool => (bool) ($featuresMap[$key]['enabled'] ?? false);

          $canDashboard = $authUser?->canAccessModule('dashboard', 'view') ?? false;
          $canProducts = Route::has('products.index') && $planOn('module_products') && ($authUser?->canAccessModule('products', 'view') ?? false);
          $canCategories = Route::has('categories.index') && $planOn('module_products') && ($authUser?->canAccessModule('categories', 'view') ?? false);
          $canUnits = Route::has('units.index') && $planOn('module_products') && ($authUser?->canAccessModule('units', 'view') ?? false);
          $canWarehouses = Route::has('warehouses.index') && $planOn('module_warehouses') && ($authUser?->canAccessModule('warehouses', 'view') ?? false);
          $canWarehouseStock = Route::has('inventory.warehouses.index') && $planOn('module_stock_monitoring') && ($authUser?->canAccessModule('warehouse_stock', 'view') ?? false);
          $canStockMovements = Route::has('stock-movements.index') && $planOn('module_stock_movements') && ($authUser?->canAccessModule('stock_movements', 'view') ?? false);
          $canAdjustments = Route::has('adjustments.index') && $planOn('module_stock_adjustments') && ($authUser?->canAccessModule('stock_adjustments', 'view') ?? false);

          $canSuppliers = Route::has('suppliers.index') && $planOn('module_suppliers') && ($authUser?->canAccessModule('suppliers', 'view') ?? false);
          $canPurchases = Route::has('purchases.index') && $planOn('module_purchase_orders') && ($authUser?->canAccessModule('purchase_orders', 'view') ?? false);
          $canGrns = Route::has('grns.index') && $planOn('module_grn') && ($authUser?->canAccessModule('grn', 'view') ?? false);

          $canTransfers = Route::has('transfers.index') && $planOn('module_stock_transfers') && ($authUser?->canAccessModule('stock_transfers', 'view') ?? false);
          $canIssues = Route::has('issues.index') && $planOn('module_stock_issues') && ($authUser?->canAccessModule('stock_issues', 'view') ?? false);

          $canReports = Route::has('reports.index') && $planOn('module_inventory_reports') && ($authUser?->canAccessModule('inventory_reports', 'view') ?? false);
          $canReconciliation = Route::has('reconciliation-audits.index') && $planOn('module_reconciliation_audits') && ($authUser?->canAccessModule('warehouse_stock', 'manage') ?? false);

          $canTeamView = Route::has('team.index') && $planOn('module_team_management') && ($authUser?->canAccessModule('team_management', 'view') ?? false);
          $canTeamInvites = $canTeamView && Route::has('team.invitations.index') && $planOn('module_team_invitations');
          $canTeamManage = Route::has('team.permissions.index') && $planOn('module_team_management') && $planOn('module_role_permissions') && ($authUser?->canAccessModule('team_management', 'manage') ?? false);
          $canCompanyList = Route::has('companies.index') && ($authUser?->canAccessModule('company_settings', 'view') ?? false);
          $canCompanyEdit = isset($activeCompany) && Route::has('companies.edit') && ($authUser?->canAccessModule('company_settings', 'edit') ?? false);
        @endphp

        <ul class="nav nav-sidebar">
          @if($canDashboard)
            <li class="nav-label"><label class="content-label">{{ __('navigation.overview') }}</label></li>
            <li class="nav-item">
              <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i data-feather="grid"></i> {{ __('navigation.dashboard') }}
              </a>
            </li>
          @endif

          @if($canProducts || $canCategories || $canUnits || $canWarehouses || $canWarehouseStock || $canStockMovements || $canAdjustments)
            <li class="nav-label"><label class="content-label">{{ __('navigation.inventory') }}</label></li>
            @if($canProducts)
              <li class="nav-item">
                <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                  <i data-feather="package"></i> {{ __('navigation.products') }}
                </a>
              </li>
            @endif
            @if($canCategories)
              <li class="nav-item">
                <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                  <i data-feather="tag"></i> {{ __('navigation.categories') }}
                </a>
              </li>
            @endif
            @if($canUnits)
              <li class="nav-item">
                <a href="{{ route('units.index') }}" class="nav-link {{ request()->routeIs('units.*', 'unit-conversions.*') ? 'active' : '' }}">
                  <i data-feather="sliders"></i> {{ __('navigation.units_conversions') }}
                </a>
              </li>
            @endif
            @if($canWarehouses)
              <li class="nav-item">
                <a href="{{ route('warehouses.index') }}" class="nav-link {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">
                  <i data-feather="home"></i> {{ __('navigation.warehouses') }}
                </a>
              </li>
            @endif
            @if($canWarehouseStock)
              <li class="nav-item">
                <a href="{{ route('inventory.warehouses.index') }}" class="nav-link {{ request()->routeIs('inventory.warehouses.*') ? 'active' : '' }}">
                  <i data-feather="layers"></i> {{ __('navigation.stock_overview') }}
                </a>
              </li>
            @endif
            @if($canStockMovements)
              <li class="nav-item">
                <a href="{{ route('stock-movements.index') }}" class="nav-link {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}">
                  <i data-feather="activity"></i> {{ __('navigation.stock_movements') }}
                </a>
              </li>
            @endif
            @if($canAdjustments)
              <li class="nav-item">
                <a href="{{ route('adjustments.index') }}" class="nav-link {{ request()->routeIs('adjustments.*') ? 'active' : '' }}">
                  <i data-feather="edit-3"></i> {{ __('navigation.stock_adjustments') }}
                </a>
              </li>
            @endif
          @endif

          @if($canSuppliers || $canPurchases || $canGrns)
            <li class="nav-label"><label class="content-label">{{ __('navigation.purchasing') }}</label></li>
            @if($canSuppliers)
              <li class="nav-item">
                <a href="{{ route('suppliers.index') }}" class="nav-link {{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                  <i data-feather="truck"></i> {{ __('navigation.suppliers') }}
                </a>
              </li>
            @endif
            @if($canPurchases)
              <li class="nav-item">
                <a href="{{ route('purchases.index') }}" class="nav-link {{ request()->routeIs('purchases.*') ? 'active' : '' }}">
                  <i data-feather="shopping-cart"></i> {{ __('navigation.purchase_orders') }}
                </a>
              </li>
            @endif
            @if($canGrns)
              <li class="nav-item">
                <a href="{{ route('grns.index') }}" class="nav-link {{ request()->routeIs('grns.*') ? 'active' : '' }}">
                  <i data-feather="check-square"></i> {{ __('navigation.goods_receiving') }}
                </a>
              </li>
            @endif
          @endif

          @if($canTransfers || $canIssues)
            <li class="nav-label"><label class="content-label">{{ __('navigation.operations') }}</label></li>
            @if($canTransfers)
              <li class="nav-item">
                <a href="{{ route('transfers.index') }}" class="nav-link {{ request()->routeIs('transfers.*') ? 'active' : '' }}">
                  <i data-feather="repeat"></i> {{ __('navigation.stock_transfers') }}
                </a>
              </li>
            @endif
            @if($canIssues)
              <li class="nav-item">
                <a href="{{ route('issues.index') }}" class="nav-link {{ request()->routeIs('issues.*') ? 'active' : '' }}">
                  <i data-feather="arrow-up-right"></i> {{ __('navigation.stock_issues') }}
                </a>
              </li>
            @endif
          @endif

          @if($canReports || $canReconciliation)
            <li class="nav-label"><label class="content-label">{{ __('navigation.reports_section') }}</label></li>
            @if($canReports)
              <li class="nav-item">
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                  <i data-feather="bar-chart-2"></i> {{ __('navigation.inventory_reports') }}
                </a>
              </li>
            @endif
            @if($canReconciliation)
              <li class="nav-item">
                <a href="{{ route('reconciliation-audits.index') }}" class="nav-link {{ request()->routeIs('reconciliation-audits.*') ? 'active' : '' }}">
                  <i data-feather="shield"></i> {{ __('navigation.reconciliation_audit') }}
                </a>
              </li>
            @endif
          @endif
        </ul>

        <hr class="sidebar-divider">

        <ul class="nav nav-sidebar">
          <li class="nav-label"><label class="content-label">{{ __('navigation.settings_section') }}</label></li>
          @if($canTeamView)
            <li class="nav-item">
              <a href="{{ route('team.index') }}" class="nav-link {{ request()->routeIs('team.index', 'team.show') ? 'active' : '' }}">
                <i data-feather="users"></i> {{ __('navigation.team_management') }}
              </a>
            </li>
          @endif
          @if($canTeamInvites)
            <li class="nav-item">
              <a href="{{ route('team.invitations.index') }}" class="nav-link {{ request()->routeIs('team.invitations*') ? 'active' : '' }}">
                <i data-feather="mail"></i> {{ __('navigation.invitations') }}
              </a>
            </li>
          @endif
          @if($canTeamManage)
            <li class="nav-item">
              <a href="{{ route('team.permissions.index') }}" class="nav-link {{ request()->routeIs('team.permissions*') ? 'active' : '' }}">
                <i data-feather="lock"></i> {{ __('navigation.permissions') }}
              </a>
            </li>
          @endif
          @if($canCompanyList)
            <li class="nav-item">
              <a href="{{ route('companies.index') }}" class="nav-link {{ request()->routeIs('companies.index', 'companies.create') ? 'active' : '' }}">
                <i data-feather="briefcase"></i> {{ __('navigation.companies') }}
              </a>
            </li>
          @endif
          @if($canCompanyEdit)
            <li class="nav-item">
              <a href="{{ route('companies.edit', $activeCompany) }}" class="nav-link {{ request()->routeIs('companies.edit') ? 'active' : '' }}">
                <i data-feather="settings"></i> {{ __('navigation.company_settings') }}
              </a>
            </li>
          @endif
          @if(isset($activeCompany) && $authUser && app(\App\Services\RbacService::class)->canManageCompanyBilling($authUser, (int) $activeCompany->id, false))
            <li class="nav-item">
              <a href="{{ route('subscription.index') }}" class="nav-link {{ request()->routeIs('subscription.*') ? 'active' : '' }}">
                <i data-feather="credit-card"></i> {{ __('navigation.subscription_billing') }}
              </a>
            </li>
          @endif
          @if(Route::has('plans.index'))
            <li class="nav-item">
              <a href="{{ route('plans.index') }}" class="nav-link {{ request()->routeIs('plans.index') ? 'active' : '' }}">
                <i data-feather="layers"></i> {{ __('navigation.subscription_plans') }}
              </a>
            </li>
          @endif
          <li class="nav-item">
            <a href="{{ route('company.select') }}" class="nav-link">
              <i data-feather="shuffle"></i> {{ __('navigation.switch_tenant') }}
            </a>
          </li>
        </ul>
      </div>
    </aside>

    <div class="content content-page">
      <header class="header">
        <div class="header-left">
          <a href="#" class="burger-menu" id="stkBurgerBtn" aria-label="{{ __('navigation.toggle_navigation') }}"><i data-feather="menu"></i></a>
          @if($canProducts)
            <form action="{{ route('products.index') }}" method="GET" class="header-search">
              <i data-feather="search"></i>
              <input type="search" name="search" value="{{ request()->routeIs('products.index') ? request('search') : '' }}" class="form-control" placeholder="{{ __('navigation.search_sku_placeholder') }}">
            </form>
          @endif
        </div>

        <div class="header-right">
          @include('partials.language-switcher')

          @if(isset($activeCompany))
            <div class="dropdown">
              <a href="#" class="stk-tenant-btn dropdown-toggle" data-toggle="dropdown" data-display="static" aria-expanded="false">
                <span class="stk-tenant-code">{{ $activeCompany->code }}</span>
                <span class="d-none d-md-inline tx-color-02">{{ \Illuminate\Support\Str::limit($activeCompany->name, 22) }}</span>
              </a>
              <div class="dropdown-menu dropdown-menu-right" style="min-width: 260px;">
                <h6 class="dropdown-header tx-11 text-uppercase tx-color-03">{{ __('navigation.active_workspace') }}</h6>
                @foreach($userCompanies ?? collect() as $co)
                  <form action="{{ route('company.switch') }}" method="POST">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $co->id }}">
                    <button type="submit" class="dropdown-item justify-content-between {{ (int) $co->id === (int) $activeCompany->id ? 'active' : '' }}">
                      <span class="text-truncate" style="max-width: 165px;">{{ $co->name }}</span>
                      <span class="badge badge-light">{{ $co->code }}</span>
                    </button>
                  </form>
                @endforeach
                <div class="dropdown-divider"></div>
                <a href="{{ route('company.select') }}" class="dropdown-item"><i data-feather="shuffle" class="svg-14"></i> {{ __('navigation.switch_company_view') }}</a>
                @if($canCompanyList)
                  <a href="{{ route('companies.index') }}" class="dropdown-item"><i data-feather="briefcase" class="svg-14"></i> {{ __('navigation.manage_companies') }}</a>
                @endif
              </div>
            </div>
          @endif

          <div class="dropdown dropdown-notification">
            <a href="#" class="dropdown-link {{ (($navLowStock ?? 0) + ($navQuarantinedCount ?? 0)) > 0 ? 'new' : '' }}" data-toggle="dropdown" data-display="static" aria-label="{{ __('navigation.notifications') }}"><i data-feather="bell"></i></a>
            <div class="dropdown-menu dropdown-menu-right">
              <div class="dropdown-menu-header">
                <h6>{{ __('navigation.system_alerts') }}</h6>
              </div>
              <div class="dropdown-menu-body">
                @if(($navQuarantinedCount ?? 0) > 0)
                  <a href="{{ $canReconciliation ? route('reconciliation-audits.index') : route('dashboard') }}" class="dropdown-item">
                    <div class="avatar"><span class="avatar-initial rounded-circle tx-warning bg-warning-light"><i data-feather="shield-off" class="svg-14"></i></span></div>
                    <div class="dropdown-item-body">
                      <p>{{ __('navigation.legacy_quarantined_alert', ['count' => \App\Support\StockoraFormat::number($navQuarantinedCount ?? 0)]) }}</p>
                      <span>{{ __('navigation.review_exceptions') }}</span>
                    </div>
                  </a>
                @endif
                @if(($navLowStock ?? 0) > 0)
                  <a href="{{ $canProducts ? route('products.index', ['stock_filter' => 'low']) : route('dashboard') }}" class="dropdown-item">
                    <div class="avatar"><span class="avatar-initial rounded-circle tx-pink bg-pink-light"><i data-feather="alert-triangle" class="svg-14"></i></span></div>
                    <div class="dropdown-item-body">
                      <p>{{ __('navigation.low_stock_alert', ['count' => \App\Support\StockoraFormat::number($navLowStock ?? 0)]) }}</p>
                      <span>{{ __('navigation.reorder_recommended') }}</span>
                    </div>
                  </a>
                @endif
                @if(($navLowStock ?? 0) === 0 && ($navQuarantinedCount ?? 0) === 0)
                  <div class="pd-15 tx-12 tx-color-03 text-center">{{ __('navigation.alerts_clear') }}</div>
                @endif
              </div>
            </div>
          </div>

          <div class="dropdown dropdown-loggeduser">
            <a href="#" class="dropdown-link" data-toggle="dropdown" data-display="static" aria-label="User Profile">
              <div class="avatar avatar-sm">
                <span class="avatar-initial rounded-circle bg-brand-01">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-right" style="min-width: 240px;">
              <div class="dropdown-menu-header">
                <div class="media align-items-center">
                  <div class="avatar">
                    <span class="avatar-initial rounded-circle bg-brand-01">{{ strtoupper(substr(Auth::user()->name, 0, 1)) }}</span>
                  </div>
                  <div class="media-body mg-l-10">
                    <h6>{{ Auth::user()->name }}</h6>
                    <span class="d-block text-truncate" style="max-width: 155px;">{{ Auth::user()->email }}</span>
                    @if(!empty($activeCompanyRole))
                      <div class="mg-t-4">
                        @include('partials.role-badge', ['role' => $activeCompanyRole])
                      </div>
                    @endif
                  </div>
                </div>
              </div>
              <div class="dropdown-menu-body">
                @if($canTeamView)
                  <a href="{{ route('team.index') }}" class="dropdown-item"><i data-feather="users" class="svg-14"></i> {{ __('navigation.team_management') }}</a>
                @endif
                @if($canCompanyEdit)
                  <a href="{{ route('companies.edit', $activeCompany) }}" class="dropdown-item"><i data-feather="settings" class="svg-14"></i> {{ __('navigation.company_settings') }}</a>
                @endif
                @if(isset($activeCompany) && $authUser && app(\App\Services\RbacService::class)->canManageCompanyBilling($authUser, (int) $activeCompany->id, false))
                  <a href="{{ route('subscription.index') }}" class="dropdown-item"><i data-feather="credit-card" class="svg-14"></i> {{ __('navigation.subscription_billing') }}</a>
                @endif
                <a href="{{ route('company.select') }}" class="dropdown-item"><i data-feather="shuffle" class="svg-14"></i> {{ __('navigation.switch_company_view') }}</a>
                @if($canCompanyList)
                  <a href="{{ route('companies.index') }}" class="dropdown-item"><i data-feather="briefcase" class="svg-14"></i> {{ __('navigation.manage_companies') }}</a>
                @endif
                <div class="dropdown-divider"></div>
                <form id="logout-form" action="{{ route('logout') }}" method="POST">
                  @csrf
                  <button type="submit" class="dropdown-item text-danger"><i data-feather="log-out" class="svg-14"></i> {{ __('app.sign_out') }}</button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </header>

      <div class="content-header flex-wrap justify-content-between align-items-start align-items-sm-center">
        <div class="mg-r-10">
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('app.brand_name') }}</a></li>
              @yield('breadcrumb')
            </ol>
          </nav>
          <h4 class="content-title content-title-xs">@yield('page_title', __('navigation.dashboard'))</h4>
          @hasSection('page_subtitle')
            <p class="stk-page-subtitle">@yield('page_subtitle')</p>
          @endif
        </div>
        @hasSection('actions')
          <div class="content-actions d-flex flex-wrap align-items-center mg-t-10 mg-sm-t-0">@yield('actions')</div>
        @endif
      </div>

      <main class="content-body">
        @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i data-feather="check-circle" class="svg-14 mg-r-5"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
        @endif
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i data-feather="alert-circle" class="svg-14 mg-r-5"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
        @endif
        @if($errors->any())
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>{{ __('app.validation_error_heading') }}</strong>
            <ul class="mg-b-0 mg-t-5 pd-l-20">
              @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
              @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
        @endif

        @yield('content')
      </main>
    </div>

    {{-- Reusable Confirmation Modal --}}
    <div class="modal fade" id="stockoraConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h6 class="modal-title font-weight-bold" id="stockoraConfirmTitle">{{ __('app.confirm_action') }}</h6>
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('app.close') }}"><span aria-hidden="true">&times;</span></button>
          </div>
          <div class="modal-body" id="stockoraConfirmMessage">{{ __('app.confirm_message') }}</div>
          <div class="modal-footer">
            <button type="button" class="btn btn-white" data-dismiss="modal">{{ __('app.cancel') }}</button>
            <button type="button" class="btn btn-danger" id="stockoraConfirmSubmitBtn">{{ __('app.confirm') }}</button>
          </div>
        </div>
      </div>
    </div>

    <script src="{{ asset('lib/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('lib/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('lib/feather-icons/feather.min.js') }}"></script>
    <script src="{{ asset('lib/perfect-scrollbar/perfect-scrollbar.min.js') }}"></script>
    <script src="{{ asset('lib/js-cookie/js.cookie.js') }}"></script>
    @stack('vendor-scripts')
    <script src="{{ asset('assets/js/cassie.js') }}"></script>
    <script>
      $(function () {
        var pendingConfirmForm = null;

        $('#stkSidebarBackdrop').on('click', function () {
          $('body').removeClass('toggle-sidebar');
        });

        $(document).on('click', '.dropdown-link, #stkBurgerBtn', function (e) {
          if ($(this).attr('href') === '#') {
            e.preventDefault();
          }
        });

        $(document).on('submit', 'form[data-confirm]', function (e) {
          if ($(this).data('confirmed')) {
            return true;
          }
          e.preventDefault();
          pendingConfirmForm = this;
          $('#stockoraConfirmTitle').text($(this).attr('data-confirm-title') || @json(__('app.confirm_action')));
          $('#stockoraConfirmMessage').text($(this).attr('data-confirm'));
          $('#stockoraConfirmSubmitBtn').text($(this).attr('data-confirm-btn') || @json(__('app.confirm')))
            .removeClass('btn-danger btn-primary btn-brand-01 btn-warning btn-success')
            .addClass($(this).attr('data-confirm-btn-class') || 'btn-danger');
          $('#stockoraConfirmModal').modal('show');
        });

        $('#stockoraConfirmSubmitBtn').on('click', function () {
          if (pendingConfirmForm) {
            $(pendingConfirmForm).data('confirmed', true).trigger('submit');
            $('#stockoraConfirmModal').modal('hide');
          }
        });

        $(document).on('submit', 'form[method="POST"], form[method="post"]', function (e) {
          var $form = $(this);
          if ($form.is('[data-confirm]') && !$form.data('confirmed')) {
            return;
          }
          if ($form.data('stkSubmitting')) {
            e.preventDefault();
            return false;
          }
          $form.data('stkSubmitting', true);

          var $btn = $form.find('button[type="submit"]:focus, button[type="submit"].btn-brand-01, button[type="submit"].btn-primary').first();
          if ($btn.length && !$btn.data('no-loading')) {
            $btn.addClass('stk-btn-loading');
          }
          setTimeout(function () {
            $form.find('button[type="submit"]:not([data-no-disable]), input[type="submit"]:not([data-no-disable])').prop('disabled', true);
          }, 0);
        });

        $(window).on('pageshow', function () {
          $('form').each(function () {
            $(this).removeData('stkSubmitting');
            $(this).find('button[type="submit"], input[type="submit"]').prop('disabled', false).removeClass('stk-btn-loading');
          });
        });
      });
    </script>
    @stack('scripts')
  </body>
</html>
