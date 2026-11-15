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
            throw new RuntimeException("SAFETY ABORT: Migration 100005 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100005_purchases_upgrade', 'started', 'begin');

        $companyRow = $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'");
        if (!$companyRow) {
            throw new RuntimeException('MISSING_DEFAULT_COMPANY: Run 100001 first.');
        }
        $companyId = (int) $companyRow->id;

        if (!$ins->hasColumn('purchases', 'company_id')) {
            $conn->statement('ALTER TABLE `purchases` ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`');
        }
        $conn->statement('ALTER TABLE `purchases` MODIFY COLUMN `total_amount` decimal(28,4) NOT NULL DEFAULT 0.0000');
        $conn->statement("ALTER TABLE `purchases` MODIFY COLUMN `status` enum('pending','ordered','partial','received','cancelled') NOT NULL DEFAULT 'pending'");
        $conn->statement('UPDATE `purchases` SET `company_id` = ? WHERE `company_id` IS NULL', [$companyId]);
        if ($ins->isColumnNullable('purchases', 'company_id')) {
            $conn->statement('ALTER TABLE `purchases` MODIFY COLUMN `company_id` bigint unsigned NOT NULL');
        }

        $ins->dropForeignKeyIfExists('purchases', 'purchases_supplier_id_foreign');
        $ins->dropForeignKeyIfExists('purchases', 'purchases_user_id_foreign');
        $ins->dropIndexIfExists('purchases', 'purchases_purchase_number_unique');

        $ins->ensureUnique('purchases', 'uq_purchases_company_id', ['company_id', 'id']);
        $ins->ensureUnique('purchases', 'uq_purchases_company_number', ['company_id', 'purchase_number']);
        $ins->ensureForeignKey('purchases', 'fk_purchases_company', ['company_id'], 'companies', ['id']);
        $ins->ensureForeignKey('purchases', 'fk_purchases_supplier_comp', ['company_id', 'supplier_id'], 'suppliers', ['company_id', 'id']);
        $ins->ensureForeignKey('purchases', 'fk_purchases_user_comp', ['company_id', 'user_id'], 'company_user', ['company_id', 'user_id']);

        $piAdds = [
            'company_id'            => 'ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`',
            'unit_id'               => 'ADD COLUMN `unit_id` bigint unsigned NULL AFTER `product_id`',
            'received_quantity'     => 'ADD COLUMN `received_quantity` decimal(16,4) NOT NULL DEFAULT 0.0000 AFTER `quantity`',
            'conversion_status'     => "ADD COLUMN `conversion_status` enum('VERIFIED','NEEDS_REVIEW') NOT NULL DEFAULT 'VERIFIED' AFTER `subtotal`",
            'cost_breakdown_status' => "ADD COLUMN `cost_breakdown_status` enum('VERIFIED','UNKNOWN_COMPONENTS') NOT NULL DEFAULT 'VERIFIED' AFTER `conversion_status`",
        ];
        foreach ($piAdds as $col => $ddl) {
            if (!$ins->hasColumn('purchase_items', $col)) {
                $conn->statement("ALTER TABLE `purchase_items` {$ddl}");
            }
        }
        $conn->statement('ALTER TABLE `purchase_items` MODIFY COLUMN `quantity` decimal(16,4) NOT NULL');
        $conn->statement('ALTER TABLE `purchase_items` MODIFY COLUMN `unit_price` decimal(20,6) NOT NULL');
        $conn->statement('ALTER TABLE `purchase_items` MODIFY COLUMN `subtotal` decimal(28,4) NOT NULL');

        $conn->statement('UPDATE `purchase_items` SET `company_id` = ? WHERE `company_id` IS NULL', [$companyId]);
        $conn->statement("
            UPDATE `purchase_items`
            SET `conversion_status` = 'NEEDS_REVIEW',
                `cost_breakdown_status` = 'UNKNOWN_COMPONENTS'
            WHERE `unit_id` IS NULL
        ");
        if ($ins->isColumnNullable('purchase_items', 'company_id')) {
            $conn->statement('ALTER TABLE `purchase_items` MODIFY COLUMN `company_id` bigint unsigned NOT NULL');
        }

        $ins->dropForeignKeyIfExists('purchase_items', 'purchase_items_purchase_id_foreign');
        $ins->dropForeignKeyIfExists('purchase_items', 'purchase_items_product_id_foreign');

        $ins->ensureUnique('purchase_items', 'uq_pi_company_id', ['company_id', 'id']);
        $ins->ensureUnique('purchase_items', 'uq_pi_comp_id_po_prod', ['company_id', 'id', 'purchase_id', 'product_id']);
        $ins->ensureForeignKey('purchase_items', 'fk_pi_purchase_comp', ['company_id', 'purchase_id'], 'purchases', ['company_id', 'id']);
        $ins->ensureForeignKey('purchase_items', 'fk_pi_product_comp', ['company_id', 'product_id'], 'products', ['company_id', 'id']);
        $ins->ensureForeignKey('purchase_items', 'fk_pi_unit_comp', ['company_id', 'unit_id'], 'units', ['company_id', 'id']);

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100005_purchases_upgrade', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Rollback 100005 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->dropForeignKeyIfExists('purchase_items', 'fk_pi_unit_comp');
        $ins->dropForeignKeyIfExists('purchase_items', 'fk_pi_product_comp');
        $ins->dropForeignKeyIfExists('purchase_items', 'fk_pi_purchase_comp');
        $ins->dropIndexIfExists('purchase_items', 'uq_pi_comp_id_po_prod');
        $ins->dropIndexIfExists('purchase_items', 'uq_pi_company_id');
        foreach (['cost_breakdown_status', 'conversion_status', 'received_quantity', 'unit_id', 'company_id'] as $col) {
            if ($ins->hasColumn('purchase_items', $col)) {
                $conn->statement("ALTER TABLE `purchase_items` DROP COLUMN `{$col}`");
            }
        }
        $ins->ensureForeignKey('purchase_items', 'purchase_items_purchase_id_foreign', ['purchase_id'], 'purchases', ['id']);
        $ins->ensureForeignKey('purchase_items', 'purchase_items_product_id_foreign', ['product_id'], 'products', ['id']);

        $ins->dropForeignKeyIfExists('purchases', 'fk_purchases_user_comp');
        $ins->dropForeignKeyIfExists('purchases', 'fk_purchases_supplier_comp');
        $ins->dropForeignKeyIfExists('purchases', 'fk_purchases_company');
        $ins->dropIndexIfExists('purchases', 'uq_purchases_company_number');
        $ins->dropIndexIfExists('purchases', 'uq_purchases_company_id');
        if ($ins->hasColumn('purchases', 'company_id')) {
            $conn->statement('ALTER TABLE `purchases` DROP COLUMN `company_id`');
        }
        $conn->statement("UPDATE `purchases` SET `status` = 'ordered' WHERE `status` = 'partial'");
        $conn->statement("ALTER TABLE `purchases` MODIFY COLUMN `status` enum('pending','ordered','received','cancelled') NOT NULL DEFAULT 'received'");
        $ins->ensureUnique('purchases', 'purchases_purchase_number_unique', ['purchase_number']);
        $ins->ensureForeignKey('purchases', 'purchases_supplier_id_foreign', ['supplier_id'], 'suppliers', ['id']);
        $ins->ensureForeignKey('purchases', 'purchases_user_id_foreign', ['user_id'], 'users', ['id']);

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100005_purchases_upgrade'");
    }
};
