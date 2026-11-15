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
            throw new RuntimeException("SAFETY ABORT: Migration 100002 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100002_categories_suppliers', 'started', 'begin');

        $companyRow = $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'");
        if (!$companyRow) {
            throw new RuntimeException('MISSING_DEFAULT_COMPANY: Run 100001 first.');
        }
        $companyId = (int) $companyRow->id;

        foreach (['categories', 'suppliers'] as $tbl) {
            if (!$ins->hasColumn($tbl, 'company_id')) {
                $conn->statement("ALTER TABLE `{$tbl}` ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`");
            }
            $conn->statement("UPDATE `{$tbl}` SET `company_id` = ? WHERE `company_id` IS NULL", [$companyId]);
            if ($ins->isColumnNullable($tbl, 'company_id')) {
                $conn->statement("ALTER TABLE `{$tbl}` MODIFY COLUMN `company_id` bigint unsigned NOT NULL");
            }
            $ins->dropIndexIfExists($tbl, "{$tbl}_code_unique");
            $ins->ensureUnique($tbl, "uq_{$tbl}_company_id", ['company_id', 'id']);
            $ins->ensureUnique($tbl, "uq_{$tbl}_company_code", ['company_id', 'code']);
            $ins->ensureForeignKey($tbl, "fk_{$tbl}_company", ['company_id'], 'companies', ['id']);
        }

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100002_categories_suppliers', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Rollback 100002 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        foreach (['categories', 'suppliers'] as $tbl) {
            $ins->dropForeignKeyIfExists($tbl, "fk_{$tbl}_company");
            $ins->dropIndexIfExists($tbl, "uq_{$tbl}_company_code");
            $ins->dropIndexIfExists($tbl, "uq_{$tbl}_company_id");
            if ($ins->hasColumn($tbl, 'company_id')) {
                $conn->statement("ALTER TABLE `{$tbl}` DROP COLUMN `company_id`");
            }
            $ins->ensureUnique($tbl, "{$tbl}_code_unique", ['code']);
        }
        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100002_categories_suppliers'");
    }
};
