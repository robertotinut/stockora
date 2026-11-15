<?php

namespace App\Support;

use Illuminate\Database\ConnectionInterface;

class SchemaIntrospector
{
    public function __construct(private ConnectionInterface $conn) {}

    public function schemaName(): string
    {
        return (string) $this->conn->getDatabaseName();
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->conn->getSchemaBuilder()->hasColumn($table, $column);
    }

    public function isColumnNullable(string $table, string $column): bool
    {
        $row = $this->conn->selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->schemaName(), $table, $column]
        );
        return $row && $row->IS_NULLABLE === 'YES';
    }

    public function hasIndexName(string $table, string $indexName): bool
    {
        $row = $this->conn->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$this->schemaName(), $table, $indexName]
        );
        return (int) $row->c > 0;
    }

    public function findIndexByExactColumns(string $table, array $columns, ?bool $uniqueOnly = null): ?string
    {
        $rows = $this->conn->select(
            "SELECT INDEX_NAME, NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols, SUM(CASE WHEN SUB_PART IS NOT NULL THEN 1 ELSE 0 END) AS has_subpart
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             GROUP BY INDEX_NAME, NON_UNIQUE",
            [$this->schemaName(), $table]
        );
        $target = implode(',', $columns);
        foreach ($rows as $r) {
            if ((int) $r->has_subpart > 0) continue;
            if ($uniqueOnly === true && (int) $r->NON_UNIQUE !== 0) continue;
            if ($r->cols === $target) return $r->INDEX_NAME;
        }
        return null;
    }

    public function hasForeignKeySignature(string $table, array $cols, string $refTable, array $refCols, string $onDelete = 'RESTRICT', string $onUpdate = 'RESTRICT'): ?string
    {
        $norm = fn (string $s) => strtoupper(trim($s)) === 'NO ACTION' ? 'RESTRICT' : strtoupper(trim($s));
        $rows = $this->conn->select(
            "SELECT k.CONSTRAINT_NAME,
                    GROUP_CONCAT(k.COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') AS cols,
                    MIN(k.REFERENCED_TABLE_NAME) AS ref_table,
                    GROUP_CONCAT(k.REFERENCED_COLUMN_NAME ORDER BY k.ORDINAL_POSITION SEPARATOR ',') AS ref_cols,
                    MIN(rc.DELETE_RULE) AS delete_rule,
                    MIN(rc.UPDATE_RULE) AS update_rule
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
               ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
             WHERE k.TABLE_SCHEMA = ? AND k.TABLE_NAME = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL
             GROUP BY k.CONSTRAINT_NAME",
            [$this->schemaName(), $table]
        );
        $tc = implode(',', $cols);
        $trc = implode(',', $refCols);
        $wd = $norm($onDelete);
        $wu = $norm($onUpdate);
        foreach ($rows as $r) {
            if ($r->cols === $tc && $r->ref_table === $refTable && $r->ref_cols === $trc && $norm($r->delete_rule) === $wd && $norm($r->update_rule) === $wu) {
                return $r->CONSTRAINT_NAME;
            }
        }
        return null;
    }

    public function hasForeignKeyName(string $table, string $fkName): bool
    {
        $row = $this->conn->selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$this->schemaName(), $table, $fkName]
        );
        return (int) $row->c > 0;
    }

    public function dropForeignKeyIfExists(string $table, string $fkName): void
    {
        if ($this->hasForeignKeyName($table, $fkName)) {
            $this->conn->statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
        }
    }

    public function dropIndexIfExists(string $table, string $idxName): void
    {
        if ($this->hasIndexName($table, $idxName)) {
            $this->conn->statement("ALTER TABLE `{$table}` DROP INDEX `{$idxName}`");
        }
    }

    public function ensureUnique(string $table, string $idxName, array $columns): void
    {
        if ($this->findIndexByExactColumns($table, $columns, true) === null) {
            $colsSql = '`' . implode('`,`', $columns) . '`';
            $this->conn->statement("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$idxName}` ({$colsSql})");
        }
    }

    public function ensureIndex(string $table, string $idxName, array $columns): void
    {
        if ($this->findIndexByExactColumns($table, $columns, null) === null) {
            $colsSql = '`' . implode('`,`', $columns) . '`';
            $this->conn->statement("ALTER TABLE `{$table}` ADD KEY `{$idxName}` ({$colsSql})");
        }
    }

    public function ensureForeignKey(string $table, string $fkName, array $cols, string $refTable, array $refCols, string $onDelete = 'RESTRICT', string $onUpdate = 'RESTRICT'): void
    {
        if ($this->hasForeignKeySignature($table, $cols, $refTable, $refCols, $onDelete, $onUpdate) !== null) {
            return;
        }
        $this->dropForeignKeyIfExists($table, $fkName);
        $colsSql = '`' . implode('`,`', $cols) . '`';
        $refColsSql = '`' . implode('`,`', $refCols) . '`';
        $this->conn->statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$fkName}` FOREIGN KEY ({$colsSql}) REFERENCES `{$refTable}` ({$refColsSql}) ON DELETE {$onDelete} ON UPDATE {$onUpdate}");
    }

    public function ensureCheck(string $table, string $checkName, string $expr): void
    {
        $row = $this->conn->selectOne(
            "SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'CHECK'",
            [$this->schemaName(), $table, $checkName]
        );
        if ((int) $row->c === 0) {
            $this->conn->statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$checkName}` CHECK ({$expr})");
        }
    }

    public function findUnitColumnReferences(): array
    {
        return $this->conn->select(
            "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE k
             WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME = 'units' AND k.REFERENCED_COLUMN_NAME = 'id'",
            [$this->schemaName()]
        );
    }

    public function recordBatchStep(string $batchKey, string $phase, string $status, string $lastStep, ?string $details = null): void
    {
        $this->conn->statement(
            "INSERT INTO migration_batches (batch_key, phase, status, last_step, details, started_at, completed_at)
             VALUES (?, ?, ?, ?, ?, NOW(), IF(? = 'completed', NOW(), NULL))
             ON DUPLICATE KEY UPDATE status = VALUES(status), last_step = VALUES(last_step), details = VALUES(details), completed_at = IF(VALUES(status) = 'completed', NOW(), NULL)",
            [$batchKey, $phase, $status, $lastStep, $details, $status]
        );
    }
}
