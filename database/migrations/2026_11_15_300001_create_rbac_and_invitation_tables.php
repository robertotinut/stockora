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
            throw new RuntimeException("SAFETY ABORT: Migration 300001 refused on non-sandbox DB '{$db}'.");
        }
    }

    public function up(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round19_rbac_v1', '300001_rbac_invitations', 'started', 'create_tables');

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `company_role_permissions` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `role` varchar(50) NOT NULL,
                `module` varchar(64) NOT NULL,
                `action` varchar(32) NOT NULL,
                `is_allowed` tinyint(1) NOT NULL,
                `updated_by_user_id` bigint unsigned NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_crp_company_role_mod_act` (`company_id`, `role`, `module`, `action`),
                KEY `idx_crp_company_role` (`company_id`, `role`),
                CONSTRAINT `fk_crp_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT `fk_crp_updater` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->statement("
            CREATE TABLE IF NOT EXISTS `company_invitations` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `company_id` bigint unsigned NOT NULL,
                `invited_by_user_id` bigint unsigned NOT NULL,
                `email` varchar(255) NOT NULL,
                `role` varchar(50) NOT NULL,
                `token_hash` char(64) NOT NULL,
                `personal_message` text NULL,
                `status` enum('pending', 'accepted', 'revoked') NOT NULL DEFAULT 'pending',
                `expires_at` timestamp NOT NULL,
                `accepted_by_user_id` bigint unsigned NULL,
                `accepted_at` timestamp NULL DEFAULT NULL,
                `revoked_by_user_id` bigint unsigned NULL,
                `revoked_at` timestamp NULL DEFAULT NULL,
                `last_sent_at` timestamp NULL DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ci_token_hash` (`token_hash`),
                KEY `idx_ci_company_email_status` (`company_id`, `email`, `status`),
                KEY `idx_ci_company_status_exp` (`company_id`, `status`, `expires_at`),
                CONSTRAINT `fk_ci_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
                CONSTRAINT `fk_ci_inviter` FOREIGN KEY (`invited_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
                CONSTRAINT `fk_ci_accepter` FOREIGN KEY (`accepted_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
                CONSTRAINT `fk_ci_revoker` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $ins->recordBatchStep('round19_rbac_v1', '300001_rbac_invitations', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $conn->statement('DROP TABLE IF EXISTS `company_invitations`');
        $conn->statement('DROP TABLE IF EXISTS `company_role_permissions`');
        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round19_rbac_v1' AND `phase` = '300001_rbac_invitations'");
    }
};
