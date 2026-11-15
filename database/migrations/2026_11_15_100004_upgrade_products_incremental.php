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
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100004_products_upgrade', 'started', 'add_columns');

        $companyRow = $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'");
        if (!$companyRow) {
            throw new RuntimeException('MISSING_DEFAULT_COMPANY: Run 100001 first.');
        }
        $companyId = (int) $companyRow->id;

        $adds = [
            'company_id'                   => 'ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`',
            'moving_average_cost'          => 'ADD COLUMN `moving_average_cost` decimal(20,6) NOT NULL DEFAULT 0.000000 AFTER `selling_price`',
            'inventory_value'              => 'ADD COLUMN `inventory_value` decimal(28,4) NOT NULL DEFAULT 0.0000 AFTER `stock`',
            'legacy_reconciliation_status' => "ADD COLUMN `legacy_reconciliation_status` enum('PENDING','CHAIN_CONSISTENT','BASELINE_INITIALIZATION','HISTORICALLY_VERIFIED','CHAIN_BREAK') NOT NULL DEFAULT 'PENDING' AFTER `inventory_value`",
        ];
        foreach ($adds as $col => $ddl) {
            if (!$ins->hasColumn('products', $col)) {
                $conn->statement("ALTER TABLE `products` {$ddl}");
            }
        }

        $conn->statement('ALTER TABLE `products` MODIFY COLUMN `purchase_price` decimal(20,6) NOT NULL DEFAULT 0.000000');
        $conn->statement('ALTER TABLE `products` MODIFY COLUMN `selling_price` decimal(20,6) NOT NULL DEFAULT 0.000000');
        $conn->statement('ALTER TABLE `products` MODIFY COLUMN `stock` decimal(16,4) NOT NULL DEFAULT 0.0000');
        $conn->statement('ALTER TABLE `products` MODIFY COLUMN `min_stock` decimal(16,4) NOT NULL DEFAULT 0.0000');

        // Testable mid-migration crash hook for Round 14 Section 2 resumability proof
        $simFail = getenv('STOCKORA_SIMULATE_MIGRATION_FAIL') ?: ($_ENV['STOCKORA_SIMULATE_MIGRATION_FAIL'] ?? '');
        if ($simFail === '100004') {
            $ins->recordBatchStep(
                'round14_laravel_incremental_v1',
                '100004_products_upgrade',
                'failed',
                'mid_alter_columns_before_backfill',
                'Simulated crash triggered via STOCKORA_SIMULATE_MIGRATION_FAIL=100004'
            );
            throw new RuntimeException('SIMULATED_CRASH_100004: Interrupted mid-migration after column additions before NOT NULL & FKs.');
        }

        $conn->statement('UPDATE `products` SET `company_id` = ? WHERE `company_id` IS NULL', [$companyId]);
        if ($ins->isColumnNullable('products', 'company_id')) {
            $conn->statement('ALTER TABLE `products` MODIFY COLUMN `company_id` bigint unsigned NOT NULL');
        }

        $ins->dropForeignKeyIfExists('products', 'products_category_id_foreign');
        $ins->dropForeignKeyIfExists('products', 'products_unit_id_foreign');
        $ins->dropIndexIfExists('products', 'products_code_unique');

        $ins->ensureUnique('products', 'uq_products_company_id', ['company_id', 'id']);
        $ins->ensureUnique('products', 'uq_products_company_code', ['company_id', 'code']);
        $ins->ensureUnique('products', 'uq_products_company_id_unit', ['company_id', 'id', 'unit_id']);
        $ins->ensureForeignKey('products', 'fk_products_company', ['company_id'], 'companies', ['id']);
        $ins->ensureForeignKey('products', 'fk_products_category_comp', ['company_id', 'category_id'], 'categories', ['company_id', 'id']);
        $ins->ensureForeignKey('products', 'fk_products_unit_comp', ['company_id', 'unit_id'], 'units', ['company_id', 'id']);

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100004_products_upgrade', 'completed', 'products_upgraded');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        \App\Support\DatabaseSafetyGuard::assertConnectedDatabase((string) $db, 'Migration');

        $ins = new SchemaIntrospector($conn);
        $ins->dropForeignKeyIfExists('products', 'fk_products_unit_comp');
        $ins->dropForeignKeyIfExists('products', 'fk_products_category_comp');
        $ins->dropForeignKeyIfExists('products', 'fk_products_company');
        $ins->dropIndexIfExists('products', 'uq_products_company_id_unit');
        $ins->dropIndexIfExists('products', 'uq_products_company_code');
        $ins->dropIndexIfExists('products', 'uq_products_company_id');

        foreach (['legacy_reconciliation_status', 'inventory_value', 'moving_average_cost', 'company_id'] as $col) {
            if ($ins->hasColumn('products', $col)) {
                $conn->statement("ALTER TABLE `products` DROP COLUMN `{$col}`");
            }
        }

        $ins->ensureUnique('products', 'products_code_unique', ['code']);
        $ins->ensureForeignKey('products', 'products_category_id_foreign', ['category_id'], 'categories', ['id']);
        $ins->ensureForeignKey('products', 'products_unit_id_foreign', ['unit_id'], 'units', ['id']);
        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100004_products_upgrade'");
    }
};
