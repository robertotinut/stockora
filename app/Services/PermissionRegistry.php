<?php

namespace App\Services;

class PermissionRegistry
{
    public const ROLE_OWNER = 'owner';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_STAFF = 'staff';
    public const ROLE_VIEWER = 'viewer';

    public const ACTIONS = [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'approve' => 'Approve / Post / Reverse',
        'export' => 'Export',
        'manage' => 'Manage Settings / Permissions',
    ];

    public const ROLES = [
        self::ROLE_OWNER => [
            'label' => 'Owner',
            'description' => 'Full company ownership and unrestricted control over every module, billing/tenant settings, and ownership transfer.',
            'badge' => 'primary',
            'assignable' => false,
            'editable_permissions' => false,
        ],
        self::ROLE_ADMIN => [
            'label' => 'Admin',
            'description' => 'Broad company administration and team management without transferring ownership or modifying owner privileges.',
            'badge' => 'info',
            'assignable' => true,
            'editable_permissions' => true,
        ],
        self::ROLE_MANAGER => [
            'label' => 'Manager',
            'description' => 'Operational oversight across purchasing, warehouses, inventory posting, approvals, and report exports.',
            'badge' => 'success',
            'assignable' => true,
            'editable_permissions' => true,
        ],
        self::ROLE_STAFF => [
            'label' => 'Staff',
            'description' => 'Daily operational execution (drafts, PO creation, GRNs, transfers, issues) without team or company administration.',
            'badge' => 'warning',
            'assignable' => true,
            'editable_permissions' => true,
        ],
        self::ROLE_VIEWER => [
            'label' => 'Viewer',
            'description' => 'Read-only visibility into permitted inventory, purchasing, and operational dashboards.',
            'badge' => 'secondary',
            'assignable' => true,
            'editable_permissions' => true,
        ],
    ];

    public const MODULES = [
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'Overview',
            'actions' => ['view'],
        ],
        'products' => [
            'label' => 'Products',
            'group' => 'Master Data',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'categories' => [
            'label' => 'Categories',
            'group' => 'Master Data',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'units' => [
            'label' => 'Units',
            'group' => 'Master Data',
            'actions' => ['view', 'create', 'edit', 'delete', 'manage'],
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'group' => 'Purchasing',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'warehouses' => [
            'label' => 'Warehouses',
            'group' => 'Master Data',
            'actions' => ['view', 'create', 'edit', 'delete'],
        ],
        'warehouse_stock' => [
            'label' => 'Warehouse Stock',
            'group' => 'Inventory',
            'actions' => ['view', 'manage'],
        ],
        'purchase_orders' => [
            'label' => 'Purchase Orders',
            'group' => 'Purchasing',
            'actions' => ['view', 'create', 'edit', 'delete', 'approve'],
        ],
        'grn' => [
            'label' => 'Goods Receiving (GRN)',
            'group' => 'Purchasing',
            'actions' => ['view', 'create', 'approve'],
        ],
        'stock_transfers' => [
            'label' => 'Stock Transfers',
            'group' => 'Operations',
            'actions' => ['view', 'create', 'approve'],
        ],
        'stock_issues' => [
            'label' => 'Stock Issues',
            'group' => 'Operations',
            'actions' => ['view', 'create', 'approve'],
        ],
        'stock_adjustments' => [
            'label' => 'Stock Adjustments',
            'group' => 'Operations',
            'actions' => ['view', 'create', 'edit', 'approve'],
        ],
        'stock_movements' => [
            'label' => 'Stock Movements',
            'group' => 'Inventory',
            'actions' => ['view'],
        ],
        'inventory_reports' => [
            'label' => 'Inventory Reports',
            'group' => 'Reports',
            'actions' => ['view', 'export'],
        ],
        'team_management' => [
            'label' => 'Team Management',
            'group' => 'Settings',
            'actions' => ['view', 'create', 'edit', 'delete', 'manage'],
        ],
        'company_settings' => [
            'label' => 'Company Settings',
            'group' => 'Settings',
            'actions' => ['view', 'edit', 'manage'],
        ],
    ];

    public static function roles(): array
    {
        return array_keys(self::ROLES);
    }

    public static function assignableRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_STAFF,
            self::ROLE_VIEWER,
        ];
    }

    public static function editablePermissionRoles(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_MANAGER,
            self::ROLE_STAFF,
            self::ROLE_VIEWER,
        ];
    }

    public static function isValidRole(string $role): bool
    {
        return array_key_exists(strtolower($role), self::ROLES);
    }

    public static function isValidPermission(string $module, string $action): bool
    {
        return isset(self::MODULES[$module]) && in_array($action, self::MODULES[$module]['actions'], true);
    }

    public static function defaultPermissionsForRole(string $role): array
    {
        $role = strtolower($role);

        if ($role === self::ROLE_OWNER) {
            $all = [];
            foreach (self::MODULES as $module => $meta) {
                $all[$module] = $meta['actions'];
            }

            return $all;
        }

        return match ($role) {
            self::ROLE_ADMIN => [
                'dashboard' => ['view'],
                'products' => ['view', 'create', 'edit', 'delete'],
                'categories' => ['view', 'create', 'edit', 'delete'],
                'units' => ['view', 'create', 'edit', 'delete', 'manage'],
                'suppliers' => ['view', 'create', 'edit', 'delete'],
                'warehouses' => ['view', 'create', 'edit', 'delete'],
                'warehouse_stock' => ['view', 'manage'],
                'purchase_orders' => ['view', 'create', 'edit', 'delete', 'approve'],
                'grn' => ['view', 'create', 'approve'],
                'stock_transfers' => ['view', 'create', 'approve'],
                'stock_issues' => ['view', 'create', 'approve'],
                'stock_adjustments' => ['view', 'create', 'edit', 'approve'],
                'stock_movements' => ['view'],
                'inventory_reports' => ['view', 'export'],
                'team_management' => ['view', 'create', 'edit', 'delete', 'manage'],
                'company_settings' => ['view', 'edit', 'manage'],
            ],
            self::ROLE_MANAGER => [
                'dashboard' => ['view'],
                'products' => ['view', 'create', 'edit'],
                'categories' => ['view', 'create', 'edit'],
                'units' => ['view', 'create', 'edit', 'manage'],
                'suppliers' => ['view', 'create', 'edit'],
                'warehouses' => ['view', 'create', 'edit'],
                'warehouse_stock' => ['view', 'manage'],
                'purchase_orders' => ['view', 'create', 'edit', 'approve'],
                'grn' => ['view', 'create', 'approve'],
                'stock_transfers' => ['view', 'create', 'approve'],
                'stock_issues' => ['view', 'create', 'approve'],
                'stock_adjustments' => ['view', 'create', 'edit', 'approve'],
                'stock_movements' => ['view'],
                'inventory_reports' => ['view', 'export'],
                'team_management' => [],
                'company_settings' => [],
            ],
            self::ROLE_STAFF => [
                'dashboard' => ['view'],
                'products' => ['view'],
                'categories' => ['view'],
                'units' => ['view'],
                'suppliers' => ['view'],
                'warehouses' => ['view'],
                'warehouse_stock' => ['view'],
                'purchase_orders' => ['view', 'create'],
                'grn' => ['view', 'create'],
                'stock_transfers' => ['view', 'create'],
                'stock_issues' => ['view', 'create'],
                'stock_adjustments' => ['view', 'create'],
                'stock_movements' => ['view'],
                'inventory_reports' => ['view'],
                'team_management' => [],
                'company_settings' => [],
            ],
            self::ROLE_VIEWER => [
                'dashboard' => ['view'],
                'products' => ['view'],
                'categories' => ['view'],
                'units' => ['view'],
                'suppliers' => ['view'],
                'warehouses' => ['view'],
                'warehouse_stock' => ['view'],
                'purchase_orders' => ['view'],
                'grn' => ['view'],
                'stock_transfers' => ['view'],
                'stock_issues' => ['view'],
                'stock_adjustments' => ['view'],
                'stock_movements' => ['view'],
                'inventory_reports' => ['view'],
                'team_management' => [],
                'company_settings' => [],
            ],
            default => [],
        };
    }

    public static function defaultAllows(string $role, string $module, string $action): bool
    {
        if (!self::isValidPermission($module, $action)) {
            return false;
        }

        $defaults = self::defaultPermissionsForRole($role);

        return in_array($action, $defaults[$module] ?? [], true);
    }
}
