<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;
use RuntimeException;

class DatabaseSafetyGuard
{
    public const SANDBOX_DATABASE = 'stockora_sandbox';

    /**
     * Enforce environment-aware database safety rules during application boot.
     *
     * Non-production environments (`local`, `testing`, `staging`, etc.) remain
     * strictly locked to `stockora_sandbox`. Production (`production`) fails closed
     * unless explicit opt-in flags, matching approved database name, and release
     * approval token are all intentionally configured.
     */
    public static function enforce(?string $environment = null): void
    {
        $env = $environment ?? (string) Config::get('app.env', 'production');
        $defaultConnection = (string) Config::get('database.default', 'mysql');
        $defaultDb = trim((string) Config::get("database.connections.{$defaultConnection}.database", ''));

        if ($defaultDb === '') {
            throw new RuntimeException(
                "DATABASE SAFETY VIOLATION: Default connection '{$defaultConnection}' has no configured database."
            );
        }

        if ($env !== 'production') {
            if ($defaultDb !== self::SANDBOX_DATABASE) {
                throw new RuntimeException(
                    "SANDBOX SAFETY VIOLATION: Refusing to boot environment '{$env}' on database '{$defaultDb}'. Only '" . self::SANDBOX_DATABASE . "' is allowed."
                );
            }

            if ($defaultConnection === 'mysql') {
                $migratorDb = trim((string) Config::get('database.connections.mysql_migrator.database', ''));
                if ($migratorDb !== '' && $migratorDb !== self::SANDBOX_DATABASE) {
                    throw new RuntimeException(
                        "SANDBOX SAFETY VIOLATION: Refusing to boot environment '{$env}' with mysql_migrator database '{$migratorDb}'."
                    );
                }
            }

            return;
        }

        self::enforceProductionGate($defaultConnection, $defaultDb);
    }

    /**
     * Validate an active runtime MySQL connection database name against active guard policy.
     */
    public static function assertConnectedDatabase(
        string $connectedDb,
        string $context = 'database operation',
        ?string $environment = null
    ): void {
        $env = $environment ?? (string) Config::get('app.env', 'production');
        self::enforce($env);

        $trimmed = trim($connectedDb);
        $defaultConnection = (string) Config::get('database.default', 'mysql');
        $configuredDb = trim((string) Config::get("database.connections.{$defaultConnection}.database", ''));

        if ($trimmed === '' || $trimmed !== $configuredDb) {
            throw new RuntimeException(
                "DATABASE SAFETY ABORT ({$context}): Active database '{$trimmed}' does not match configured database '{$configuredDb}'."
            );
        }

        if ($env !== 'production' && $trimmed !== self::SANDBOX_DATABASE) {
            throw new RuntimeException(
                "SAFETY ABORT ({$context}): Refused non-sandbox DB '{$trimmed}' in '{$env}' environment."
            );
        }
    }

    private static function enforceProductionGate(string $defaultConnection, string $defaultDb): void
    {
        $allowProduction = Config::get('stockora.database_guard.allow_production_db');
        $approvedDb = trim((string) Config::get('stockora.database_guard.approved_production_database', ''));
        $approvalToken = trim((string) Config::get('stockora.database_guard.production_approval_token', ''));

        if ($allowProduction !== true) {
            throw new RuntimeException(
                'PRODUCTION DB GATE LOCKED: Intentional production release approval is required (STOCKORA_ALLOW_PRODUCTION_DB=true).'
            );
        }

        if ($approvedDb === '' || $approvedDb === self::SANDBOX_DATABASE) {
            throw new RuntimeException(
                'PRODUCTION DB GATE LOCKED: STOCKORA_APPROVED_PRODUCTION_DATABASE must name a non-sandbox production database.'
            );
        }

        if ($defaultDb === self::SANDBOX_DATABASE || $defaultDb !== $approvedDb) {
            throw new RuntimeException(
                "PRODUCTION DB GATE VIOLATION: Configured DB_DATABASE '{$defaultDb}' does not match approved database '{$approvedDb}'."
            );
        }

        if (!self::isValidApprovalToken($approvalToken)) {
            throw new RuntimeException(
                'PRODUCTION DB GATE LOCKED: STOCKORA_PRODUCTION_DB_APPROVAL must hold an explicit non-placeholder approval reference (min 12 chars).'
            );
        }

        if ($defaultConnection === 'mysql') {
            $migratorDb = trim((string) Config::get('database.connections.mysql_migrator.database', ''));
            if ($migratorDb !== '' && $migratorDb !== $approvedDb) {
                throw new RuntimeException(
                    "PRODUCTION DB GATE VIOLATION: mysql_migrator database '{$migratorDb}' does not match approved database '{$approvedDb}'."
                );
            }
        }
    }

    private static function isValidApprovalToken(string $token): bool
    {
        if (strlen($token) < 12) {
            return false;
        }

        $lower = strtolower($token);
        foreach (['changeme', 'replace_me', 'placeholder', 'example', 'todo', 'pending', 'false', 'null'] as $disallowed) {
            if (str_contains($lower, $disallowed)) {
                return false;
            }
        }

        return true;
    }
}
