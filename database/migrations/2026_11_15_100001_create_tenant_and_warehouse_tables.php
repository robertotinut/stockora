<?php

use App\Support\SchemaIntrospector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    private function guardSandbox($conn): void
    {
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        \App\Support\DatabaseSafetyGuard::assertConnectedDatabase((string) $db, 'Migration');
    }

    public function up(): void
    {
        $conn = DB::connection();
        $this->guardSandbox($conn);
        $ins = new SchemaIntrospector($conn);

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `migration_batches` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `batch_key` varchar(100) NOT NULL,
                `phase` varchar(100) NOT NULL,
                `status` enum('started','completed','failed') NOT NULL,
                `last_step` varchar(100) NOT NULL,
                `details` text NULL,
                `started_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `completed_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_mb_batch_phase` (`batch_key`, `phase`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100001_tenant_foundation', 'started', 'create_tables');

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `companies` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `code` varchar(50) NOT NULL,
                `name` varchar(255) NOT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_companies_code` (`code`),
                UNIQUE KEY `uq_companies_id_code` (`id`, `code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `company_user` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `user_id` bigint unsigned NOT NULL,
                `role` varchar(50) NOT NULL DEFAULT 'owner',
                `is_default` tinyint(1) NOT NULL DEFAULT 0,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_cu_company_user` (`company_id`, `user_id`),
                CONSTRAINT `fk_cu_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `fk_cu_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `warehouses` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `code` varchar(50) NOT NULL,
                `name` varchar(255) NOT NULL,
                `is_default` tinyint(1) NOT NULL DEFAULT 0,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_wh_company_id` (`company_id`, `id`),
                UNIQUE KEY `uq_wh_company_code` (`company_id`, `code`),
                CONSTRAINT `fk_wh_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `legacy_unit_mappings` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `legacy_unit_id` bigint unsigned NOT NULL,
                `tenant_unit_id` bigint unsigned NOT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_lum_comp_legacy` (`company_id`, `legacy_unit_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Optional sandbox offset to prove ZERO code assumes company_id=1 or warehouse_id=1
        $minCompId = (int) (getenv('STOCKORA_SANDBOX_MIN_COMPANY_ID') ?: 0);
        $minWhId = (int) (getenv('STOCKORA_SANDBOX_MIN_WAREHOUSE_ID') ?: 0);
        if ($minCompId > 1 && (int) ($conn->selectOne('SELECT COUNT(*) AS c FROM companies')->c ?? 0) === 0) {
            $conn->statement("ALTER TABLE `companies` AUTO_INCREMENT = {$minCompId}");
        }
        if ($minWhId > 1 && (int) ($conn->selectOne('SELECT COUNT(*) AS c FROM warehouses')->c ?? 0) === 0) {
            $conn->statement("ALTER TABLE `warehouses` AUTO_INCREMENT = {$minWhId}");
        }

        // Dynamic lookup without ever assuming ID = 1
        $conn->statement("INSERT IGNORE INTO `companies` (`code`, `name`, `is_active`) VALUES ('DEFAULT', 'Stockora Default Company', 1)");
        $companyId = (int) $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'")->id;

        $conn->statement(
            "INSERT IGNORE INTO `warehouses` (`company_id`, `code`, `name`, `is_default`, `is_active`) VALUES (?, 'WH-MAIN', 'Main Warehouse', 1, 1)",
            [$companyId]
        );

        $users = $conn->select('SELECT id FROM `users` ORDER BY id ASC');
        foreach ($users as $u) {
            $conn->statement(
                "INSERT IGNORE INTO `company_user` (`company_id`, `user_id`, `role`, `is_default`, `is_active`) VALUES (?, ?, 'owner', 1, 1)",
                [$companyId, (int) $u->id]
            );
        }

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100001_tenant_foundation', 'completed', 'seeded_default_tenant', "company_id={$companyId}");
    }

    public function down(): void
    {
        $conn = DB::connection();
        $this->guardSandbox($conn);

        $conn->statement('DROP TABLE IF EXISTS `legacy_unit_mappings`');
        $conn->statement('DROP TABLE IF EXISTS `warehouses`');
        $conn->statement('DROP TABLE IF EXISTS `company_user`');
        $conn->statement('DROP TABLE IF EXISTS `companies`');
        $conn->statement('DROP TABLE IF EXISTS `migration_batches`');
    }
};
