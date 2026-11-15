<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;

/**
 * Centralized catalog definition for Stockora Round 21 subscription features and limits.
 *
 * Runtime plans, feature toggles, and limits are persisted in the database
 * (`subscription_plans`, `subscription_features`, `subscription_plan_features`).
 * This class provides seed definitions plus explicit documentation of how
 * each implemented resource limit will be counted in Round 22.
 */
class SubscriptionCatalog
{
    /**
     * Explicit inventory & purchasing modules verified in the current codebase.
     * Sales / tenant-customer / storage modules are omitted from active defaults
     * because those tables and controllers do not yet exist in Stockora.
     */
    public const FEATURES = [
        // Inventory Management
        [
            'feature_key' => 'module_products',
            'name' => 'Product Catalog Management',
            'description' => 'Manage SKUs, categories, purchase/selling reference prices, and minimum stock alerts (/products).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 10,
        ],
        [
            'feature_key' => 'module_warehouses',
            'name' => 'Warehouse Management',
            'description' => 'Create and maintain company warehouse sites and default receiving locations (/warehouses).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 20,
        ],
        [
            'feature_key' => 'module_stock_monitoring',
            'name' => 'Stock Monitoring & Valuation',
            'description' => 'Monitor per-warehouse on-hand balances, moving average cost, and valuation basis (/inventory/warehouses).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 30,
        ],
        [
            'feature_key' => 'module_stock_adjustments',
            'name' => 'Stock Adjustments',
            'description' => 'Draft, post, and reverse controlled stock adjustment documents (/adjustments).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 40,
        ],
        [
            'feature_key' => 'module_stock_transfers',
            'name' => 'Inter-Warehouse Stock Transfers',
            'description' => 'Transfer stock across company warehouses with atomic ledger and cost carry-forward (/transfers).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '0',
            'sort_order' => 50,
        ],
        [
            'feature_key' => 'module_stock_issues',
            'name' => 'Stock Issues (Outgoing Usage)',
            'description' => 'Record internal consumption and operational stock issues per warehouse (/issues).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '0',
            'sort_order' => 60,
        ],
        [
            'feature_key' => 'module_stock_movements',
            'name' => 'Stock Movement History Ledger',
            'description' => 'Inspect append-only SKU movement ledger and running balance/valuation snapshots (/stock-movements).',
            'category' => 'Inventory Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 70,
        ],

        // Purchasing
        [
            'feature_key' => 'module_suppliers',
            'name' => 'Supplier Directory',
            'description' => 'Maintain supplier profiles, contacts, and purchasing counterparties (/suppliers).',
            'category' => 'Purchasing',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 80,
        ],
        [
            'feature_key' => 'module_purchase_orders',
            'name' => 'Purchase Orders',
            'description' => 'Create, confirm, track receiving progress, and manage payment status on purchase orders (/purchases).',
            'category' => 'Purchasing',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 90,
        ],
        [
            'feature_key' => 'module_grn',
            'name' => 'Goods Receiving (GRN)',
            'description' => 'Post partial/full goods receipts with freight/discount/tax capitalization and reversals (/grns).',
            'category' => 'Purchasing',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 100,
        ],

        // Reporting
        [
            'feature_key' => 'module_inventory_reports',
            'name' => 'Inventory & Valuation Reports',
            'description' => 'Analyze valuation summaries, movement trends, and stock exceptions across date ranges (/reports).',
            'category' => 'Reporting',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 110,
        ],
        [
            'feature_key' => 'module_report_exports',
            'name' => 'Report CSV Exports',
            'description' => 'Download CSV exports of inventory valuation, movement ledger, and exception summaries (/reports/export).',
            'category' => 'Reporting',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '0',
            'sort_order' => 120,
        ],
        [
            'feature_key' => 'module_reconciliation_audits',
            'name' => 'Legacy Reconciliation Audits',
            'description' => 'Review legacy stock reconciliation classifications and quarantined chain breaks (/reconciliation-audits).',
            'category' => 'Reporting',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '0',
            'sort_order' => 130,
        ],

        // Team & Access Management
        [
            'feature_key' => 'module_team_management',
            'name' => 'Team Member Management',
            'description' => 'Manage workspace members, roles, status toggles, and ownership transfer (/team).',
            'category' => 'Team & Access Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 140,
        ],
        [
            'feature_key' => 'module_role_permissions',
            'name' => 'Custom Role Permission Matrix',
            'description' => 'Customize module-level view/create/edit/delete/approve/export permissions per role (/team/permissions).',
            'category' => 'Team & Access Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '0',
            'sort_order' => 150,
        ],
        [
            'feature_key' => 'module_team_invitations',
            'name' => 'Team Email Invitations',
            'description' => 'Issue, resend, and revoke time-limited invitation links for new staff members (/team/invitations).',
            'category' => 'Team & Access Management',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '0',
            'sort_order' => 160,
        ],

        // Advanced Capabilities (verified implemented capabilities only)
        [
            'feature_key' => 'module_unit_conversions',
            'name' => 'Multi-Unit Conversions',
            'description' => 'Configure product-specific purchase-to-base unit conversion ratios (/units).',
            'category' => 'Advanced Capabilities',
            'feature_type' => SubscriptionFeature::TYPE_BOOLEAN,
            'default_value' => '1',
            'sort_order' => 170,
        ],
        [
            'feature_key' => 'capability_support_tier',
            'name' => 'Support & Onboarding SLA',
            'description' => 'Informational support level descriptor attached to the plan tier.',
            'category' => 'Advanced Capabilities',
            'feature_type' => SubscriptionFeature::TYPE_TEXT,
            'default_value' => 'Standard Documentation',
            'sort_order' => 180,
        ],

        // Resource Limits (null = Unlimited; counted only against verified company-scoped tables)
        [
            'feature_key' => 'max_users',
            'name' => 'Maximum Active Team Users',
            'description' => 'Active seats in company_user (is_active = 1, lifetime capacity). Null = Unlimited.',
            'category' => 'Resource Limits',
            'feature_type' => SubscriptionFeature::TYPE_LIMIT,
            'default_value' => '2',
            'sort_order' => 190,
        ],
        [
            'feature_key' => 'max_warehouses',
            'name' => 'Maximum Active Warehouses',
            'description' => 'Active sites in warehouses (is_active = 1, lifetime capacity). Null = Unlimited.',
            'category' => 'Resource Limits',
            'feature_type' => SubscriptionFeature::TYPE_LIMIT,
            'default_value' => '1',
            'sort_order' => 200,
        ],
        [
            'feature_key' => 'max_products',
            'name' => 'Maximum Catalog Products (SKUs)',
            'description' => 'Product records scoped by products.company_id (lifetime capacity). Null = Unlimited.',
            'category' => 'Resource Limits',
            'feature_type' => SubscriptionFeature::TYPE_LIMIT,
            'default_value' => '50',
            'sort_order' => 210,
        ],
        [
            'feature_key' => 'max_suppliers',
            'name' => 'Maximum Suppliers',
            'description' => 'Supplier records scoped by suppliers.company_id (lifetime capacity). Null = Unlimited.',
            'category' => 'Resource Limits',
            'feature_type' => SubscriptionFeature::TYPE_LIMIT,
            'default_value' => '10',
            'sort_order' => 220,
        ],
        [
            'feature_key' => 'max_monthly_purchases',
            'name' => 'Monthly Purchase Orders',
            'description' => 'Purchase orders created in purchases.company_id within current calendar month (created_at). Null = Unlimited.',
            'category' => 'Resource Limits',
            'feature_type' => SubscriptionFeature::TYPE_LIMIT,
            'default_value' => '25',
            'sort_order' => 230,
        ],
        [
            'feature_key' => 'max_monthly_stock_movements',
            'name' => 'Monthly Stock Ledger Entries',
            'description' => 'Stock movement ledger entries recorded in stock_movements.company_id per calendar month. Null = Unlimited.',
            'category' => 'Resource Limits',
            'feature_type' => SubscriptionFeature::TYPE_LIMIT,
            'default_value' => '200',
            'sort_order' => 240,
        ],
    ];

    /**
     * Measurable numerical quota limit feature keys enforced in Round 22.
     *
     * @var array<int, string>
     */
    public const LIMIT_KEYS = [
        'max_users',
        'max_warehouses',
        'max_products',
        'max_suppliers',
        'max_monthly_purchases',
        'max_monthly_stock_movements',
    ];

    /**
     * Explicit verification matrix documenting each limit's schema grounding for Round 22.
     */
    public const MEASURABLE_LIMIT_SPECS = [
        'max_users' => [
            'table' => 'company_user',
            'company_scoped' => true,
            'basis' => 'Lifetime (active seats: is_active = 1)',
            'ambiguity' => 'Excludes deactivated members (is_active = 0); Round 22 must decide whether pending invitations reserve seats.',
        ],
        'max_warehouses' => [
            'table' => 'warehouses',
            'company_scoped' => true,
            'basis' => 'Lifetime (active sites: is_active = 1)',
            'ambiguity' => 'Archived warehouses (is_active = 0) preserve historical movements and do not consume active site slots.',
        ],
        'max_products' => [
            'table' => 'products',
            'company_scoped' => true,
            'basis' => 'Lifetime (current SKU count)',
            'ambiguity' => 'No soft-deletes exist on products; total rows equal active SKUs.',
        ],
        'max_suppliers' => [
            'table' => 'suppliers',
            'company_scoped' => true,
            'basis' => 'Lifetime (current supplier count)',
            'ambiguity' => 'No active/inactive flag or soft-delete exists on suppliers.',
        ],
        'max_monthly_purchases' => [
            'table' => 'purchases',
            'company_scoped' => true,
            'basis' => 'Monthly (calendar month by created_at)',
            'ambiguity' => 'Counting by created_at avoids bypass via backdated date column; Round 22 should decide cancelled-PO treatment.',
        ],
        'max_monthly_stock_movements' => [
            'table' => 'stock_movements',
            'company_scoped' => true,
            'basis' => 'Monthly (calendar month by created_at)',
            'ambiguity' => 'Measures append-only ledger rows across GRNs, transfers, issues, and adjustments rather than document header count.',
        ],
    ];

    /**
     * Proposed limits explicitly omitted as active DB quotas because underlying modules do not exist yet.
     */
    public const UNIMPLEMENTED_LIMIT_NOTES = [
        'max_customers' => 'Not measurable yet: tenant customers CRM table does not exist (companies represents SaaS subscribers).',
        'max_monthly_sales_documents' => 'Not measurable yet: no sales, sales_orders, or invoices tables or controllers exist.',
        'max_storage_mb' => 'Not measurable yet: only single optional companies.logo_path exists; no per-tenant attachment storage metering exists.',
    ];

    /**
     * Provisional draft plan presets (explicitly informational; editable by Platform Super Admin).
     */
    public static function provisionalPlans(): array
    {
        return [
            [
                'code' => Company::ACCESS_FREE,
                'tier' => Company::ACCESS_FREE,
                'name' => 'Free',
                'description' => '[Provisional Draft] Entry-level single-warehouse evaluation tier with manual onboarding.',
                'price' => 0.00,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'is_active' => true,
                'is_recommended' => false,
                'sort_order' => 1,
                'features' => [
                    'module_products' => '1',
                    'module_warehouses' => '1',
                    'module_stock_monitoring' => '1',
                    'module_stock_adjustments' => '1',
                    'module_stock_transfers' => '0',
                    'module_stock_issues' => '0',
                    'module_stock_movements' => '1',
                    'module_suppliers' => '1',
                    'module_purchase_orders' => '1',
                    'module_grn' => '1',
                    'module_inventory_reports' => '1',
                    'module_report_exports' => '0',
                    'module_reconciliation_audits' => '0',
                    'module_team_management' => '1',
                    'module_role_permissions' => '0',
                    'module_team_invitations' => '0',
                    'module_unit_conversions' => '1',
                    'capability_support_tier' => 'Self-Service Docs (Provisional)',
                    'max_users' => '2',
                    'max_warehouses' => '1',
                    'max_products' => '50',
                    'max_suppliers' => '10',
                    'max_monthly_purchases' => '25',
                    'max_monthly_stock_movements' => '200',
                ],
            ],
            [
                'code' => Company::ACCESS_STARTER,
                'tier' => Company::ACCESS_STARTER,
                'name' => 'Starter',
                'description' => '[Provisional Draft] Small distributor and warehouse tier with transfers, stock issues, and team invites.',
                'price' => 299000.00,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'is_active' => true,
                'is_recommended' => false,
                'sort_order' => 2,
                'features' => [
                    'module_products' => '1',
                    'module_warehouses' => '1',
                    'module_stock_monitoring' => '1',
                    'module_stock_adjustments' => '1',
                    'module_stock_transfers' => '1',
                    'module_stock_issues' => '1',
                    'module_stock_movements' => '1',
                    'module_suppliers' => '1',
                    'module_purchase_orders' => '1',
                    'module_grn' => '1',
                    'module_inventory_reports' => '1',
                    'module_report_exports' => '0',
                    'module_reconciliation_audits' => '0',
                    'module_team_management' => '1',
                    'module_role_permissions' => '0',
                    'module_team_invitations' => '1',
                    'module_unit_conversions' => '1',
                    'capability_support_tier' => 'Business Email Support (Provisional)',
                    'max_users' => '5',
                    'max_warehouses' => '2',
                    'max_products' => '500',
                    'max_suppliers' => '50',
                    'max_monthly_purchases' => '150',
                    'max_monthly_stock_movements' => '2000',
                ],
            ],
            [
                'code' => Company::ACCESS_PROFESSIONAL,
                'tier' => Company::ACCESS_PROFESSIONAL,
                'name' => 'Professional',
                'description' => '[Provisional Draft] Recommended multi-warehouse operational suite with CSV exports, custom RBAC, and reconciliation audits.',
                'price' => 799000.00,
                'billing_interval' => SubscriptionPlan::INTERVAL_MONTHLY,
                'is_active' => true,
                'is_recommended' => true,
                'sort_order' => 3,
                'features' => [
                    'module_products' => '1',
                    'module_warehouses' => '1',
                    'module_stock_monitoring' => '1',
                    'module_stock_adjustments' => '1',
                    'module_stock_transfers' => '1',
                    'module_stock_issues' => '1',
                    'module_stock_movements' => '1',
                    'module_suppliers' => '1',
                    'module_purchase_orders' => '1',
                    'module_grn' => '1',
                    'module_inventory_reports' => '1',
                    'module_report_exports' => '1',
                    'module_reconciliation_audits' => '1',
                    'module_team_management' => '1',
                    'module_role_permissions' => '1',
                    'module_team_invitations' => '1',
                    'module_unit_conversions' => '1',
                    'capability_support_tier' => 'Priority Support & Onboarding (Provisional)',
                    'max_users' => '20',
                    'max_warehouses' => '10',
                    'max_products' => '5000',
                    'max_suppliers' => '250',
                    'max_monthly_purchases' => '1000',
                    'max_monthly_stock_movements' => '25000',
                ],
            ],
            [
                'code' => Company::ACCESS_ENTERPRISE,
                'tier' => Company::ACCESS_ENTERPRISE,
                'name' => 'Enterprise',
                'description' => '[Provisional Draft] High-scale multi-warehouse operations with unlimited quotas and custom commercial terms.',
                'price' => 1999000.00,
                'billing_interval' => SubscriptionPlan::INTERVAL_CUSTOM,
                'is_active' => true,
                'is_recommended' => false,
                'is_internal' => false,
                'sort_order' => 4,
                'features' => [
                    'module_products' => '1',
                    'module_warehouses' => '1',
                    'module_stock_monitoring' => '1',
                    'module_stock_adjustments' => '1',
                    'module_stock_transfers' => '1',
                    'module_stock_issues' => '1',
                    'module_stock_movements' => '1',
                    'module_suppliers' => '1',
                    'module_purchase_orders' => '1',
                    'module_grn' => '1',
                    'module_inventory_reports' => '1',
                    'module_report_exports' => '1',
                    'module_reconciliation_audits' => '1',
                    'module_team_management' => '1',
                    'module_role_permissions' => '1',
                    'module_team_invitations' => '1',
                    'module_unit_conversions' => '1',
                    'capability_support_tier' => 'Dedicated Account SLA (Provisional)',
                    'max_users' => null,
                    'max_warehouses' => null,
                    'max_products' => null,
                    'max_suppliers' => null,
                    'max_monthly_purchases' => null,
                    'max_monthly_stock_movements' => null,
                ],
            ],
            [
                'code' => SubscriptionPlan::CODE_INTERNAL,
                'tier' => Company::ACCESS_ENTERPRISE,
                'name' => 'Internal Unlimited',
                'description' => 'Internal Stockora operations workspace plan with full module access and unlimited quotas. Excluded from public plan listings and billing.',
                'price' => 0.00,
                'billing_interval' => SubscriptionPlan::INTERVAL_CUSTOM,
                'is_active' => true,
                'is_recommended' => false,
                'is_internal' => true,
                'sort_order' => 999,
                'features' => [
                    'module_products' => '1',
                    'module_warehouses' => '1',
                    'module_stock_monitoring' => '1',
                    'module_stock_adjustments' => '1',
                    'module_stock_transfers' => '1',
                    'module_stock_issues' => '1',
                    'module_stock_movements' => '1',
                    'module_suppliers' => '1',
                    'module_purchase_orders' => '1',
                    'module_grn' => '1',
                    'module_inventory_reports' => '1',
                    'module_report_exports' => '1',
                    'module_reconciliation_audits' => '1',
                    'module_team_management' => '1',
                    'module_role_permissions' => '1',
                    'module_team_invitations' => '1',
                    'module_unit_conversions' => '1',
                    'capability_support_tier' => 'Internal Operations Team',
                    'max_users' => null,
                    'max_warehouses' => null,
                    'max_products' => null,
                    'max_suppliers' => null,
                    'max_monthly_purchases' => null,
                    'max_monthly_stock_movements' => null,
                ],
            ],
        ];
    }
}
