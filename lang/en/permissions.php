<?php

return [
    'roles' => [
        'owner' => [
            'label' => 'Owner',
            'description' => 'Full workspace authority including billing tier lifecycle, team roles, and ownership transfer.',
        ],
        'admin' => [
            'label' => 'Admin',
            'description' => 'Broad administrative access across operations, reports, and team management (except owner governance).',
        ],
        'manager' => [
            'label' => 'Manager',
            'description' => 'Operational lead overseeing inventory approvals, purchasing workflows, and stock audits.',
        ],
        'staff' => [
            'label' => 'Staff',
            'description' => 'Day-to-day warehouse execution across receipts, transfers, issues, and draft adjustments.',
        ],
        'viewer' => [
            'label' => 'Viewer',
            'description' => 'Read-only visibility across dashboards, stock levels, and inventory reports.',
        ],
    ],

    'modules' => [
        'dashboard' => 'Dashboard',
        'products' => 'Products',
        'categories' => 'Categories',
        'units' => 'Units & Conversions',
        'warehouses' => 'Warehouses',
        'warehouse_stock' => 'Warehouse Stock & Audits',
        'stock_movements' => 'Stock Movements',
        'stock_adjustments' => 'Stock Adjustments',
        'suppliers' => 'Suppliers',
        'purchase_orders' => 'Purchase Orders',
        'grn' => 'Goods Receiving (GRN)',
        'stock_transfers' => 'Stock Transfers',
        'stock_issues' => 'Stock Issues',
        'inventory_reports' => 'Inventory Reports',
        'team_management' => 'Team Management',
        'company_settings' => 'Company Settings',
    ],

    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'approve' => 'Approve / Post / Reverse',
        'export' => 'Export',
        'manage' => 'Manage / Configure',
    ],
];
