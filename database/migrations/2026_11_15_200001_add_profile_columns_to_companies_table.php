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
            throw new RuntimeException("SAFETY ABORT: Migration 200001 refused on non-sandbox DB '{$db}'.");
        }
    }

    public function up(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round18_onboarding_v1', '200001_companies_profile', 'started', 'alter_companies');

        if (!$ins->hasColumn('companies', 'industry')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `industry` varchar(100) NULL AFTER `name`');
        }
        if (!$ins->hasColumn('companies', 'phone')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `phone` varchar(50) NULL AFTER `industry`');
        }
        if (!$ins->hasColumn('companies', 'address')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `address` text NULL AFTER `phone`');
        }
        if (!$ins->hasColumn('companies', 'logo_path')) {
            $conn->statement('ALTER TABLE `companies` ADD COLUMN `logo_path` varchar(255) NULL AFTER `address`');
        }

        $ins->recordBatchStep('round18_onboarding_v1', '200001_companies_profile', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection($this->getConnection());
        $this->guardSandbox($conn);

        $ins = new SchemaIntrospector($conn);

        foreach (['logo_path', 'address', 'phone', 'industry'] as $col) {
            if ($ins->hasColumn('companies', $col)) {
                $conn->statement("ALTER TABLE `companies` DROP COLUMN `{$col}`");
            }
        }

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round18_onboarding_v1' AND `phase` = '200001_companies_profile'");
    }
};
