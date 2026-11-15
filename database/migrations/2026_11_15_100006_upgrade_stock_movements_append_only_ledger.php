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
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Migration 100006 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100006_stock_movements_ledger', 'started', 'begin');

        $companyRow = $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'");
        if (!$companyRow) {
            throw new RuntimeException('MISSING_DEFAULT_COMPANY: Run 100001 first.');
        }
        $companyId = (int) $companyRow->id;

        $whRow = $conn->selectOne('SELECT id FROM `warehouses` WHERE `company_id` = ? AND `is_default` = 1 ORDER BY id ASC LIMIT 1', [$companyId]);
        if (!$whRow) {
            throw new RuntimeException("MISSING_DEFAULT_WAREHOUSE for company={$companyId}");
        }
        $defaultWarehouseId = (int) $whRow->id;

        $adds = [
            'company_id'          => 'ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`',
            'warehouse_id'        => 'ADD COLUMN `warehouse_id` bigint unsigned NULL AFTER `product_id`',
            'direction'           => "ADD COLUMN `direction` enum('in','out') NULL AFTER `type`",
            'unit_cost'           => 'ADD COLUMN `unit_cost` decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `quantity`',
            'total_cost'          => 'ADD COLUMN `total_cost` decimal(28,4) NOT NULL DEFAULT 0.0000 AFTER `unit_cost`',
            'running_stock_after' => 'ADD COLUMN `running_stock_after` decimal(16,4) NULL AFTER `stock_after`',
            'running_value_after' => 'ADD COLUMN `running_value_after` decimal(28,4) NULL AFTER `running_stock_after`',
            'valuation_basis'     => "ADD COLUMN `valuation_basis` enum('VERIFIED_ACQUISITION','ESTIMATED_LEGACY_BASELINE','REVERSAL_CARRIED_COST','UNVERIFIED_QUARANTINED') NOT NULL DEFAULT 'VERIFIED_ACQUISITION' AFTER `running_value_after`",
            'migration_batch_id'  => 'ADD COLUMN `migration_batch_id` varchar(64) NULL AFTER `notes`',
            'idempotency_key'     => 'ADD COLUMN `idempotency_key` varchar(128) NULL AFTER `migration_batch_id`',
        ];
        foreach ($adds as $col => $ddl) {
            if (!$ins->hasColumn('stock_movements', $col)) {
                $conn->statement("ALTER TABLE `stock_movements` {$ddl}");
            }
        }

        $conn->statement("
            ALTER TABLE `stock_movements`
            MODIFY COLUMN `type` enum('opening_balance','in','out','transfer_in','transfer_out','adjustment','issue','reversal_in','reversal_out') NOT NULL
        ");

        $conn->statement("
            UPDATE `stock_movements`
            SET `direction` = CASE
                    WHEN `direction` IS NOT NULL THEN `direction`
                    WHEN `type` IN ('in', 'opening_balance', 'transfer_in', 'reversal_in') THEN 'in'
                    WHEN `type` IN ('out', 'transfer_out', 'issue', 'reversal_out') THEN 'out'
                    WHEN `quantity` < 0 THEN 'out'
                    ELSE 'in'
                END,
                `quantity` = ABS(`quantity`),
                `valuation_basis` = CASE
                    WHEN `idempotency_key` IS NULL THEN 'ESTIMATED_LEGACY_BASELINE'
                    ELSE `valuation_basis`
                END
            WHERE `direction` IS NULL OR `quantity` < 0 OR `idempotency_key` IS NULL
        ");

        $conn->statement("ALTER TABLE `stock_movements` MODIFY COLUMN `direction` enum('in','out') NOT NULL");
        $conn->statement('ALTER TABLE `stock_movements` MODIFY COLUMN `quantity` decimal(16,4) NOT NULL');
        $conn->statement('ALTER TABLE `stock_movements` MODIFY COLUMN `stock_before` decimal(16,4) NOT NULL');
        $conn->statement('ALTER TABLE `stock_movements` MODIFY COLUMN `stock_after` decimal(16,4) NOT NULL');

        $conn->statement('UPDATE `stock_movements` SET `company_id` = ? WHERE `company_id` IS NULL', [$companyId]);
        $conn->statement('UPDATE `stock_movements` SET `warehouse_id` = ? WHERE `warehouse_id` IS NULL', [$defaultWarehouseId]);
        $conn->statement("
            UPDATE `stock_movements`
            SET `idempotency_key` = CONCAT(
                'legacy-sm-', `id`, '-',
                SUBSTRING(SHA2(CONCAT_WS('|', `company_id`, `product_id`, `type`, `direction`, `quantity`, `stock_before`, `stock_after`), 256), 1, 24)
            )
            WHERE `idempotency_key` IS NULL
        ");

        if ($ins->isColumnNullable('stock_movements', 'company_id')) {
            $conn->statement('ALTER TABLE `stock_movements` MODIFY COLUMN `company_id` bigint unsigned NOT NULL');
        }
        if ($ins->isColumnNullable('stock_movements', 'warehouse_id')) {
            $conn->statement('ALTER TABLE `stock_movements` MODIFY COLUMN `warehouse_id` bigint unsigned NOT NULL');
        }
        if ($ins->isColumnNullable('stock_movements', 'idempotency_key')) {
            $conn->statement('ALTER TABLE `stock_movements` MODIFY COLUMN `idempotency_key` varchar(128) NOT NULL');
        }

        $ins->dropForeignKeyIfExists('stock_movements', 'stock_movements_product_id_foreign');
        $ins->dropForeignKeyIfExists('stock_movements', 'stock_movements_user_id_foreign');

        $ins->ensureUnique('stock_movements', 'uq_sm_company_id', ['company_id', 'id']);
        $ins->ensureUnique('stock_movements', 'uq_sm_company_idem', ['company_id', 'idempotency_key']);
        $ins->ensureIndex('stock_movements', 'idx_sm_comp_prod_wh_id', ['company_id', 'product_id', 'warehouse_id', 'id']);
        $ins->ensureForeignKey('stock_movements', 'fk_sm_company', ['company_id'], 'companies', ['id']);
        $ins->ensureForeignKey('stock_movements', 'fk_sm_product_comp', ['company_id', 'product_id'], 'products', ['company_id', 'id']);
        $ins->ensureForeignKey('stock_movements', 'fk_sm_warehouse_comp', ['company_id', 'warehouse_id'], 'warehouses', ['company_id', 'id']);
        $ins->ensureForeignKey('stock_movements', 'fk_sm_user_comp', ['company_id', 'user_id'], 'company_user', ['company_id', 'user_id']);
        $ins->ensureCheck('stock_movements', 'chk_sm_quantity_positive', '`quantity` > 0');

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100006_stock_movements_ledger', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Rollback 100006 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $chk = $conn->selectOne("SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'stock_movements' AND CONSTRAINT_NAME = 'chk_sm_quantity_positive'", [$db]);
        if ((int) ($chk->c ?? 0) > 0) {
            $conn->statement('ALTER TABLE `stock_movements` DROP CHECK `chk_sm_quantity_positive`');
        }
        foreach (['fk_sm_user_comp', 'fk_sm_warehouse_comp', 'fk_sm_product_comp', 'fk_sm_company'] as $fk) {
            $ins->dropForeignKeyIfExists('stock_movements', $fk);
        }
        foreach (['idx_sm_comp_prod_wh_id', 'uq_sm_company_idem', 'uq_sm_company_id'] as $idx) {
            $ins->dropIndexIfExists('stock_movements', $idx);
        }

        if ($ins->hasColumn('stock_movements', 'direction')) {
            $conn->statement("UPDATE `stock_movements` SET `quantity` = -ABS(`quantity`) WHERE `type` = 'adjustment' AND `direction` = 'out'");
        }

        foreach (['idempotency_key', 'migration_batch_id', 'valuation_basis', 'running_value_after', 'running_stock_after', 'total_cost', 'unit_cost', 'direction', 'warehouse_id', 'company_id'] as $col) {
            if ($ins->hasColumn('stock_movements', $col)) {
                $conn->statement("ALTER TABLE `stock_movements` DROP COLUMN `{$col}`");
            }
        }
        $conn->statement("ALTER TABLE `stock_movements` MODIFY COLUMN `type` enum('in','out','adjustment') NOT NULL");

        $ins->ensureForeignKey('stock_movements', 'stock_movements_product_id_foreign', ['product_id'], 'products', ['id']);
        $ins->ensureForeignKey('stock_movements', 'stock_movements_user_id_foreign', ['user_id'], 'users', ['id']);

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100006_stock_movements_ledger'");
    }
};
