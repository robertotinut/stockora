<?php

use App\Support\SchemaIntrospector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        \App\Support\DatabaseSafetyGuard::assertConnectedDatabase((string) $db, 'Migration');

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100007_new_inventory_tables', 'started', 'begin');

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `unit_conversions` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NULL,
                `from_unit_id` bigint unsigned NOT NULL,
                `to_unit_id` bigint unsigned NOT NULL,
                `factor` decimal(20,8) NOT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_uc_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_uc_comp_prod_units` (`company_id`, `product_id`, `from_unit_id`, `to_unit_id`),
                CONSTRAINT `chk_uc_factor_pos` CHECK (`factor` > 0),
                CONSTRAINT `fk_uc_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_uc_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_uc_from_unit_comp` FOREIGN KEY (`company_id`, `from_unit_id`) REFERENCES `units` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_uc_to_unit_comp` FOREIGN KEY (`company_id`, `to_unit_id`) REFERENCES `units` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `product_warehouses` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `warehouse_id` bigint unsigned NOT NULL,
                `stock` decimal(16,4) NOT NULL DEFAULT 0.0000,
                `moving_average_cost` decimal(20,6) NOT NULL DEFAULT 0.000000,
                `inventory_value` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `valuation_basis` enum('VERIFIED_ACQUISITION','ESTIMATED_LEGACY_BASELINE','MIXED_BASELINE_AND_ACQUISITION','UNVERIFIED_QUARANTINED') NOT NULL DEFAULT 'VERIFIED_ACQUISITION',
                `last_reconciled_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pw_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_pw_comp_prod_wh` (`company_id`, `product_id`, `warehouse_id`),
                CONSTRAINT `chk_pw_stock_nonneg` CHECK (`stock` >= 0),
                CONSTRAINT `chk_pw_mac_nonneg` CHECK (`moving_average_cost` >= 0),
                CONSTRAINT `chk_pw_val_nonneg` CHECK (`inventory_value` >= 0),
                CONSTRAINT `fk_pw_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pw_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pw_wh_comp` FOREIGN KEY (`company_id`, `warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `purchase_receipts` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `purchase_id` bigint unsigned NOT NULL,
                `warehouse_id` bigint unsigned NOT NULL,
                `receipt_number` varchar(100) NOT NULL,
                `status` enum('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
                `received_by_user_id` bigint unsigned NOT NULL,
                `total_gross_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `total_discount_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `total_freight_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `total_non_recoverable_tax` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `total_recoverable_tax` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `total_capitalized_cost` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `posted_at` timestamp NULL DEFAULT NULL,
                `cancelled_at` timestamp NULL DEFAULT NULL,
                `idempotency_key` varchar(128) NOT NULL,
                `notes` text NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pr_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_pr_company_id_po` (`company_id`, `id`, `purchase_id`),
                UNIQUE KEY `uq_pr_company_number` (`company_id`, `receipt_number`),
                UNIQUE KEY `uq_pr_company_idem` (`company_id`, `idempotency_key`),
                CONSTRAINT `fk_pr_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pr_purchase_comp` FOREIGN KEY (`company_id`, `purchase_id`) REFERENCES `purchases` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pr_wh_comp` FOREIGN KEY (`company_id`, `warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pr_user_comp` FOREIGN KEY (`company_id`, `received_by_user_id`) REFERENCES `company_user` (`company_id`, `user_id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `purchase_receipt_items` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `purchase_receipt_id` bigint unsigned NOT NULL,
                `purchase_id` bigint unsigned NOT NULL,
                `purchase_item_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `received_unit_id` bigint unsigned NOT NULL,
                `base_unit_id` bigint unsigned NOT NULL,
                `conversion_factor` decimal(20,8) NOT NULL,
                `received_quantity` decimal(16,4) NOT NULL,
                `base_quantity` decimal(16,4) NOT NULL,
                `unit_price` decimal(20,6) NOT NULL,
                `discount_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `allocated_freight_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `non_recoverable_tax_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `recoverable_tax_amount` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `capitalized_total_cost` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `unit_cost_base` decimal(20,6) NOT NULL DEFAULT 0.000000,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_pri_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_pri_comp_rcpt_item` (`company_id`, `purchase_receipt_id`, `purchase_item_id`),
                CONSTRAINT `chk_pri_factor_pos` CHECK (`conversion_factor` > 0),
                CONSTRAINT `chk_pri_recv_pos` CHECK (`received_quantity` > 0),
                CONSTRAINT `chk_pri_base_pos` CHECK (`base_quantity` > 0),
                CONSTRAINT `fk_pri_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pri_receipt_po_comp` FOREIGN KEY (`company_id`, `purchase_receipt_id`, `purchase_id`) REFERENCES `purchase_receipts` (`company_id`, `id`, `purchase_id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pri_po_item_prod_comp` FOREIGN KEY (`company_id`, `purchase_item_id`, `purchase_id`, `product_id`) REFERENCES `purchase_items` (`company_id`, `id`, `purchase_id`, `product_id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pri_recv_unit_comp` FOREIGN KEY (`company_id`, `received_unit_id`) REFERENCES `units` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_pri_prod_base_unit_comp` FOREIGN KEY (`company_id`, `product_id`, `base_unit_id`) REFERENCES `products` (`company_id`, `id`, `unit_id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `stock_transfers` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `transfer_number` varchar(100) NOT NULL,
                `from_warehouse_id` bigint unsigned NOT NULL,
                `to_warehouse_id` bigint unsigned NOT NULL,
                `status` enum('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
                `user_id` bigint unsigned NOT NULL,
                `idempotency_key` varchar(128) NOT NULL,
                `notes` text NULL,
                `posted_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_st_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_st_company_number` (`company_id`, `transfer_number`),
                UNIQUE KEY `uq_st_company_idem` (`company_id`, `idempotency_key`),
                CONSTRAINT `chk_st_diff_wh` CHECK (`from_warehouse_id` <> `to_warehouse_id`),
                CONSTRAINT `fk_st_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_st_from_wh_comp` FOREIGN KEY (`company_id`, `from_warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_st_to_wh_comp` FOREIGN KEY (`company_id`, `to_warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_st_user_comp` FOREIGN KEY (`company_id`, `user_id`) REFERENCES `company_user` (`company_id`, `user_id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `stock_transfer_items` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `stock_transfer_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `unit_id` bigint unsigned NOT NULL,
                `conversion_factor` decimal(20,8) NOT NULL,
                `quantity` decimal(16,4) NOT NULL,
                `base_quantity` decimal(16,4) NOT NULL,
                `unit_cost_snapshot` decimal(20,6) NOT NULL DEFAULT 0.000000,
                `total_cost_snapshot` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sti_company_id` (`company_id`, `id`),
                CONSTRAINT `chk_sti_qty_pos` CHECK (`quantity` > 0 AND `base_quantity` > 0 AND `conversion_factor` > 0),
                CONSTRAINT `fk_sti_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sti_transfer_comp` FOREIGN KEY (`company_id`, `stock_transfer_id`) REFERENCES `stock_transfers` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sti_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sti_unit_comp` FOREIGN KEY (`company_id`, `unit_id`) REFERENCES `units` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `stock_issues` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `issue_number` varchar(100) NOT NULL,
                `warehouse_id` bigint unsigned NOT NULL,
                `status` enum('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
                `user_id` bigint unsigned NOT NULL,
                `idempotency_key` varchar(128) NOT NULL,
                `notes` text NULL,
                `posted_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_si_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_si_company_number` (`company_id`, `issue_number`),
                UNIQUE KEY `uq_si_company_idem` (`company_id`, `idempotency_key`),
                CONSTRAINT `fk_si_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_si_wh_comp` FOREIGN KEY (`company_id`, `warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_si_user_comp` FOREIGN KEY (`company_id`, `user_id`) REFERENCES `company_user` (`company_id`, `user_id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `stock_issue_items` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `stock_issue_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `unit_id` bigint unsigned NOT NULL,
                `conversion_factor` decimal(20,8) NOT NULL,
                `quantity` decimal(16,4) NOT NULL,
                `base_quantity` decimal(16,4) NOT NULL,
                `unit_cost_snapshot` decimal(20,6) NOT NULL DEFAULT 0.000000,
                `total_cost_snapshot` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sii_company_id` (`company_id`, `id`),
                CONSTRAINT `chk_sii_qty_pos` CHECK (`quantity` > 0 AND `base_quantity` > 0 AND `conversion_factor` > 0),
                CONSTRAINT `fk_sii_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sii_issue_comp` FOREIGN KEY (`company_id`, `stock_issue_id`) REFERENCES `stock_issues` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sii_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sii_unit_comp` FOREIGN KEY (`company_id`, `unit_id`) REFERENCES `units` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `stock_adjustments` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `adjustment_number` varchar(100) NOT NULL,
                `warehouse_id` bigint unsigned NOT NULL,
                `status` enum('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
                `user_id` bigint unsigned NOT NULL,
                `idempotency_key` varchar(128) NOT NULL,
                `reverses_type` varchar(64) NULL,
                `reverses_id` bigint unsigned NULL,
                `reason` varchar(255) NOT NULL,
                `posted_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sa_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_sa_company_number` (`company_id`, `adjustment_number`),
                UNIQUE KEY `uq_sa_company_idem` (`company_id`, `idempotency_key`),
                UNIQUE KEY `uq_sa_company_reverses` (`company_id`, `reverses_type`, `reverses_id`),
                CONSTRAINT `fk_sa_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sa_wh_comp` FOREIGN KEY (`company_id`, `warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sa_user_comp` FOREIGN KEY (`company_id`, `user_id`) REFERENCES `company_user` (`company_id`, `user_id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `stock_adjustment_items` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `stock_adjustment_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `direction` enum('in','out') NOT NULL,
                `quantity` decimal(16,4) NOT NULL,
                `unit_cost` decimal(20,6) NOT NULL DEFAULT 0.000000,
                `total_cost` decimal(28,4) NOT NULL DEFAULT 0.0000,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sai_company_id` (`company_id`, `id`),
                CONSTRAINT `chk_sai_qty_pos` CHECK (`quantity` > 0),
                CONSTRAINT `fk_sai_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sai_adj_comp` FOREIGN KEY (`company_id`, `stock_adjustment_id`) REFERENCES `stock_adjustments` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_sai_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `inventory_valuation_adjustments` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `warehouse_id` bigint unsigned NOT NULL,
                `stock_movement_id` bigint unsigned NULL,
                `adjustment_type` enum('depletion_residual','rounding_reconciliation','manual_revaluation') NOT NULL,
                `adjustment_delta` decimal(28,4) NOT NULL,
                `value_before` decimal(28,4) NOT NULL,
                `value_after` decimal(28,4) NOT NULL,
                `reason` varchar(255) NOT NULL,
                `idempotency_key` varchar(128) NOT NULL,
                `created_by_user_id` bigint unsigned NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_iva_company_idem` (`company_id`, `idempotency_key`),
                CONSTRAINT `chk_iva_val_nonneg` CHECK (`value_after` >= 0),
                CONSTRAINT `fk_iva_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_iva_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_iva_wh_comp` FOREIGN KEY (`company_id`, `warehouse_id`) REFERENCES `warehouses` (`company_id`, `id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_iva_sm_comp` FOREIGN KEY (`company_id`, `stock_movement_id`) REFERENCES `stock_movements` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `legacy_reconciliation_audits` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `product_id` bigint unsigned NOT NULL,
                `warehouse_id` bigint unsigned NOT NULL,
                `classification` enum('CHAIN_CONSISTENT','BASELINE_INITIALIZATION','HISTORICALLY_VERIFIED','CHAIN_BREAK') NOT NULL,
                `legacy_product_stock` decimal(16,4) NOT NULL,
                `computed_opening_qty` decimal(16,4) NULL,
                `action_taken` enum('AUTO_BACKFILLED','BLOCKED_NEEDS_REVIEW') NOT NULL,
                `blocking_reason` varchar(500) NULL,
                `evidence_json` text NOT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_lra_comp_prod` (`company_id`, `product_id`),
                CONSTRAINT `fk_lra_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
                CONSTRAINT `fk_lra_product_comp` FOREIGN KEY (`company_id`, `product_id`) REFERENCES `products` (`company_id`, `id`) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100007_new_inventory_tables', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        \App\Support\DatabaseSafetyGuard::assertConnectedDatabase((string) $db, 'Migration');

        foreach ([
            'legacy_reconciliation_audits',
            'inventory_valuation_adjustments',
            'stock_adjustment_items',
            'stock_adjustments',
            'stock_issue_items',
            'stock_issues',
            'stock_transfer_items',
            'stock_transfers',
            'purchase_receipt_items',
            'purchase_receipts',
            'product_warehouses',
            'unit_conversions',
        ] as $tbl) {
            $conn->statement("DROP TABLE IF EXISTS `{$tbl}`");
        }

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100007_new_inventory_tables'");
    }
};
