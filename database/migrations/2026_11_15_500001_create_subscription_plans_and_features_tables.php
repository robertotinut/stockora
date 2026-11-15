<?php

use App\Support\SchemaIntrospector;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function getConnection(): ?string
    {
        return 'mysql_migrator';
    }

    private function guardSandbox($conn): void
    {
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        \App\Support\DatabaseSafetyGuard::assertConnectedDatabase((string) $db, 'Migration');
    }

    public function up(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round21_subscription_plans_v1', '500001_subscription_plans_features', 'started', 'create_and_alter');

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `subscription_plans` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `code` varchar(50) NOT NULL,
                `tier` enum('FREE', 'STARTER', 'PROFESSIONAL', 'ENTERPRISE') NOT NULL DEFAULT 'FREE',
                `name` varchar(120) NOT NULL,
                `description` text NULL,
                `price` decimal(15,2) NOT NULL DEFAULT 0.00,
                `billing_interval` enum('MONTHLY', 'YEARLY', 'CUSTOM') NOT NULL DEFAULT 'MONTHLY',
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `is_recommended` tinyint(1) NOT NULL DEFAULT 0,
                `sort_order` int unsigned NOT NULL DEFAULT 0,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sub_plans_code` (`code`),
                KEY `idx_sub_plans_active_sort` (`is_active`, `sort_order`),
                KEY `idx_sub_plans_tier` (`tier`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `subscription_features` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `feature_key` varchar(80) NOT NULL,
                `name` varchar(120) NOT NULL,
                `description` text NULL,
                `category` varchar(64) NOT NULL,
                `feature_type` enum('BOOLEAN', 'LIMIT', 'TEXT') NOT NULL DEFAULT 'BOOLEAN',
                `default_value` varchar(255) NULL DEFAULT NULL,
                `sort_order` int unsigned NOT NULL DEFAULT 0,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sub_features_key` (`feature_key`),
                KEY `idx_sub_features_cat_sort` (`category`, `sort_order`),
                KEY `idx_sub_features_active_type` (`is_active`, `feature_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `subscription_plan_features` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `subscription_plan_id` bigint unsigned NOT NULL,
                `subscription_feature_id` bigint unsigned NOT NULL,
                `value` varchar(255) NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_sub_plan_feature` (`subscription_plan_id`, `subscription_feature_id`),
                KEY `idx_spf_feature` (`subscription_feature_id`),
                CONSTRAINT `fk_spf_plan` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT `fk_spf_feature` FOREIGN KEY (`subscription_feature_id`) REFERENCES `subscription_features` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!$ins->hasColumn('companies', 'subscription_plan_id')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `subscription_plan_id` bigint unsigned NULL AFTER `access_type`');
        }
        if (!$ins->hasIndexName('companies', 'idx_comp_sub_plan')) {
            $conn->statement('ALTER TABLE `companies` ADD KEY `idx_comp_sub_plan` (`subscription_plan_id`)');
        }
        if (!$ins->hasForeignKeyName('companies', 'fk_comp_sub_plan')) {
            $conn->statement('ALTER TABLE `companies` ADD CONSTRAINT `fk_comp_sub_plan` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
        }

        if (!$ins->hasColumn('platform_audit_logs', 'subscription_plan_id')) {
            $conn->statement('ALTER TABLE `platform_audit_logs` ADD COLUMN `subscription_plan_id` bigint unsigned NULL AFTER `company_id`');
        }
        if (!$ins->hasColumn('platform_audit_logs', 'subscription_feature_id')) {
            $conn->statement('ALTER TABLE `platform_audit_logs` ADD COLUMN `subscription_feature_id` bigint unsigned NULL AFTER `subscription_plan_id`');
        }
        if (!$ins->hasIndexName('platform_audit_logs', 'idx_pal_plan_created')) {
            $conn->statement('ALTER TABLE `platform_audit_logs` ADD KEY `idx_pal_plan_created` (`subscription_plan_id`, `created_at`)');
        }
        if (!$ins->hasIndexName('platform_audit_logs', 'idx_pal_feature_created')) {
            $conn->statement('ALTER TABLE `platform_audit_logs` ADD KEY `idx_pal_feature_created` (`subscription_feature_id`, `created_at`)');
        }
        if (!$ins->hasForeignKeyName('platform_audit_logs', 'fk_pal_sub_plan')) {
            $conn->statement('ALTER TABLE `platform_audit_logs` ADD CONSTRAINT `fk_pal_sub_plan` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT');
        }
        if (!$ins->hasForeignKeyName('platform_audit_logs', 'fk_pal_sub_feature')) {
            $conn->statement('ALTER TABLE `platform_audit_logs` ADD CONSTRAINT `fk_pal_sub_feature` FOREIGN KEY (`subscription_feature_id`) REFERENCES `subscription_features` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT');
        }

        $ins->recordBatchStep('round21_subscription_plans_v1', '500001_subscription_plans_features', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);

        $ins->dropForeignKeyIfExists('platform_audit_logs', 'fk_pal_sub_feature');
        $ins->dropForeignKeyIfExists('platform_audit_logs', 'fk_pal_sub_plan');
        $ins->dropIndexIfExists('platform_audit_logs', 'idx_pal_feature_created');
        $ins->dropIndexIfExists('platform_audit_logs', 'idx_pal_plan_created');
        foreach (['subscription_feature_id', 'subscription_plan_id'] as $col) {
            if ($ins->hasColumn('platform_audit_logs', $col)) {
                $conn->statement("ALTER TABLE `platform_audit_logs` DROP COLUMN `{$col}`");
            }
        }

        $ins->dropForeignKeyIfExists('companies', 'fk_comp_sub_plan');
        $ins->dropIndexIfExists('companies', 'idx_comp_sub_plan');
        if ($ins->hasColumn('companies', 'subscription_plan_id')) {
            $conn->statement('ALTER TABLE `companies` DROP COLUMN `subscription_plan_id`');
        }

        $conn->statement('DROP TABLE IF EXISTS `subscription_plan_features`');
        $conn->statement('DROP TABLE IF EXISTS `subscription_features`');
        $conn->statement('DROP TABLE IF EXISTS `subscription_plans`');

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round21_subscription_plans_v1' AND `phase` = '500001_subscription_plans_features'");
    }
};
