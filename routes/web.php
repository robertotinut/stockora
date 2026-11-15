<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PasswordSetupController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CustomerSubscriptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrnController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanComparisonController;
use App\Http\Controllers\Platform\PlatformAuditController;
use App\Http\Controllers\Platform\PlatformAuthController;
use App\Http\Controllers\Platform\PlatformCustomerController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformFeatureController;
use App\Http\Controllers\Platform\PlatformPaymentController;
use App\Http\Controllers\Platform\PlatformPaymentInstructionController;
use App\Http\Controllers\Platform\PlatformPlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockIssueController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamPermissionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

$registerCrudModule = function (string $prefix, string $controller, string $permModule, string $param, bool $hasShow = true, ?string $planFeature = null): void {
    $withPlan = fn (string $perm): array => $planFeature !== null ? ["feature:{$planFeature}", $perm] : [$perm];

    Route::get("/{$prefix}", [$controller, 'index'])->name("{$prefix}.index")->middleware($withPlan("perm:{$permModule},view"));
    Route::get("/{$prefix}/create", [$controller, 'create'])->name("{$prefix}.create")->middleware($withPlan("perm:{$permModule},create"));
    Route::post("/{$prefix}", [$controller, 'store'])->name("{$prefix}.store")->middleware($withPlan("perm:{$permModule},create"));
    if ($hasShow) {
        Route::get("/{$prefix}/{{$param}}", [$controller, 'show'])->name("{$prefix}.show")->middleware($withPlan("perm:{$permModule},view"));
    }
    Route::get("/{$prefix}/{{$param}}/edit", [$controller, 'edit'])->name("{$prefix}.edit")->middleware($withPlan("perm:{$permModule},edit"));
    Route::match(['put', 'patch'], "/{$prefix}/{{$param}}", [$controller, 'update'])->name("{$prefix}.update")->middleware($withPlan("perm:{$permModule},edit"));
    Route::delete("/{$prefix}/{{$param}}", [$controller, 'destroy'])->name("{$prefix}.destroy")->middleware($withPlan("perm:{$permModule},delete"));
};

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public / Tenant / Guest Language Switcher
Route::post('/locale', [LocaleController::class, 'updateTenantOrGuest'])->name('locale.update');

// Public / Reusable Subscription Plan Comparison Page (reads directly from DB)
Route::get('/plans', [PlanComparisonController::class, 'index'])->name('plans.index');

// Guest Authentication, Password Recovery & Self-Service Registration Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:tenant-login');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/forgot-password', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:password-reset-request')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:password-reset-submit')
        ->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Single-use account password setup link for platform-provisioned owners
Route::get('/setup-password/{token}', [PasswordSetupController::class, 'show'])
    ->middleware('throttle:password-setup-view')
    ->name('password.setup');
Route::post('/setup-password', [PasswordSetupController::class, 'store'])
    ->middleware('throttle:password-setup')
    ->name('password.setup.store');

// Public Token-Gated Company Invitation Landing + Explicit Acceptance
Route::get('/invitations/{token}', [InvitationAcceptController::class, 'show'])
    ->middleware('throttle:invitation-view')
    ->name('invitations.show');
Route::post('/invitations/{token}/accept', [InvitationAcceptController::class, 'accept'])
    ->middleware(['auth', 'throttle:invitation-accept'])
    ->name('invitations.accept');

// Self-Service Onboarding, Workspace Access Status & Company Selection (Authenticated)
Route::middleware('auth')->group(function () {
    Route::get('/onboarding/company', [OnboardingController::class, 'createCompany'])->name('onboarding.company.create');
    Route::post('/onboarding/company', [OnboardingController::class, 'storeCompany'])->name('onboarding.company.store');
    Route::get('/onboarding/ready', [OnboardingController::class, 'ready'])->name('onboarding.ready');

    Route::get('/company/access-status', [CompanyController::class, 'accessStatus'])->name('company.access-status');
    Route::get('/company/select', [CompanyController::class, 'select'])->name('company.select');
    Route::post('/company/switch', [CompanyController::class, 'switchCompany'])->name('company.switch');

    // Customer Subscription Overview, Manual Payment Confirmations & Renewals
    Route::get('/subscription', [CustomerSubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('/subscription/payments/create', [CustomerSubscriptionController::class, 'createPayment'])->name('subscription.payments.create');
    Route::post('/subscription/payments', [CustomerSubscriptionController::class, 'storePayment'])
        ->middleware('throttle:subscription-payment-submit')
        ->name('subscription.payments.store');
    Route::get('/subscription/payments/{paymentId}', [CustomerSubscriptionController::class, 'showPayment'])->whereNumber('paymentId')->name('subscription.payments.show');
    Route::get('/subscription/payments/{paymentId}/proof', [CustomerSubscriptionController::class, 'downloadProof'])->whereNumber('paymentId')->name('subscription.payments.proof');
    Route::post('/subscription/payments/{paymentId}/cancel', [CustomerSubscriptionController::class, 'cancelPayment'])
        ->whereNumber('paymentId')
        ->middleware('throttle:customer-payment-action')
        ->name('subscription.payments.cancel');
});

// Separate Stockora Platform Super Admin Control Plane (/platform/*)
Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('/', fn () => redirect()->route('platform.dashboard'))->name('home');
    Route::post('/locale', [LocaleController::class, 'updatePlatform'])->name('locale.update');
    Route::get('/login', [PlatformAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PlatformAuthController::class, 'login'])
        ->middleware('throttle:platform-login')
        ->name('login.submit');

    Route::middleware('platform.admin')->group(function () {
        Route::post('/logout', [PlatformAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [PlatformDashboardController::class, 'index'])->name('dashboard');

        Route::get('/pending', [PlatformCustomerController::class, 'pending'])->name('pending.index');
        Route::get('/access', [PlatformCustomerController::class, 'accessIndex'])->name('access.index');

        Route::get('/customers', [PlatformCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [PlatformCustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [PlatformCustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{company}', [PlatformCustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{company}', [PlatformCustomerController::class, 'updateProfile'])->name('customers.update');

        Route::post('/customers/{company}/activate', [PlatformCustomerController::class, 'activate'])->name('customers.activate');
        Route::post('/customers/{company}/suspend', [PlatformCustomerController::class, 'suspend'])->name('customers.suspend');
        Route::post('/customers/{company}/reactivate', [PlatformCustomerController::class, 'reactivate'])->name('customers.reactivate');
        Route::post('/customers/{company}/expire', [PlatformCustomerController::class, 'expire'])->name('customers.expire');
        Route::post('/customers/{company}/cancel', [PlatformCustomerController::class, 'cancel'])->name('customers.cancel');
        Route::post('/customers/{company}/extend', [PlatformCustomerController::class, 'extendExpiration'])->name('customers.extend');
        Route::post('/customers/{company}/access-type', [PlatformCustomerController::class, 'changeAccessType'])->name('customers.access-type');
        Route::post('/customers/{company}/assign-plan', [PlatformCustomerController::class, 'assignPlan'])->name('customers.assign-plan');

        // Subscription Plans
        Route::get('/plans', [PlatformPlanController::class, 'index'])->name('plans.index');
        Route::get('/plans/create', [PlatformPlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlatformPlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}', [PlatformPlanController::class, 'show'])->name('plans.show');
        Route::get('/plans/{plan}/edit', [PlatformPlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [PlatformPlanController::class, 'update'])->name('plans.update');
        Route::post('/plans/{plan}/activate', [PlatformPlanController::class, 'activate'])->name('plans.activate');
        Route::post('/plans/{plan}/deactivate', [PlatformPlanController::class, 'deactivate'])->name('plans.deactivate');
        Route::delete('/plans/{plan}', [PlatformPlanController::class, 'destroy'])->name('plans.destroy');

        // Feature Catalog
        Route::get('/features', [PlatformFeatureController::class, 'index'])->name('features.index');
        Route::post('/features', [PlatformFeatureController::class, 'store'])->name('features.store');
        Route::put('/features/{feature}', [PlatformFeatureController::class, 'update'])->name('features.update');
        Route::post('/features/{feature}/activate', [PlatformFeatureController::class, 'activate'])->name('features.activate');
        Route::post('/features/{feature}/deactivate', [PlatformFeatureController::class, 'deactivate'])->name('features.deactivate');
        Route::delete('/features/{feature}', [PlatformFeatureController::class, 'destroy'])->name('features.destroy');

        // Manual Payments & Renewal Governance
        Route::get('/payments', [PlatformPaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/create', [PlatformPaymentController::class, 'create'])->name('payments.create');
        Route::middleware('throttle:platform-payment-actions')->group(function () {
            Route::post('/payments', [PlatformPaymentController::class, 'store'])->name('payments.store');
            Route::post('/payments/{payment}/verify', [PlatformPaymentController::class, 'verify'])->whereNumber('payment')->name('payments.verify');
            Route::post('/payments/{payment}/reject', [PlatformPaymentController::class, 'reject'])->whereNumber('payment')->name('payments.reject');
            Route::post('/payments/{payment}/cancel', [PlatformPaymentController::class, 'cancel'])->whereNumber('payment')->name('payments.cancel');
            Route::post('/payments/{payment}/apply', [PlatformPaymentController::class, 'applyToSubscription'])->whereNumber('payment')->name('payments.apply');
        });
        Route::get('/payments/{payment}', [PlatformPaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');
        Route::get('/payments/{payment}/proof', [PlatformPaymentController::class, 'downloadProof'])->whereNumber('payment')->name('payments.proof');

        Route::get('/logs', [PlatformAuditController::class, 'index'])->name('logs.index');
        Route::get('/settings', [PlatformAuditController::class, 'settings'])->name('settings.index');
        Route::post('/settings/billing', [PlatformPaymentInstructionController::class, 'updateBillingSettings'])->name('settings.billing.update');
        Route::post('/payment-instructions', [PlatformPaymentInstructionController::class, 'store'])->name('payment-instructions.store');
        Route::put('/payment-instructions/{instruction}', [PlatformPaymentInstructionController::class, 'update'])->name('payment-instructions.update');
        Route::post('/payment-instructions/{instruction}/toggle', [PlatformPaymentInstructionController::class, 'toggle'])->name('payment-instructions.toggle');
    });
});

// Protected Tenant-Scoped Routes with Backend RBAC Enforcement
Route::middleware(['auth', 'tenant'])->group(function () use ($registerCrudModule) {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('perm:dashboard,view');

    // Company Settings
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index')->middleware('perm:company_settings,view');
    Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create')->middleware('perm:company_settings,manage');
    Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store')->middleware('perm:company_settings,manage');
    Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit')->middleware('perm:company_settings,edit');
    Route::match(['put', 'patch'], '/companies/{company}', [CompanyController::class, 'update'])->name('companies.update')->middleware('perm:company_settings,edit');

    // Team Management, Invitations, Permissions & Ownership Transfer
    Route::middleware('feature:module_team_management')->group(function () {
        Route::get('/team', [TeamController::class, 'index'])->name('team.index')->middleware('perm:team_management,view');
        Route::post('/team/transfer-ownership', [TeamController::class, 'transferOwnership'])->name('team.transfer-ownership')->middleware('perm:team_management,edit');
        Route::get('/team/{member}', [TeamController::class, 'show'])->whereNumber('member')->name('team.show')->middleware('perm:team_management,view');
        Route::patch('/team/{member}/role', [TeamController::class, 'updateRole'])->whereNumber('member')->name('team.members.role')->middleware('perm:team_management,edit');
        Route::patch('/team/{member}/status', [TeamController::class, 'updateStatus'])->whereNumber('member')->name('team.members.status')->middleware('perm:team_management,edit');
        Route::delete('/team/{member}', [TeamController::class, 'destroy'])->whereNumber('member')->name('team.members.destroy')->middleware('perm:team_management,delete');

        Route::middleware('feature:module_role_permissions')->group(function () {
            Route::get('/team/permissions', [TeamPermissionController::class, 'index'])->name('team.permissions.index')->middleware('perm:team_management,manage');
            Route::put('/team/permissions/{role}', [TeamPermissionController::class, 'update'])->name('team.permissions.update')->middleware('perm:team_management,manage');
            Route::post('/team/permissions/{role}/reset', [TeamPermissionController::class, 'reset'])->name('team.permissions.reset')->middleware('perm:team_management,manage');
        });
    });

    Route::middleware(['feature:module_team_management', 'feature:module_team_invitations'])->group(function () {
        Route::get('/team/invitations', [TeamInvitationController::class, 'index'])->name('team.invitations.index')->middleware('perm:team_management,view');
        Route::post('/team/invitations', [TeamInvitationController::class, 'store'])->name('team.invitations.store')->middleware('perm:team_management,create');
        Route::post('/team/invitations/{invitation}/resend', [TeamInvitationController::class, 'resend'])->name('team.invitations.resend')->middleware('perm:team_management,create');
        Route::delete('/team/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('team.invitations.destroy')->middleware('perm:team_management,delete');
    });

    // Master Data
    $registerCrudModule('categories', CategoryController::class, 'categories', 'category', false, 'module_products');
    $registerCrudModule('units', UnitController::class, 'units', 'unit', false, 'module_products');
    Route::post('/unit-conversions', [UnitController::class, 'storeConversion'])->name('unit-conversions.store')->middleware(['feature:module_unit_conversions', 'perm:units,manage']);
    Route::delete('/unit-conversions/{unitConversion}', [UnitController::class, 'destroyConversion'])->name('unit-conversions.destroy')->middleware(['feature:module_unit_conversions', 'perm:units,manage']);
    $registerCrudModule('suppliers', SupplierController::class, 'suppliers', 'supplier', true, 'module_suppliers');
    $registerCrudModule('products', ProductController::class, 'products', 'product', true, 'module_products');
    $registerCrudModule('warehouses', WarehouseController::class, 'warehouses', 'warehouse', true, 'module_warehouses');

    // Warehouse Stock Overview & Reconciliation Audits
    Route::get('/inventory/warehouses', [WarehouseController::class, 'stockOverview'])->name('inventory.warehouses.index')->middleware(['feature:module_stock_monitoring', 'perm:warehouse_stock,view']);
    Route::get('/reconciliation-audits', [WarehouseController::class, 'reconciliationAudits'])->name('reconciliation-audits.index')->middleware(['feature:module_reconciliation_audits', 'perm:warehouse_stock,manage']);

    // Purchasing & Web GRN
    $registerCrudModule('purchases', PurchaseController::class, 'purchase_orders', 'purchase', true, 'module_purchase_orders');
    Route::get('/purchases/{purchase}/print', [PurchaseController::class, 'print'])->name('purchases.print')->middleware(['feature:module_purchase_orders', 'perm:purchase_orders,view']);
    Route::post('/purchases/{purchase}/confirm', [PurchaseController::class, 'confirm'])->name('purchases.confirm')->middleware(['feature:module_purchase_orders', 'perm:purchase_orders,approve']);
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel')->middleware(['feature:module_purchase_orders', 'perm:purchase_orders,approve']);
    Route::patch('/purchases/{purchase}/payment', [PurchaseController::class, 'updatePayment'])->name('purchases.update-payment')->middleware(['feature:module_purchase_orders', 'perm:purchase_orders,approve']);

    Route::middleware('feature:module_grn')->group(function () {
        Route::get('/grns', [GrnController::class, 'index'])->name('grns.index')->middleware('perm:grn,view');
        Route::get('/grns/create', [GrnController::class, 'create'])->name('grns.create')->middleware('perm:grn,create');
        Route::post('/grns', [GrnController::class, 'store'])->name('grns.store')->middleware('perm:grn,create');
        Route::get('/grns/{grn}', [GrnController::class, 'show'])->name('grns.show')->middleware('perm:grn,view');
        Route::get('/grns/{grn}/print', [GrnController::class, 'print'])->name('grns.print')->middleware('perm:grn,view');
        Route::post('/grns/{grn}/reverse', [GrnController::class, 'reverse'])->name('grns.reverse')->middleware('perm:grn,approve');
    });

    // Stock Transfers, Stock Issues, and Stock Adjustments
    Route::middleware('feature:module_stock_transfers')->group(function () {
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index')->middleware('perm:stock_transfers,view');
        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create')->middleware('perm:stock_transfers,create');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store')->middleware('perm:stock_transfers,create');
        Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show')->middleware('perm:stock_transfers,view');
        Route::get('/transfers/{transfer}/print', [TransferController::class, 'print'])->name('transfers.print')->middleware('perm:stock_transfers,view');
        Route::post('/transfers/{transfer}/reverse', [TransferController::class, 'reverse'])->name('transfers.reverse')->middleware('perm:stock_transfers,approve');
    });

    Route::middleware('feature:module_stock_issues')->group(function () {
        Route::get('/issues', [StockIssueController::class, 'index'])->name('issues.index')->middleware('perm:stock_issues,view');
        Route::get('/issues/create', [StockIssueController::class, 'create'])->name('issues.create')->middleware('perm:stock_issues,create');
        Route::post('/issues', [StockIssueController::class, 'store'])->name('issues.store')->middleware('perm:stock_issues,create');
        Route::get('/issues/{issue}', [StockIssueController::class, 'show'])->name('issues.show')->middleware('perm:stock_issues,view');
        Route::get('/issues/{issue}/print', [StockIssueController::class, 'print'])->name('issues.print')->middleware('perm:stock_issues,view');
        Route::post('/issues/{issue}/reverse', [StockIssueController::class, 'reverse'])->name('issues.reverse')->middleware('perm:stock_issues,approve');
    });

    Route::middleware('feature:module_stock_adjustments')->group(function () {
        Route::get('/adjustments', [StockAdjustmentController::class, 'index'])->name('adjustments.index')->middleware('perm:stock_adjustments,view');
        Route::get('/adjustments/create', [StockAdjustmentController::class, 'create'])->name('adjustments.create')->middleware('perm:stock_adjustments,create');
        Route::post('/adjustments', [StockAdjustmentController::class, 'store'])->name('adjustments.store')->middleware('perm:stock_adjustments,create');
        Route::get('/adjustments/{adjustment}', [StockAdjustmentController::class, 'show'])->name('adjustments.show')->middleware('perm:stock_adjustments,view');
        Route::get('/adjustments/{adjustment}/print', [StockAdjustmentController::class, 'print'])->name('adjustments.print')->middleware('perm:stock_adjustments,view');
        Route::get('/adjustments/{adjustment}/edit', [StockAdjustmentController::class, 'edit'])->name('adjustments.edit')->middleware('perm:stock_adjustments,edit');
        Route::put('/adjustments/{adjustment}', [StockAdjustmentController::class, 'update'])->name('adjustments.update')->middleware('perm:stock_adjustments,edit');
        Route::post('/adjustments/{adjustment}/post', [StockAdjustmentController::class, 'postDraft'])->name('adjustments.post')->middleware('perm:stock_adjustments,approve');
        Route::post('/adjustments/{adjustment}/reverse', [StockAdjustmentController::class, 'reverse'])->name('adjustments.reverse')->middleware('perm:stock_adjustments,approve');
    });

    // Movement History & Inventory Reports
    Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index')->middleware(['feature:module_stock_movements', 'perm:stock_movements,view']);
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware(['feature:module_inventory_reports', 'perm:inventory_reports,view']);
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware(['feature:module_inventory_reports', 'feature:module_report_exports', 'perm:inventory_reports,export']);

    // Transactional Inventory JSON API Endpoints
    Route::post('/inventory/grn', [InventoryController::class, 'storeGrn'])->name('inventory.grn.store')->middleware(['feature:module_grn', 'perm:grn,create']);
    Route::post('/inventory/transfers', [InventoryController::class, 'storeTransfer'])->name('inventory.transfers.store')->middleware(['feature:module_stock_transfers', 'perm:stock_transfers,create']);
    Route::post('/inventory/issues', [InventoryController::class, 'storeIssue'])->name('inventory.issues.store')->middleware(['feature:module_stock_issues', 'perm:stock_issues,create']);
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store')->middleware(['feature:module_stock_adjustments', 'perm:stock_adjustments,approve']);
    Route::post('/inventory/reversals', [InventoryController::class, 'storeReversal'])->name('inventory.reversals.store');
});
