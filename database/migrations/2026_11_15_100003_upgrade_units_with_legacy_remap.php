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
            throw new RuntimeException("SAFETY ABORT: Migration 100003 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100003_units_remap', 'started', 'add_nullable_company_id');

        $companyRow = $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'");
        if (!$companyRow) {
            throw new RuntimeException('MISSING_DEFAULT_COMPANY: Run 100001 first.');
        }
        $companyId = (int) $companyRow->id;

        if (!$ins->hasColumn('units', 'company_id')) {
            $conn->statement('ALTER TABLE `units` ADD COLUMN `company_id` bigint unsigned NULL AFTER `id`');
        }
        $ins->ensureUnique('units', 'uq_units_company_id', ['company_id', 'id']);
        $ins->ensureUnique('units', 'uq_units_company_symbol', ['company_id', 'symbol']);
        $ins->ensureForeignKey('units', 'fk_units_company', ['company_id'], 'companies', ['id']);

        $legacyCount = (int) ($conn->selectOne('SELECT COUNT(*) AS c FROM `units` WHERE `company_id` IS NULL')->c ?? 0);
        if ($ins->isColumnNullable('units', 'company_id') || $legacyCount > 0) {
            $ins->recordBatchStep('round14_laravel_incremental_v1', '100003_units_remap', 'started', 'clone_legacy_units');
            $legacyUnits = $conn->select('SELECT id, name, symbol FROM `units` WHERE `company_id` IS NULL ORDER BY id ASC');

            foreach ($legacyUnits as $lu) {
                $mapped = $conn->selectOne(
                    'SELECT tenant_unit_id FROM `legacy_unit_mappings` WHERE `company_id` = ? AND `legacy_unit_id` = ?',
                    [$companyId, (int) $lu->id]
                );
                $mappedId = $mapped?->tenant_unit_id;

                if (!$mappedId) {
                    $existing = $conn->selectOne(
                        'SELECT id FROM `units` WHERE `company_id` = ? AND `symbol` = ?',
                        [$companyId, $lu->symbol]
                    );
                    if ($existing) {
                        $mappedId = (int) $existing->id;
                    } else {
                        $conn->statement(
                            'INSERT INTO `units` (`company_id`, `name`, `symbol`, `created_at`, `updated_at`) VALUES (?, ?, ?, NOW(), NOW())',
                            [$companyId, $lu->name, $lu->symbol]
                        );
                        $mappedId = (int) $conn->getPdo()->lastInsertId();
                    }
                    $conn->statement(
                        'INSERT IGNORE INTO `legacy_unit_mappings` (`company_id`, `legacy_unit_id`, `tenant_unit_id`) VALUES (?, ?, ?)',
                        [$companyId, (int) $lu->id, $mappedId]
                    );
                }
            }

            $ins->recordBatchStep('round14_laravel_incremental_v1', '100003_units_remap', 'started', 'remap_all_unit_fks');
            $unitRefs = $ins->findUnitColumnReferences();
            foreach ($unitRefs as $ref) {
                $refTbl = $ref->TABLE_NAME;
                $refCol = $ref->COLUMN_NAME;
                if ($ref->CONSTRAINT_NAME === 'products_unit_id_foreign') {
                    $ins->dropForeignKeyIfExists($refTbl, $ref->CONSTRAINT_NAME);
                }
                $conn->statement(
                    "UPDATE `{$refTbl}` r
                     JOIN `units` u_cur ON u_cur.id = r.`{$refCol}` AND u_cur.company_id IS NULL
                     JOIN `legacy_unit_mappings` m ON m.legacy_unit_id = r.`{$refCol}` AND m.company_id = ?
                     SET r.`{$refCol}` = m.tenant_unit_id",
                    [$companyId]
                );
            }

            // Re-verify ALL columns referencing units.id have zero NULL-company or dangling unit IDs
            foreach ($unitRefs as $ref) {
                $refTbl = $ref->TABLE_NAME;
                $refCol = $ref->COLUMN_NAME;
                $dangling = (int) ($conn->selectOne(
                    "SELECT COUNT(*) AS c FROM `{$refTbl}` r LEFT JOIN `units` u ON u.id = r.`{$refCol}`
                     WHERE r.`{$refCol}` IS NOT NULL AND (u.id IS NULL OR u.company_id IS NULL)"
                )->c ?? 0);
                if ($dangling > 0) {
                    throw new RuntimeException("ABORT: {$dangling} dangling or NULL-company unit references remain on {$refTbl}.{$refCol}");
                }
            }

            // Delete ONLY unreferenced NULL-company legacy unit rows after verification
            $conn->statement('DELETE FROM `units` WHERE `company_id` IS NULL');
            $conn->statement('ALTER TABLE `units` MODIFY COLUMN `company_id` bigint unsigned NOT NULL');
        }

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100003_units_remap', 'completed', 'units_upgraded');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Rollback 100003 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->dropForeignKeyIfExists('units', 'fk_units_company');
        $ins->dropIndexIfExists('units', 'uq_units_company_symbol');
        $ins->dropIndexIfExists('units', 'uq_units_company_id');
        if ($ins->hasColumn('units', 'company_id')) {
            $conn->statement('ALTER TABLE `units` DROP COLUMN `company_id`');
        }
        $conn->statement('DELETE FROM `legacy_unit_mappings`');
        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100003_units_remap'");
    }
};
