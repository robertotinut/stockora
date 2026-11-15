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
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Migration 400001 refused on non-sandbox DB '{$db}'.");
        }
    }

    public function up(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round20_platform_admin_v1', '400001_platform_admin_lifecycle', 'started', 'create_and_alter');

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `platform_admins` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `email` varchar(255) NOT NULL,
                `password` varchar(255) NOT NULL,
                `is_active` tinyint(1) NOT NULL DEFAULT 1,
                `last_login_at` timestamp NULL DEFAULT NULL,
                `remember_token` varchar(100) NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_platform_admins_email` (`email`),
                KEY `idx_platform_admins_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!$ins->hasColumn('companies', 'account_status')) {
            $conn->statement("
                ALTER TABLE `companies`
                ADD COLUMN `account_status` enum('PENDING_ACTIVATION', 'ACTIVE', 'SUSPENDED', 'EXPIRED', 'CANCELLED') NOT NULL DEFAULT 'ACTIVE' AFTER `is_active`
            ");
        }
        if (!$ins->hasColumn('companies', 'access_type')) {
            $conn->statement("
                ALTER TABLE `companies`
                ADD COLUMN `access_type` enum('FREE', 'STARTER', 'PROFESSIONAL', 'ENTERPRISE') NOT NULL DEFAULT 'FREE' AFTER `account_status`
            ");
        }
        if (!$ins->hasColumn('companies', 'access_starts_at')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `access_starts_at` timestamp NULL DEFAULT NULL AFTER `access_type`');
        }
        if (!$ins->hasColumn('companies', 'access_expires_at')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `access_expires_at` timestamp NULL DEFAULT NULL AFTER `access_starts_at`');
        }
        if (!$ins->hasColumn('companies', 'activated_at')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `activated_at` timestamp NULL DEFAULT NULL AFTER `access_expires_at`');
        }
        if (!$ins->hasColumn('companies', 'activated_by_platform_admin_id')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `activated_by_platform_admin_id` bigint unsigned NULL AFTER `activated_at`');
        }
        if (!$ins->hasColumn('companies', 'suspended_at')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `suspended_at` timestamp NULL DEFAULT NULL AFTER `activated_by_platform_admin_id`');
        }
        if (!$ins->hasColumn('companies', 'suspended_by_platform_admin_id')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `suspended_by_platform_admin_id` bigint unsigned NULL AFTER `suspended_at`');
        }
        if (!$ins->hasColumn('companies', 'status_reason')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `status_reason` varchar(500) NULL AFTER `suspended_by_platform_admin_id`');
        }
        if (!$ins->hasColumn('companies', 'admin_notes')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `admin_notes` text NULL AFTER `status_reason`');
        }

        if (!$ins->hasIndexName('companies', 'idx_comp_status_exp')) {
            $conn->statement('ALTER TABLE `companies` ADD KEY `idx_comp_status_exp` (`account_status`, `access_type`, `access_expires_at`)');
        }
        if (!$ins->hasForeignKeyName('companies', 'fk_comp_activated_admin')) {
            $conn->statement('ALTER TABLE `companies` ADD CONSTRAINT `fk_comp_activated_admin` FOREIGN KEY (`activated_by_platform_admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT');
        }
        if (!$ins->hasForeignKeyName('companies', 'fk_comp_suspended_admin')) {
            $conn->statement('ALTER TABLE `companies` ADD CONSTRAINT `fk_comp_suspended_admin` FOREIGN KEY (`suspended_by_platform_admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT');
        }

        // Preserve existing sandbox tenants as ACTIVE and initialize their baseline lifecycle timestamps.
        $conn->statement("
            UPDATE `companies`
            SET `account_status` = CASE WHEN `is_active` = 1 THEN 'ACTIVE' ELSE 'SUSPENDED' END,
                `access_starts_at` = COALESCE(`access_starts_at`, `created_at`, CURRENT_TIMESTAMP),
                `activated_at` = CASE
                    WHEN `is_active` = 1 AND `activated_at` IS NULL THEN COALESCE(`created_at`, CURRENT_TIMESTAMP)
                    ELSE `activated_at`
                END
            WHERE `access_starts_at` IS NULL
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `platform_audit_logs` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NULL,
                `platform_admin_id` bigint unsigned NULL,
                `actor_user_id` bigint unsigned NULL,
                `action` varchar(64) NOT NULL,
                `previous_values` json NULL,
                `new_values` json NULL,
                `note` text NULL,
                `ip_address` varchar(45) NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_pal_company_created` (`company_id`, `created_at`),
                KEY `idx_pal_action_created` (`action`, `created_at`),
                KEY `idx_pal_admin_created` (`platform_admin_id`, `created_at`),
                CONSTRAINT `fk_pal_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
                CONSTRAINT `fk_pal_platform_admin` FOREIGN KEY (`platform_admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
                CONSTRAINT `fk_pal_actor_user` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ins->recordBatchStep('round20_platform_admin_v1', '400001_platform_admin_lifecycle', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);

        $conn->statement('DROP TABLE IF EXISTS `platform_audit_logs`');

        $ins->dropForeignKeyIfExists('companies', 'fk_comp_suspended_admin');
        $ins->dropForeignKeyIfExists('companies', 'fk_comp_activated_admin');

        if ($ins->hasIndexName('companies', 'idx_comp_status_exp')) {
            $conn->statement('ALTER TABLE `companies` DROP KEY `idx_comp_status_exp`');
        }

        foreach ([
            'admin_notes',
            'status_reason',
            'suspended_by_platform_admin_id',
            'suspended_at',
            'activated_by_platform_admin_id',
            'activated_at',
            'access_expires_at',
            'access_starts_at',
            'access_type',
            'account_status',
        ] as $col) {
            if ($ins->hasColumn('companies', $col)) {
                $conn->statement("ALTER TABLE `companies` DROP COLUMN `{$col}`");
            }
        }

        $conn->statement('DROP TABLE IF EXISTS `platform_admins`');
        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round20_platform_admin_v1' AND `phase` = '400001_platform_admin_lifecycle'");
    }
};
