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
            throw new RuntimeException("SAFETY ABORT: Migration 600001 refused on non-sandbox DB '{$db}'.");
        }
    }

    public function up(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round22_localization_v1', '600001_add_locale_columns', 'started', 'alter_users_and_platform_admins');

        if (!$ins->hasColumn('users', 'locale')) {
            $conn->statement("ALTER TABLE `users` ADD COLUMN `locale` varchar(10) NULL DEFAULT NULL AFTER `email`");
        }

        if (!$ins->hasColumn('platform_admins', 'locale')) {
            $conn->statement("ALTER TABLE `platform_admins` ADD COLUMN `locale` varchar(10) NULL DEFAULT NULL AFTER `email`");
        }

        $ins->recordBatchStep('round22_localization_v1', '600001_add_locale_columns', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);

        if ($ins->hasColumn('platform_admins', 'locale')) {
            $conn->statement('ALTER TABLE `platform_admins` DROP COLUMN `locale`');
        }

        if ($ins->hasColumn('users', 'locale')) {
            $conn->statement('ALTER TABLE `users` DROP COLUMN `locale`');
        }

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round22_localization_v1' AND `phase` = '600001_add_locale_columns'");
    }
};
