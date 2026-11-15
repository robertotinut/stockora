<?php

use App\Support\BcMathHelper;
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
            throw new RuntimeException("SAFETY ABORT: Migration 100008 refused on '{$db}'.");
        }

        $ins = new SchemaIntrospector($conn);
        $ins->recordBatchStep('round14_laravel_incremental_v1', '100008_legacy_reconcile', 'started', 'begin');

        $companyRow = $conn->selectOne("SELECT id FROM `companies` WHERE `code` = 'DEFAULT'");
        if (!$companyRow) {
            throw new RuntimeException('MISSING_DEFAULT_COMPANY');
        }
        $companyId = (int) $companyRow->id;

        $whRow = $conn->selectOne('SELECT id FROM `warehouses` WHERE `company_id` = ? AND `is_default` = 1 ORDER BY id ASC LIMIT 1', [$companyId]);
        if (!$whRow) {
            throw new RuntimeException("MISSING_DEFAULT_WAREHOUSE for company={$companyId}");
        }
        $whId = (int) $whRow->id;

        $cuRow = $conn->selectOne('SELECT user_id FROM `company_user` WHERE `company_id` = ? ORDER BY `is_default` DESC, `user_id` ASC LIMIT 1', [$companyId]);
        if (!$cuRow) {
            throw new RuntimeException("MISSING_COMPANY_USER for company={$companyId}");
        }
        $reconUserId = (int) $cuRow->user_id;

        $conn->transaction(function () use ($conn, $companyId, $whId, $reconUserId): void {
            $products = $conn->select('SELECT id, code, name, stock, purchase_price FROM `products` WHERE `company_id` = ? ORDER BY id ASC FOR UPDATE', [$companyId]);

            foreach ($products as $prod) {
                $pid = (int) $prod->id;
                $legacyStock = BcMathHelper::normalize((string) $prod->stock, 4);
                $unitCost = BcMathHelper::normalize((string) $prod->purchase_price, 6);

                $mvs = $conn->select(
                    "SELECT id, type, direction, quantity, stock_before, stock_after
                     FROM `stock_movements`
                     WHERE `company_id` = ? AND `product_id` = ? AND (`migration_batch_id` IS NULL OR `migration_batch_id` NOT LIKE 'recon-%')
                     ORDER BY id ASC
                     FOR UPDATE",
                    [$companyId, $pid]
                );

                $classification = null;
                $computedOpen = null;
                $blockReason = null;

                if (count($mvs) === 0) {
                    $classification = 'BASELINE_INITIALIZATION';
                    $computedOpen = $legacyStock;
                } else {
                    $breaks = [];
                    $prevAfter = null;
                    $netDelta = '0.0000';

                    foreach ($mvs as $idx => $mv) {
                        $qty = BcMathHelper::normalize((string) $mv->quantity, 4);
                        $before = BcMathHelper::normalize((string) $mv->stock_before, 4);
                        $after = BcMathHelper::normalize((string) $mv->stock_after, 4);
                        $signed = ($mv->direction === 'in') ? $qty : bcmul($qty, '-1', 4);
                        $expectedAfter = bcadd($before, $signed, 4);

                        if (bccomp($expectedAfter, $after, 4) !== 0) {
                            $breaks[] = "Movement #{$mv->id}: {$before} + ({$signed}) != {$after}";
                        }
                        if ($idx > 0 && bccomp($prevAfter, $before, 4) !== 0) {
                            $gap = bcsub($before, $prevAfter, 4);
                            $breaks[] = "Sequential gap before Movement #{$mv->id}: prev stock_after={$prevAfter} != stock_before={$before} (gap={$gap})";
                        }
                        $prevAfter = $after;
                        $netDelta = bcadd($netDelta, $signed, 4);
                    }

                    if (bccomp($prevAfter, $legacyStock, 4) !== 0) {
                        $breaks[] = "Final movement stock_after={$prevAfter} != products.stock={$legacyStock}";
                    }

                    $firstBefore = BcMathHelper::normalize((string) $mvs[0]->stock_before, 4);
                    $expectedOpening = bcsub($legacyStock, $netDelta, 4);
                    if (bccomp($expectedOpening, $firstBefore, 4) !== 0) {
                        $breaks[] = "Calculated opening ({$expectedOpening}) != first movement stock_before ({$firstBefore})";
                    }

                    if ($breaks !== []) {
                        $classification = 'CHAIN_BREAK';
                        $blockReason = implode('; ', $breaks);
                    } else {
                        $classification = 'CHAIN_CONSISTENT';
                        $computedOpen = $firstBefore;
                    }
                }

                $conn->statement(
                    'UPDATE `products` SET `legacy_reconciliation_status` = ? WHERE `company_id` = ? AND `id` = ?',
                    [$classification, $companyId, $pid]
                );

                if ($classification === 'CHAIN_BREAK') {
                    $conn->statement(
                        "INSERT IGNORE INTO `legacy_reconciliation_audits`
                            (`company_id`, `product_id`, `warehouse_id`, `classification`, `legacy_product_stock`, `computed_opening_qty`, `action_taken`, `blocking_reason`, `evidence_json`)
                         VALUES (?, ?, ?, ?, ?, NULL, 'BLOCKED_NEEDS_REVIEW', ?, ?)",
                        [$companyId, $pid, $whId, $classification, $legacyStock, $blockReason, json_encode($mvs)]
                    );

                    $conn->statement(
                        "INSERT INTO `product_warehouses`
                            (`company_id`, `product_id`, `warehouse_id`, `stock`, `moving_average_cost`, `inventory_value`, `valuation_basis`, `last_reconciled_at`)
                         VALUES (?, ?, ?, 0.0000, 0.000000, 0.0000, 'UNVERIFIED_QUARANTINED', NOW())
                         ON DUPLICATE KEY UPDATE
                            `stock` = VALUES(`stock`),
                            `moving_average_cost` = VALUES(`moving_average_cost`),
                            `inventory_value` = VALUES(`inventory_value`),
                            `valuation_basis` = VALUES(`valuation_basis`),
                            `last_reconciled_at` = NOW()",
                        [$companyId, $pid, $whId]
                    );

                    // Do NOT invent valued inventory for quarantined CHAIN_BREAK product
                    $conn->statement(
                        'UPDATE `products` SET `moving_average_cost` = 0.000000, `inventory_value` = 0.0000 WHERE `company_id` = ? AND `id` = ?',
                        [$companyId, $pid]
                    );

                    continue;
                }

                $idemKey = "recon-opening-{$companyId}-{$whId}-prod-{$pid}";
                $existingRecon = $conn->selectOne(
                    'SELECT id FROM `stock_movements` WHERE `company_id` = ? AND `idempotency_key` = ?',
                    [$companyId, $idemKey]
                );

                if (!$existingRecon) {
                    foreach ($mvs as $mv) {
                        $mvTotal = BcMathHelper::roundHalfUp(bcmul(BcMathHelper::normalize((string) $mv->quantity, 4), $unitCost, 10), 4);
                        $conn->statement(
                            "UPDATE `stock_movements`
                             SET `unit_cost` = ?, `total_cost` = ?, `running_stock_after` = `stock_after`, `valuation_basis` = 'ESTIMATED_LEGACY_BASELINE'
                             WHERE `company_id` = ? AND `id` = ?",
                            [$unitCost, $mvTotal, $companyId, (int) $mv->id]
                        );
                    }

                    $openVal = BcMathHelper::roundHalfUp(bcmul($computedOpen, $unitCost, 10), 4);
                    if (bccomp($computedOpen, '0.0000', 4) > 0) {
                        $conn->statement(
                            "INSERT INTO `stock_movements`
                                (`company_id`, `product_id`, `warehouse_id`, `type`, `direction`, `quantity`, `unit_cost`, `total_cost`,
                                 `stock_before`, `stock_after`, `running_stock_after`, `running_value_after`, `valuation_basis`,
                                 `reference_type`, `reference_id`, `user_id`, `notes`, `migration_batch_id`, `idempotency_key`, `created_at`, `updated_at`)
                             VALUES (?, ?, ?, 'opening_balance', 'in', ?, ?, ?, 0.0000, ?, ?, ?, 'ESTIMATED_LEGACY_BASELINE',
                                 'Reconciliation', ?, ?, ?, 'recon-batch-1', ?, NOW(), NOW())",
                            [
                                $companyId, $pid, $whId, $computedOpen, $unitCost, $openVal,
                                $computedOpen, $computedOpen, $openVal, $pid, $reconUserId,
                                "Reconciled {$classification} opening balance baseline", $idemKey,
                            ]
                        );
                    }

                    $finalVal = BcMathHelper::roundHalfUp(bcmul($legacyStock, $unitCost, 10), 4);
                    $conn->statement(
                        "INSERT INTO `product_warehouses`
                            (`company_id`, `product_id`, `warehouse_id`, `stock`, `moving_average_cost`, `inventory_value`, `valuation_basis`, `last_reconciled_at`)
                         VALUES (?, ?, ?, ?, ?, ?, 'ESTIMATED_LEGACY_BASELINE', NOW())
                         ON DUPLICATE KEY UPDATE
                            `stock` = VALUES(`stock`),
                            `moving_average_cost` = VALUES(`moving_average_cost`),
                            `inventory_value` = VALUES(`inventory_value`),
                            `valuation_basis` = VALUES(`valuation_basis`),
                            `last_reconciled_at` = NOW()",
                        [$companyId, $pid, $whId, $legacyStock, $unitCost, $finalVal]
                    );

                    $conn->statement(
                        'UPDATE `products` SET `moving_average_cost` = ?, `inventory_value` = ? WHERE `company_id` = ? AND `id` = ?',
                        [$unitCost, $finalVal, $companyId, $pid]
                    );
                }

                $conn->statement(
                    "INSERT IGNORE INTO `legacy_reconciliation_audits`
                        (`company_id`, `product_id`, `warehouse_id`, `classification`, `legacy_product_stock`, `computed_opening_qty`, `action_taken`, `blocking_reason`, `evidence_json`)
                     VALUES (?, ?, ?, ?, ?, ?, 'AUTO_BACKFILLED', NULL, ?)",
                    [$companyId, $pid, $whId, $classification, $legacyStock, $computedOpen, json_encode($mvs)]
                );
            }
        });

        $ins->recordBatchStep('round14_laravel_incremental_v1', '100008_legacy_reconcile', 'completed', 'done');
    }

    public function down(): void
    {
        $conn = DB::connection();
        $db = $conn->selectOne('SELECT DATABASE() AS db')->db ?? '';
        if ($db !== 'stockora_sandbox') {
            throw new RuntimeException("SAFETY ABORT: Rollback 100008 refused on '{$db}'.");
        }

        // Reverting reconciliation NEVER deletes any existing legacy stock_movements or products rows
        $conn->transaction(function () use ($conn): void {
            $conn->statement("DELETE FROM `stock_movements` WHERE `migration_batch_id` LIKE 'recon-%'");
            $conn->statement("UPDATE `stock_movements` SET `unit_cost` = 0.000000, `total_cost` = 0.0000, `running_stock_after` = NULL, `running_value_after` = NULL");
            $conn->statement('DELETE FROM `legacy_reconciliation_audits`');
            $conn->statement("DELETE FROM `product_warehouses` WHERE `valuation_basis` IN ('ESTIMATED_LEGACY_BASELINE', 'UNVERIFIED_QUARANTINED')");
            $conn->statement("UPDATE `products` SET `legacy_reconciliation_status` = 'PENDING', `moving_average_cost` = 0.000000, `inventory_value` = 0.0000");
        });

        $conn->statement("DELETE FROM `migration_batches` WHERE `batch_key` = 'round14_laravel_incremental_v1' AND `phase` = '100008_legacy_reconcile'");
    }
};
