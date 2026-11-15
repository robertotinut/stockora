<?php

namespace App\Services;

use App\Support\BcMathHelper;
use App\Support\DatabaseSafetyGuard;
use DomainException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use RuntimeException;
use Throwable;

class InventoryService
{
    /**
     * Test-only failure injection hook for Section 5 atomic rollback verification.
     * Allowed values: null | 'during_ledger_insert' | 'during_warehouse_update' | 'after_status_posted'
     */
    public static ?string $simulateFailureAt = null;

    private function conn(): ConnectionInterface
    {
        $conn = DB::connection('mysql');
        $db = (string) ($conn->selectOne('SELECT DATABASE() AS db')->db ?? '');
        DatabaseSafetyGuard::assertConnectedDatabase($db, 'InventoryService');

        return $conn;
    }

    private function assertMovementQuota(ConnectionInterface $conn, int $companyId, int $addedMovements): void
    {
        if ($addedMovements <= 0) {
            return;
        }

        app(SubscriptionPlanService::class)->assertCanCreateQuota(
            $companyId,
            'max_monthly_stock_movements',
            $addedMovements,
            $conn
        );
    }

    private function resolveDocumentNumber(
        ConnectionInterface $conn,
        int $companyId,
        string $docType,
        ?string $explicitNumber
    ): string {
        $clean = $explicitNumber !== null ? strtoupper(trim($explicitNumber)) : '';

        return $clean !== ''
            ? $clean
            : app(DocumentNumberService::class)->generateNextNumber($companyId, $docType, null, $conn);
    }

    private function maybeFail(string $stage): void
    {
        if (self::$simulateFailureAt === $stage) {
            throw new RuntimeException("SIMULATED_FAILURE_AT_{$stage}: Forced rollback verification.");
        }
    }

    private function runWithRetry(callable $fn, int $maxAttempts = 4): array
    {
        $conn = $this->conn();
        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                $result = $conn->transaction(fn () => $fn($conn));
                $result['attempts'] = $attempt;
                $result['retried'] = $attempt > 1;

                return $result;
            } catch (Throwable $e) {
                $code = $this->extractMysqlErrorCode($e);
                if (in_array($code, [1213, 1205], true) && $attempt < $maxAttempts) {
                    usleep(50000);
                    continue;
                }
                throw $e;
            }
        }
    }

    private function extractMysqlErrorCode(Throwable $e): int
    {
        if ($e instanceof QueryException || $e instanceof PDOException) {
            return (int) ($e->errorInfo[1] ?? 0);
        }
        $prev = $e->getPrevious();
        if ($prev instanceof PDOException) {
            return (int) ($prev->errorInfo[1] ?? 0);
        }

        return 0;
    }

    private function assertTenantAndMembership(ConnectionInterface $conn, int $companyId, int $userId): void
    {
        $row = $conn->selectOne(
            'SELECT c.is_active AS comp_active, cu.is_active AS member_active
             FROM `companies` c
             JOIN `company_user` cu ON cu.company_id = c.id AND cu.user_id = ?
             WHERE c.id = ?
             FOR SHARE',
            [$userId, $companyId]
        );
        if (!$row || (int) $row->comp_active !== 1 || (int) $row->member_active !== 1) {
            throw new DomainException("UNAUTHORIZED_TENANT_ACCESS: company={$companyId}, user={$userId}");
        }
    }

    private function assertActiveWarehouse(ConnectionInterface $conn, int $companyId, int $warehouseId): void
    {
        $wh = $conn->selectOne(
            'SELECT id FROM `warehouses` WHERE `company_id` = ? AND `id` = ? AND `is_active` = 1 FOR SHARE',
            [$companyId, $warehouseId]
        );
        if (!$wh) {
            throw new DomainException("INVALID_WAREHOUSE: company={$companyId}, warehouse={$warehouseId}");
        }
    }

    private function assertProductNotQuarantined(object $product, array $pw): void
    {
        if ($pw['valuation_basis'] === 'UNVERIFIED_QUARANTINED' || $product->legacy_reconciliation_status === 'CHAIN_BREAK') {
            throw new DomainException("QUARANTINED_CHAIN_BREAK_PRODUCT: Product #{$product->id} ({$product->code}) is quarantined awaiting reconciliation resolution.");
        }
    }

    private function resolveConversionFactor(ConnectionInterface $conn, int $companyId, int $productId, int $fromUnitId, int $baseUnitId): string
    {
        if ($fromUnitId === $baseUnitId) {
            return '1.00000000';
        }
        $row = $conn->selectOne(
            'SELECT factor FROM `unit_conversions`
             WHERE `company_id` = ? AND `from_unit_id` = ? AND `to_unit_id` = ? AND `is_active` = 1
               AND (`product_id` = ? OR `product_id` IS NULL)
             ORDER BY CASE WHEN `product_id` IS NULL THEN 1 ELSE 0 END ASC
             LIMIT 1
             FOR SHARE',
            [$companyId, $fromUnitId, $baseUnitId, $productId]
        );
        if (!$row) {
            throw new DomainException("INVALID_CONVERSION_FACTOR: product={$productId}, from={$fromUnitId} -> to={$baseUnitId}");
        }
        $factor = BcMathHelper::normalize((string) $row->factor, 8);
        BcMathHelper::assertPositive($factor, 'conversion_factor', 8);

        return $factor;
    }

    private function lockOrCreateProductWarehouse(ConnectionInterface $conn, int $companyId, int $productId, int $warehouseId): array
    {
        $fetch = function () use ($conn, $companyId, $productId, $warehouseId) {
            return $conn->selectOne(
                'SELECT * FROM `product_warehouses` WHERE `company_id` = ? AND `product_id` = ? AND `warehouse_id` = ? FOR UPDATE',
                [$companyId, $productId, $warehouseId]
            );
        };

        $row = $fetch();
        if (!$row) {
            $conn->statement(
                "INSERT IGNORE INTO `product_warehouses` (`company_id`, `product_id`, `warehouse_id`, `stock`, `moving_average_cost`, `inventory_value`, `valuation_basis`)
                 VALUES (?, ?, ?, 0.0000, 0.000000, 0.0000, 'VERIFIED_ACQUISITION')",
                [$companyId, $productId, $warehouseId]
            );
            $row = $fetch();
        }

        return (array) $row;
    }

    private function mergeValuationBasis(string $currentBasis, string $oldStock, string $incomingBasis): string
    {
        if ($incomingBasis === 'REVERSAL_CARRIED_COST') {
            return $currentBasis;
        }
        if (bccomp($oldStock, '0.0000', 4) === 0) {
            return $incomingBasis;
        }
        if ($currentBasis === $incomingBasis) {
            return $currentBasis;
        }

        return 'MIXED_BASELINE_AND_ACQUISITION';
    }

    private function syncProductRollup(ConnectionInterface $conn, int $companyId, int $productId): void
    {
        $rows = $conn->select(
            "SELECT stock, inventory_value FROM `product_warehouses` WHERE `company_id` = ? AND `product_id` = ? AND `valuation_basis` <> 'UNVERIFIED_QUARANTINED'",
            [$companyId, $productId]
        );
        $sumStock = '0.0000';
        $sumVal = '0.0000';
        foreach ($rows as $r) {
            $sumStock = bcadd($sumStock, BcMathHelper::normalize((string) $r->stock, 4), 4);
            $sumVal = bcadd($sumVal, BcMathHelper::normalize((string) $r->inventory_value, 4), 4);
        }
        $mac = bccomp($sumStock, '0.0000', 4) > 0
            ? BcMathHelper::roundHalfUp(bcdiv($sumVal, $sumStock, 10), 6)
            : '0.000000';

        BcMathHelper::assertFitsDecimalCapacity($sumStock, 16, 4, 'products.stock');
        BcMathHelper::assertFitsDecimalCapacity($sumVal, 28, 4, 'products.inventory_value');
        BcMathHelper::assertFitsDecimalCapacity($mac, 20, 6, 'products.moving_average_cost');

        $conn->statement(
            'UPDATE `products` SET `stock` = ?, `inventory_value` = ?, `moving_average_cost` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
            [$sumStock, $sumVal, $mac, $companyId, $productId]
        );
    }

    private function applyWarehouseInbound(
        ConnectionInterface $conn,
        int $companyId,
        int $userId,
        int $productId,
        int $warehouseId,
        array &$pw,
        string $baseQty,
        string $unitCost,
        string $totalCost,
        string $movementType,
        string $movementBasis,
        string $refType,
        int $refId,
        string $smIdem,
        string $notes,
        bool $allowIntoQuarantined = false
    ): int {
        if (!$allowIntoQuarantined && $pw['valuation_basis'] === 'UNVERIFIED_QUARANTINED') {
            throw new DomainException("QUARANTINED_CHAIN_BREAK_PRODUCT: Warehouse state is quarantined for product={$productId}.");
        }

        $oldStock = BcMathHelper::normalize((string) $pw['stock'], 4);
        $oldValue = BcMathHelper::normalize((string) $pw['inventory_value'], 4);
        $newStock = bcadd($oldStock, $baseQty, 4);
        $newValue = bcadd($oldValue, $totalCost, 4);
        $newMac = bccomp($newStock, '0.0000', 4) > 0
            ? BcMathHelper::roundHalfUp(bcdiv($newValue, $newStock, 10), 6)
            : '0.000000';
        $newBasis = $this->mergeValuationBasis((string) $pw['valuation_basis'], $oldStock, $movementBasis);

        BcMathHelper::assertFitsDecimalCapacity($baseQty, 16, 4, 'quantity');
        BcMathHelper::assertFitsDecimalCapacity($unitCost, 20, 6, 'unit_cost');
        BcMathHelper::assertFitsDecimalCapacity($totalCost, 28, 4, 'total_cost');
        BcMathHelper::assertFitsDecimalCapacity($newStock, 16, 4, 'stock_after');
        BcMathHelper::assertFitsDecimalCapacity($newValue, 28, 4, 'inventory_value');

        $conn->statement(
            "INSERT INTO `stock_movements`
                (`company_id`, `product_id`, `warehouse_id`, `type`, `direction`, `quantity`, `unit_cost`, `total_cost`,
                 `stock_before`, `stock_after`, `running_stock_after`, `running_value_after`, `valuation_basis`,
                 `reference_type`, `reference_id`, `user_id`, `notes`, `idempotency_key`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, 'in', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $companyId, $productId, $warehouseId, $movementType,
                $baseQty, $unitCost, $totalCost,
                $oldStock, $newStock, $newStock, $newValue, $movementBasis,
                $refType, $refId, $userId, $notes, $smIdem,
            ]
        );
        $smId = (int) $conn->getPdo()->lastInsertId();
        $this->maybeFail('during_ledger_insert');

        $pw['stock'] = $newStock;
        $pw['inventory_value'] = $newValue;
        $pw['moving_average_cost'] = $newMac;
        $pw['valuation_basis'] = $newBasis;

        $conn->statement(
            'UPDATE `product_warehouses` SET `stock` = ?, `moving_average_cost` = ?, `inventory_value` = ?, `valuation_basis` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
            [$newStock, $newMac, $newValue, $newBasis, $companyId, (int) $pw['id']]
        );
        $this->maybeFail('during_warehouse_update');

        return $smId;
    }

    private function applyWarehouseOutbound(
        ConnectionInterface $conn,
        int $companyId,
        int $userId,
        int $productId,
        int $warehouseId,
        array &$pw,
        string $baseQty,
        string $movementType,
        string $movementBasis,
        string $refType,
        int $refId,
        string $smIdem,
        string $notes,
        ?string $forcedTotalCost = null,
        ?string $forcedUnitCost = null
    ): array {
        if ($pw['valuation_basis'] === 'UNVERIFIED_QUARANTINED') {
            throw new DomainException("QUARANTINED_CHAIN_BREAK_PRODUCT: Product #{$productId} cannot issue quarantined stock.");
        }

        $oldStock = BcMathHelper::normalize((string) $pw['stock'], 4);
        $oldValue = BcMathHelper::normalize((string) $pw['inventory_value'], 4);
        $oldMac = BcMathHelper::normalize((string) $pw['moving_average_cost'], 6);

        if (bccomp($oldStock, $baseQty, 4) < 0) {
            throw new DomainException("INSUFFICIENT_STOCK: product={$productId}, warehouse={$warehouseId}, avail={$oldStock}, req={$baseQty}");
        }

        $newStock = bcsub($oldStock, $baseQty, 4);
        if ($forcedTotalCost !== null) {
            $outCost = bccomp($forcedTotalCost, $oldValue, 4) > 0 ? $oldValue : $forcedTotalCost;
            $unitCostSnap = $forcedUnitCost ?? BcMathHelper::roundHalfUp(bcdiv($outCost, $baseQty, 10), 6);
        } else {
            $nominal = BcMathHelper::roundHalfUp(bcmul($baseQty, $oldMac, 10), 4);
            $outCost = bccomp($nominal, $oldValue, 4) > 0 ? $oldValue : $nominal;
            $unitCostSnap = $oldMac;
        }
        $afterMoveVal = bcsub($oldValue, $outCost, 4);

        $residualDelta = '0.0000';
        $finalVal = $afterMoveVal;
        $finalMac = bccomp($newStock, '0.0000', 4) > 0 ? $oldMac : '0.000000';
        if (bccomp($newStock, '0.0000', 4) === 0) {
            if (bccomp($afterMoveVal, '0.0000', 4) !== 0) {
                $residualDelta = bcsub('0.0000', $afterMoveVal, 4);
            }
            $finalVal = '0.0000';
            $finalMac = '0.000000';
        } elseif (bccomp($newStock, '0.0000', 4) > 0 && $forcedTotalCost !== null) {
            $finalMac = BcMathHelper::roundHalfUp(bcdiv($finalVal, $newStock, 10), 6);
        }

        $conn->statement(
            "INSERT INTO `stock_movements`
                (`company_id`, `product_id`, `warehouse_id`, `type`, `direction`, `quantity`, `unit_cost`, `total_cost`,
                 `stock_before`, `stock_after`, `running_stock_after`, `running_value_after`, `valuation_basis`,
                 `reference_type`, `reference_id`, `user_id`, `notes`, `idempotency_key`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, 'out', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $companyId, $productId, $warehouseId, $movementType,
                $baseQty, $unitCostSnap, $outCost,
                $oldStock, $newStock, $newStock, $afterMoveVal, $movementBasis,
                $refType, $refId, $userId, $notes, $smIdem,
            ]
        );
        $smId = (int) $conn->getPdo()->lastInsertId();
        $this->maybeFail('during_ledger_insert');

        if (bccomp($residualDelta, '0.0000', 4) !== 0) {
            $conn->statement(
                "INSERT INTO `inventory_valuation_adjustments`
                    (`company_id`, `product_id`, `warehouse_id`, `stock_movement_id`, `adjustment_type`,
                     `adjustment_delta`, `value_before`, `value_after`, `reason`, `idempotency_key`, `created_by_user_id`)
                 VALUES (?, ?, ?, ?, 'depletion_residual', ?, ?, '0.0000', ?, ?, ?)",
                [
                    $companyId, $productId, $warehouseId, $smId,
                    $residualDelta, $afterMoveVal,
                    "Final depletion residual flush ({$refType} #{$refId})",
                    "iva-depl-{$refType}-{$smId}", $userId,
                ]
            );
        }

        $pw['stock'] = $newStock;
        $pw['inventory_value'] = $finalVal;
        $pw['moving_average_cost'] = $finalMac;

        $conn->statement(
            'UPDATE `product_warehouses` SET `stock` = ?, `moving_average_cost` = ?, `inventory_value` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
            [$newStock, $finalMac, $finalVal, $companyId, (int) $pw['id']]
        );
        $this->maybeFail('during_warehouse_update');

        return [
            'movement_id' => $smId,
            'unit_cost' => $unitCostSnap,
            'total_cost' => $outCost,
            'residual_delta' => $residualDelta,
        ];
    }

    public function postPurchaseReceipt(
        int $companyId,
        int $userId,
        int $purchaseId,
        int $warehouseId,
        ?string $receiptNumber,
        string $idempotencyKey,
        array $lines,
        string $notes = ''
    ): array {
        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $purchaseId, $warehouseId, $receiptNumber, $idempotencyKey, $lines, $notes): array {
            $po = $conn->selectOne('SELECT * FROM `purchases` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, $purchaseId]);
            if (!$po) {
                throw new DomainException("PURCHASE_NOT_FOUND: company={$companyId}, po={$purchaseId}");
            }

            $this->assertTenantAndMembership($conn, $companyId, $userId);
            $this->assertActiveWarehouse($conn, $companyId, $warehouseId);

            $existing = $conn->selectOne(
                'SELECT id, receipt_number, status, total_capitalized_cost FROM `purchase_receipts` WHERE `company_id` = ? AND `idempotency_key` = ? FOR SHARE',
                [$companyId, $idempotencyKey]
            );
            if ($existing) {
                return ['idempotent_replay' => true, 'receipt' => (array) $existing];
            }

            if (!in_array($po->status, ['pending', 'ordered', 'partial'], true)) {
                throw new DomainException("PO_NOT_RECEIVABLE: status={$po->status}");
            }
            if ($lines === []) {
                throw new DomainException('EMPTY_RECEIPT_LINES');
            }

            $this->assertMovementQuota($conn, $companyId, count($lines));

            $poItems = [];
            foreach ($conn->select('SELECT * FROM `purchase_items` WHERE `company_id` = ? AND `purchase_id` = ? ORDER BY id ASC FOR UPDATE', [$companyId, $purchaseId]) as $pi) {
                $poItems[(int) $pi->id] = $pi;
            }

            $productIds = [];
            foreach ($lines as $l) {
                $piId = (int) $l['purchase_item_id'];
                if (!isset($poItems[$piId])) {
                    throw new DomainException("PURCHASE_ITEM_NOT_IN_PO: po={$purchaseId}, item={$piId}");
                }
                $productIds[] = (int) $poItems[$piId]->product_id;
            }
            $productIds = array_values(array_unique($productIds));
            sort($productIds);

            $productsMap = [];
            $pwMap = [];
            foreach ($productIds as $pid) {
                $productsMap[$pid] = $conn->selectOne('SELECT * FROM `products` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, $pid]);
                $pwMap[$pid] = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $warehouseId);
                $this->assertProductNotQuarantined($productsMap[$pid], $pwMap[$pid]);
            }

            $alreadyPostedByItem = [];
            $priorRows = $conn->select(
                "SELECT pri.purchase_item_id, pri.received_quantity
                 FROM `purchase_receipt_items` pri
                 JOIN `purchase_receipts` pr ON pr.company_id = pri.company_id AND pr.id = pri.purchase_receipt_id
                 WHERE pri.company_id = ? AND pri.purchase_id = ? AND pr.status = 'posted'
                 FOR SHARE",
                [$companyId, $purchaseId]
            );
            foreach ($priorRows as $r) {
                $iid = (int) $r->purchase_item_id;
                $alreadyPostedByItem[$iid] = bcadd($alreadyPostedByItem[$iid] ?? '0.0000', BcMathHelper::normalize((string) $r->received_quantity, 4), 4);
            }

            $preparedLines = [];
            $totalGross = '0.0000';
            $totalDisc = '0.0000';
            $totalFreight = '0.0000';
            $totalNonRecTax = '0.0000';
            $totalRecTax = '0.0000';
            $totalCapCost = '0.0000';
            $batchQtyByItem = [];

            foreach ($lines as $l) {
                $piId = (int) $l['purchase_item_id'];
                $pi = $poItems[$piId];
                $pid = (int) $pi->product_id;
                $baseUnitId = (int) $productsMap[$pid]->unit_id;
                $recvUnitId = (int) ($l['received_unit_id'] ?? $baseUnitId);

                $recvQty = BcMathHelper::normalize((string) $l['received_quantity'], 4);
                BcMathHelper::assertPositive($recvQty, 'received_quantity', 4);

                $priorPosted = $alreadyPostedByItem[$piId] ?? '0.0000';
                $batchSoFar = $batchQtyByItem[$piId] ?? '0.0000';
                $poQty = BcMathHelper::normalize((string) $pi->quantity, 4);
                $newCumul = bcadd(bcadd($priorPosted, $batchSoFar, 4), $recvQty, 4);
                if (bccomp($newCumul, $poQty, 4) > 0) {
                    throw new DomainException("OVER_RECEIVING_BLOCKED: item={$piId}, ordered={$poQty}, already_posted={$priorPosted}, attempted={$newCumul}");
                }
                $batchQtyByItem[$piId] = bcadd($batchSoFar, $recvQty, 4);

                $factor = $this->resolveConversionFactor($conn, $companyId, $pid, $recvUnitId, $baseUnitId);
                $baseQty = BcMathHelper::roundHalfUp(bcmul($recvQty, $factor, 10), 4);
                BcMathHelper::assertPositive($baseQty, 'base_quantity', 4);

                $unitPrice = BcMathHelper::normalize((string) ($l['unit_price'] ?? $pi->unit_price), 6);
                $discAmt = BcMathHelper::normalize((string) ($l['discount_amount'] ?? '0'), 4);
                $freightAmt = BcMathHelper::normalize((string) ($l['allocated_freight_amount'] ?? '0'), 4);
                $nonRecTax = BcMathHelper::normalize((string) ($l['non_recoverable_tax_amount'] ?? '0'), 4);
                $recTax = BcMathHelper::normalize((string) ($l['recoverable_tax_amount'] ?? '0'), 4);
                BcMathHelper::assertNonNegative($unitPrice, 'unit_price', 6);
                BcMathHelper::assertNonNegative($discAmt, 'discount_amount', 4);
                BcMathHelper::assertNonNegative($freightAmt, 'allocated_freight_amount', 4);
                BcMathHelper::assertNonNegative($nonRecTax, 'non_recoverable_tax_amount', 4);
                BcMathHelper::assertNonNegative($recTax, 'recoverable_tax_amount', 4);

                $gross = BcMathHelper::roundHalfUp(bcmul($recvQty, $unitPrice, 10), 4);
                $netAfterDisc = bcsub($gross, $discAmt, 4);
                BcMathHelper::assertNonNegative($netAfterDisc, 'net_after_discount', 4);
                $capCost = bcadd(bcadd($netAfterDisc, $freightAmt, 4), $nonRecTax, 4);
                $unitCostBase = BcMathHelper::roundHalfUp(bcdiv($capCost, $baseQty, 10), 6);

                $totalGross = bcadd($totalGross, $gross, 4);
                $totalDisc = bcadd($totalDisc, $discAmt, 4);
                $totalFreight = bcadd($totalFreight, $freightAmt, 4);
                $totalNonRecTax = bcadd($totalNonRecTax, $nonRecTax, 4);
                $totalRecTax = bcadd($totalRecTax, $recTax, 4);
                $totalCapCost = bcadd($totalCapCost, $capCost, 4);

                $preparedLines[] = [
                    'purchase_item_id' => $piId,
                    'product_id' => $pid,
                    'received_unit_id' => $recvUnitId,
                    'base_unit_id' => $baseUnitId,
                    'conversion_factor' => $factor,
                    'received_quantity' => $recvQty,
                    'base_quantity' => $baseQty,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discAmt,
                    'allocated_freight_amount' => $freightAmt,
                    'non_recoverable_tax_amount' => $nonRecTax,
                    'recoverable_tax_amount' => $recTax,
                    'capitalized_total_cost' => $capCost,
                    'unit_cost_base' => $unitCostBase,
                ];
            }

            $receiptNumber = $this->resolveDocumentNumber($conn, $companyId, DocumentNumberService::TYPE_GRN, $receiptNumber);

            $conn->statement(
                "INSERT INTO `purchase_receipts`
                    (`company_id`, `purchase_id`, `warehouse_id`, `receipt_number`, `status`, `received_by_user_id`,
                     `total_gross_amount`, `total_discount_amount`, `total_freight_amount`,
                     `total_non_recoverable_tax`, `total_recoverable_tax`, `total_capitalized_cost`,
                     `idempotency_key`, `notes`, `created_at`, `updated_at`)
                 VALUES (?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $companyId, $purchaseId, $warehouseId, $receiptNumber, $userId,
                    $totalGross, $totalDisc, $totalFreight, $totalNonRecTax, $totalRecTax, $totalCapCost,
                    $idempotencyKey, $notes,
                ]
            );
            $receiptId = (int) $conn->getPdo()->lastInsertId();

            foreach ($preparedLines as $pl) {
                $conn->statement(
                    'INSERT INTO `purchase_receipt_items`
                        (`company_id`, `purchase_receipt_id`, `purchase_id`, `purchase_item_id`, `product_id`,
                         `received_unit_id`, `base_unit_id`, `conversion_factor`, `received_quantity`, `base_quantity`,
                         `unit_price`, `discount_amount`, `allocated_freight_amount`, `non_recoverable_tax_amount`,
                         `recoverable_tax_amount`, `capitalized_total_cost`, `unit_cost_base`, `created_at`, `updated_at`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $companyId, $receiptId, $purchaseId, $pl['purchase_item_id'], $pl['product_id'],
                        $pl['received_unit_id'], $pl['base_unit_id'], $pl['conversion_factor'],
                        $pl['received_quantity'], $pl['base_quantity'], $pl['unit_price'],
                        $pl['discount_amount'], $pl['allocated_freight_amount'], $pl['non_recoverable_tax_amount'],
                        $pl['recoverable_tax_amount'], $pl['capitalized_total_cost'], $pl['unit_cost_base'],
                    ]
                );
                $priId = (int) $conn->getPdo()->lastInsertId();

                $pid = $pl['product_id'];
                $this->applyWarehouseInbound(
                    $conn, $companyId, $userId, $pid, $warehouseId, $pwMap[$pid],
                    $pl['base_quantity'], $pl['unit_cost_base'], $pl['capitalized_total_cost'],
                    'in', 'VERIFIED_ACQUISITION', 'PurchaseReceipt', $receiptId,
                    "grn-{$companyId}-{$receiptId}-item-{$priId}",
                    "GRN {$receiptNumber} line #{$priId}"
                );
            }

            $conn->statement(
                "UPDATE `purchase_receipts` SET `status` = 'posted', `posted_at` = NOW(), `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?",
                [$companyId, $receiptId]
            );
            $this->maybeFail('after_status_posted');

            $allReceived = true;
            foreach ($poItems as $piId => $pi) {
                $newRecv = bcadd($alreadyPostedByItem[$piId] ?? '0.0000', $batchQtyByItem[$piId] ?? '0.0000', 4);
                $conn->statement(
                    'UPDATE `purchase_items` SET `received_quantity` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
                    [$newRecv, $companyId, $piId]
                );
                if (bccomp($newRecv, BcMathHelper::normalize((string) $pi->quantity, 4), 4) < 0) {
                    $allReceived = false;
                }
            }
            $poStatus = $allReceived ? 'received' : 'partial';
            $conn->statement(
                'UPDATE `purchases` SET `status` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
                [$poStatus, $companyId, $purchaseId]
            );

            foreach ($productIds as $pid) {
                $this->syncProductRollup($conn, $companyId, $pid);
            }

            return [
                'idempotent_replay' => false,
                'receipt' => [
                    'id' => $receiptId,
                    'receipt_number' => $receiptNumber,
                    'status' => 'posted',
                    'total_capitalized_cost' => $totalCapCost,
                ],
                'po_status' => $poStatus,
            ];
        });
    }

    public function postStockTransfer(
        int $companyId,
        int $userId,
        int $fromWarehouseId,
        int $toWarehouseId,
        ?string $transferNumber,
        string $idempotencyKey,
        array $lines,
        string $notes = ''
    ): array {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new DomainException('INVALID_TRANSFER_SAME_WAREHOUSE');
        }
        if ($lines === []) {
            throw new DomainException('EMPTY_TRANSFER_LINES');
        }

        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $fromWarehouseId, $toWarehouseId, $transferNumber, $idempotencyKey, $lines, $notes): array {
            $productIds = array_values(array_unique(array_map(fn ($l) => (int) $l['product_id'], $lines)));
            sort($productIds);
            $whOrder = [$fromWarehouseId, $toWarehouseId];
            sort($whOrder);

            $productsMap = [];
            $pwLocks = [];
            foreach ($productIds as $pid) {
                $prod = $conn->selectOne('SELECT * FROM `products` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, $pid]);
                if (!$prod) {
                    throw new DomainException("PRODUCT_NOT_FOUND: company={$companyId}, product={$pid}");
                }
                $productsMap[$pid] = $prod;
                foreach ($whOrder as $wid) {
                    $pwLocks["{$pid}:{$wid}"] = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $wid);
                }
                $this->assertProductNotQuarantined($prod, $pwLocks["{$pid}:{$fromWarehouseId}"]);
            }

            $this->assertTenantAndMembership($conn, $companyId, $userId);
            $this->assertActiveWarehouse($conn, $companyId, $fromWarehouseId);
            $this->assertActiveWarehouse($conn, $companyId, $toWarehouseId);

            $existing = $conn->selectOne(
                'SELECT id, transfer_number, status FROM `stock_transfers` WHERE `company_id` = ? AND `idempotency_key` = ? FOR SHARE',
                [$companyId, $idempotencyKey]
            );
            if ($existing) {
                return ['idempotent_replay' => true, 'transfer' => (array) $existing];
            }

            $this->assertMovementQuota($conn, $companyId, count($lines) * 2);

            $transferNumber = $this->resolveDocumentNumber($conn, $companyId, DocumentNumberService::TYPE_TRANSFER, $transferNumber);

            $conn->statement(
                "INSERT INTO `stock_transfers` (`company_id`, `transfer_number`, `from_warehouse_id`, `to_warehouse_id`, `status`, `user_id`, `idempotency_key`, `notes`)
                 VALUES (?, ?, ?, ?, 'draft', ?, ?, ?)",
                [$companyId, $transferNumber, $fromWarehouseId, $toWarehouseId, $userId, $idempotencyKey, $notes]
            );
            $transferId = (int) $conn->getPdo()->lastInsertId();

            foreach ($lines as $l) {
                $pid = (int) $l['product_id'];
                $baseUnitId = (int) $productsMap[$pid]->unit_id;
                $unitId = (int) ($l['unit_id'] ?? $baseUnitId);
                $qty = BcMathHelper::normalize((string) $l['quantity'], 4);
                BcMathHelper::assertPositive($qty, 'transfer_quantity', 4);
                $factor = $this->resolveConversionFactor($conn, $companyId, $pid, $unitId, $baseUnitId);
                $baseQty = BcMathHelper::roundHalfUp(bcmul($qty, $factor, 10), 4);
                BcMathHelper::assertPositive($baseQty, 'transfer_base_quantity', 4);

                $srcKey = "{$pid}:{$fromWarehouseId}";
                $dstKey = "{$pid}:{$toWarehouseId}";
                $srcBasis = (string) $pwLocks[$srcKey]['valuation_basis'];
                $movementBasis = match ($srcBasis) {
                    'ESTIMATED_LEGACY_BASELINE', 'MIXED_BASELINE_AND_ACQUISITION' => 'ESTIMATED_LEGACY_BASELINE',
                    default => 'VERIFIED_ACQUISITION',
                };

                $out = $this->applyWarehouseOutbound(
                    $conn, $companyId, $userId, $pid, $fromWarehouseId, $pwLocks[$srcKey],
                    $baseQty, 'transfer_out', $movementBasis, 'StockTransfer', $transferId,
                    "xfer-out-tmp-{$companyId}-{$transferId}-{$pid}-" . uniqid('', true),
                    "Transfer OUT {$transferNumber}"
                );

                $conn->statement(
                    'INSERT INTO `stock_transfer_items`
                        (`company_id`, `stock_transfer_id`, `product_id`, `unit_id`, `conversion_factor`, `quantity`, `base_quantity`, `unit_cost_snapshot`, `total_cost_snapshot`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$companyId, $transferId, $pid, $unitId, $factor, $qty, $baseQty, $out['unit_cost'], $out['total_cost']]
                );

                $this->applyWarehouseInbound(
                    $conn, $companyId, $userId, $pid, $toWarehouseId, $pwLocks[$dstKey],
                    $baseQty, $out['unit_cost'], $out['total_cost'],
                    'transfer_in', $movementBasis, 'StockTransfer', $transferId,
                    "xfer-in-{$companyId}-{$transferId}-line-" . (int) $conn->getPdo()->lastInsertId(),
                    "Transfer IN {$transferNumber}"
                );
            }

            $conn->statement(
                "UPDATE `stock_transfers` SET `status` = 'posted', `posted_at` = NOW(), `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?",
                [$companyId, $transferId]
            );
            $this->maybeFail('after_status_posted');

            foreach ($productIds as $pid) {
                $this->syncProductRollup($conn, $companyId, $pid);
            }

            return ['idempotent_replay' => false, 'transfer_id' => $transferId, 'transfer_number' => $transferNumber, 'status' => 'posted'];
        });
    }

    public function postStockIssue(
        int $companyId,
        int $userId,
        int $warehouseId,
        ?string $issueNumber,
        string $idempotencyKey,
        array $lines,
        string $notes = ''
    ): array {
        if ($lines === []) {
            throw new DomainException('EMPTY_ISSUE_LINES');
        }

        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $warehouseId, $issueNumber, $idempotencyKey, $lines, $notes): array {
            $productIds = array_values(array_unique(array_map(fn ($l) => (int) $l['product_id'], $lines)));
            sort($productIds);
            $productsMap = [];
            $pwMap = [];
            foreach ($productIds as $pid) {
                $prod = $conn->selectOne('SELECT * FROM `products` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, $pid]);
                if (!$prod) {
                    throw new DomainException("PRODUCT_NOT_FOUND: company={$companyId}, product={$pid}");
                }
                $productsMap[$pid] = $prod;
                $pwMap[$pid] = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $warehouseId);
                $this->assertProductNotQuarantined($prod, $pwMap[$pid]);
            }

            $this->assertTenantAndMembership($conn, $companyId, $userId);
            $this->assertActiveWarehouse($conn, $companyId, $warehouseId);

            $existing = $conn->selectOne(
                'SELECT id, issue_number, status FROM `stock_issues` WHERE `company_id` = ? AND `idempotency_key` = ? FOR SHARE',
                [$companyId, $idempotencyKey]
            );
            if ($existing) {
                return ['idempotent_replay' => true, 'issue' => (array) $existing];
            }

            $this->assertMovementQuota($conn, $companyId, count($lines));

            $issueNumber = $this->resolveDocumentNumber($conn, $companyId, DocumentNumberService::TYPE_ISSUE, $issueNumber);

            $conn->statement(
                "INSERT INTO `stock_issues` (`company_id`, `issue_number`, `warehouse_id`, `status`, `user_id`, `idempotency_key`, `notes`)
                 VALUES (?, ?, ?, 'draft', ?, ?, ?)",
                [$companyId, $issueNumber, $warehouseId, $userId, $idempotencyKey, $notes]
            );
            $issueId = (int) $conn->getPdo()->lastInsertId();

            $residuals = [];
            foreach ($lines as $idx => $l) {
                $pid = (int) $l['product_id'];
                $baseUnitId = (int) $productsMap[$pid]->unit_id;
                $unitId = (int) ($l['unit_id'] ?? $baseUnitId);
                $qty = BcMathHelper::normalize((string) $l['quantity'], 4);
                BcMathHelper::assertPositive($qty, 'issue_quantity', 4);
                $factor = $this->resolveConversionFactor($conn, $companyId, $pid, $unitId, $baseUnitId);
                $baseQty = BcMathHelper::roundHalfUp(bcmul($qty, $factor, 10), 4);
                BcMathHelper::assertPositive($baseQty, 'issue_base_quantity', 4);

                $basis = $pwMap[$pid]['valuation_basis'] === 'ESTIMATED_LEGACY_BASELINE'
                    ? 'ESTIMATED_LEGACY_BASELINE'
                    : 'VERIFIED_ACQUISITION';

                $out = $this->applyWarehouseOutbound(
                    $conn, $companyId, $userId, $pid, $warehouseId, $pwMap[$pid],
                    $baseQty, 'issue', $basis, 'StockIssue', $issueId,
                    "issue-{$companyId}-{$issueId}-idx-{$idx}",
                    "Issue {$issueNumber}"
                );

                $conn->statement(
                    'INSERT INTO `stock_issue_items`
                        (`company_id`, `stock_issue_id`, `product_id`, `unit_id`, `conversion_factor`, `quantity`, `base_quantity`, `unit_cost_snapshot`, `total_cost_snapshot`)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$companyId, $issueId, $pid, $unitId, $factor, $qty, $baseQty, $out['unit_cost'], $out['total_cost']]
                );

                if (bccomp($out['residual_delta'], '0.0000', 4) !== 0) {
                    $residuals[] = $out;
                }
            }

            $conn->statement(
                "UPDATE `stock_issues` SET `status` = 'posted', `posted_at` = NOW(), `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?",
                [$companyId, $issueId]
            );
            $this->maybeFail('after_status_posted');

            foreach ($productIds as $pid) {
                $this->syncProductRollup($conn, $companyId, $pid);
            }

            return ['idempotent_replay' => false, 'issue_id' => $issueId, 'issue_number' => $issueNumber, 'status' => 'posted', 'residuals' => $residuals];
        });
    }

    public function saveDraftAdjustment(
        int $companyId,
        int $userId,
        int $warehouseId,
        ?string $adjustmentNumber,
        string $idempotencyKey,
        string $reason,
        array $lines,
        ?int $draftId = null
    ): array {
        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $warehouseId, $adjustmentNumber, $idempotencyKey, $reason, $lines, $draftId): array {
            $this->assertTenantAndMembership($conn, $companyId, $userId);
            $this->assertActiveWarehouse($conn, $companyId, $warehouseId);

            if ($draftId !== null) {
                $hdr = $conn->selectOne(
                    'SELECT * FROM `stock_adjustments` WHERE `company_id` = ? AND `id` = ? FOR UPDATE',
                    [$companyId, $draftId]
                );
                if (!$hdr) {
                    throw new DomainException("ADJUSTMENT_NOT_FOUND: {$draftId}");
                }
                if ($hdr->status !== 'draft') {
                    throw new DomainException("DRAFT_ALREADY_FINALIZED: Adjustment #{$draftId} status is '{$hdr->status}'.");
                }
                $adjustmentNumber = (string) $hdr->adjustment_number;
                $conn->statement(
                    'UPDATE `stock_adjustments` SET `reason` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
                    [$reason, $companyId, $draftId]
                );
                $conn->statement(
                    'DELETE FROM `stock_adjustment_items` WHERE `company_id` = ? AND `stock_adjustment_id` = ?',
                    [$companyId, $draftId]
                );
            } else {
                $adjustmentNumber = $this->resolveDocumentNumber($conn, $companyId, DocumentNumberService::TYPE_ADJUSTMENT, $adjustmentNumber);
                $conn->statement(
                    "INSERT INTO `stock_adjustments` (`company_id`, `adjustment_number`, `warehouse_id`, `status`, `user_id`, `idempotency_key`, `reason`)
                     VALUES (?, ?, ?, 'draft', ?, ?, ?)",
                    [$companyId, $adjustmentNumber, $warehouseId, $userId, $idempotencyKey, $reason]
                );
                $draftId = (int) $conn->getPdo()->lastInsertId();
            }

            foreach ($lines as $l) {
                $qty = BcMathHelper::normalize((string) $l['quantity'], 4);
                BcMathHelper::assertPositive($qty, 'adjustment_quantity', 4);
                $unitCost = BcMathHelper::normalize((string) ($l['unit_cost'] ?? '0'), 6);
                BcMathHelper::assertNonNegative($unitCost, 'adjustment_unit_cost', 6);
                $totalCost = BcMathHelper::roundHalfUp(bcmul($qty, $unitCost, 10), 4);
                $conn->statement(
                    'INSERT INTO `stock_adjustment_items` (`company_id`, `stock_adjustment_id`, `product_id`, `direction`, `quantity`, `unit_cost`, `total_cost`)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$companyId, $draftId, (int) $l['product_id'], $l['direction'], $qty, $unitCost, $totalCost]
                );
            }

            return ['adjustment_id' => $draftId, 'adjustment_number' => $adjustmentNumber, 'status' => 'draft', 'line_count' => count($lines)];
        });
    }

    public function postDraftAdjustment(int $companyId, int $userId, int $adjustmentId): array
    {
        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $adjustmentId): array {
            // Serialize against concurrent draft edits by acquiring header FOR UPDATE FIRST!
            $hdr = $conn->selectOne(
                'SELECT * FROM `stock_adjustments` WHERE `company_id` = ? AND `id` = ? FOR UPDATE',
                [$companyId, $adjustmentId]
            );
            if (!$hdr) {
                throw new DomainException("ADJUSTMENT_NOT_FOUND: {$adjustmentId}");
            }
            $this->assertTenantAndMembership($conn, $companyId, $userId);
            if ($hdr->status === 'posted') {
                return ['idempotent_replay' => true, 'adjustment_id' => $adjustmentId, 'status' => 'posted'];
            }
            if ($hdr->status !== 'draft') {
                throw new DomainException("ADJUSTMENT_NOT_POSTABLE: status={$hdr->status}");
            }

            $lines = $conn->select(
                'SELECT * FROM `stock_adjustment_items` WHERE `company_id` = ? AND `stock_adjustment_id` = ? ORDER BY id ASC FOR UPDATE',
                [$companyId, $adjustmentId]
            );
            if ($lines === []) {
                throw new DomainException('EMPTY_ADJUSTMENT_LINES');
            }

            $this->assertMovementQuota($conn, $companyId, count($lines));

            $whId = (int) $hdr->warehouse_id;
            $productIds = array_values(array_unique(array_map(fn ($r) => (int) $r->product_id, $lines)));
            sort($productIds);

            $productsMap = [];
            $pwMap = [];
            foreach ($productIds as $pid) {
                $prod = $conn->selectOne('SELECT * FROM `products` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, $pid]);
                $productsMap[$pid] = $prod;
                $pwMap[$pid] = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $whId);
                $this->assertProductNotQuarantined($prod, $pwMap[$pid]);
            }

            foreach ($lines as $line) {
                $pid = (int) $line->product_id;
                $qty = BcMathHelper::normalize((string) $line->quantity, 4);
                $basis = $pwMap[$pid]['valuation_basis'] === 'ESTIMATED_LEGACY_BASELINE'
                    ? 'ESTIMATED_LEGACY_BASELINE'
                    : 'VERIFIED_ACQUISITION';

                if ($line->direction === 'in') {
                    $uCost = BcMathHelper::normalize((string) $line->unit_cost, 6);
                    if (bccomp($uCost, '0.000000', 6) === 0) {
                        $uCost = BcMathHelper::normalize((string) $pwMap[$pid]['moving_average_cost'], 6);
                    }
                    $tCost = BcMathHelper::roundHalfUp(bcmul($qty, $uCost, 10), 4);
                    $conn->statement(
                        'UPDATE `stock_adjustment_items` SET `unit_cost` = ?, `total_cost` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
                        [$uCost, $tCost, $companyId, (int) $line->id]
                    );
                    $this->applyWarehouseInbound(
                        $conn, $companyId, $userId, $pid, $whId, $pwMap[$pid],
                        $qty, $uCost, $tCost, 'adjustment', $basis,
                        'StockAdjustment', $adjustmentId,
                        "adj-{$companyId}-{$adjustmentId}-item-{$line->id}",
                        "Adjustment IN {$hdr->adjustment_number}: {$hdr->reason}"
                    );
                } else {
                    $out = $this->applyWarehouseOutbound(
                        $conn, $companyId, $userId, $pid, $whId, $pwMap[$pid],
                        $qty, 'adjustment', $basis, 'StockAdjustment', $adjustmentId,
                        "adj-{$companyId}-{$adjustmentId}-item-{$line->id}",
                        "Adjustment OUT {$hdr->adjustment_number}: {$hdr->reason}"
                    );
                    $conn->statement(
                        'UPDATE `stock_adjustment_items` SET `unit_cost` = ?, `total_cost` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?',
                        [$out['unit_cost'], $out['total_cost'], $companyId, (int) $line->id]
                    );
                }
            }

            $conn->statement(
                "UPDATE `stock_adjustments` SET `status` = 'posted', `posted_at` = NOW(), `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?",
                [$companyId, $adjustmentId]
            );
            $this->maybeFail('after_status_posted');

            foreach ($productIds as $pid) {
                $this->syncProductRollup($conn, $companyId, $pid);
            }

            return ['idempotent_replay' => false, 'adjustment_id' => $adjustmentId, 'status' => 'posted', 'posted_line_count' => count($lines)];
        });
    }

    public function postStockAdjustment(
        int $companyId,
        int $userId,
        int $warehouseId,
        ?string $adjustmentNumber,
        string $idempotencyKey,
        string $reason,
        array $lines
    ): array {
        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $warehouseId, $adjustmentNumber, $idempotencyKey, $reason, $lines): array {
            if ($lines === []) {
                throw new DomainException('EMPTY_ADJUSTMENT_LINES');
            }
            $this->assertTenantAndMembership($conn, $companyId, $userId);
            $this->assertActiveWarehouse($conn, $companyId, $warehouseId);

            $existing = $conn->selectOne(
                'SELECT id, adjustment_number, status FROM `stock_adjustments` WHERE `company_id` = ? AND `idempotency_key` = ? FOR SHARE',
                [$companyId, $idempotencyKey]
            );
            if ($existing) {
                return ['idempotent_replay' => true, 'adjustment' => (array) $existing];
            }

            $this->assertMovementQuota($conn, $companyId, count($lines));

            $adjustmentNumber = $this->resolveDocumentNumber($conn, $companyId, DocumentNumberService::TYPE_ADJUSTMENT, $adjustmentNumber);

            $conn->statement(
                "INSERT INTO `stock_adjustments` (`company_id`, `adjustment_number`, `warehouse_id`, `status`, `user_id`, `idempotency_key`, `reason`)
                 VALUES (?, ?, ?, 'draft', ?, ?, ?)",
                [$companyId, $adjustmentNumber, $warehouseId, $userId, $idempotencyKey, $reason]
            );
            $adjustmentId = (int) $conn->getPdo()->lastInsertId();

            foreach ($lines as $l) {
                $qty = BcMathHelper::normalize((string) $l['quantity'], 4);
                BcMathHelper::assertPositive($qty, 'adjustment_quantity', 4);
                $uCost = BcMathHelper::normalize((string) ($l['unit_cost'] ?? '0'), 6);
                BcMathHelper::assertNonNegative($uCost, 'adjustment_unit_cost', 6);
                $tCost = BcMathHelper::roundHalfUp(bcmul($qty, $uCost, 10), 4);
                $conn->statement(
                    'INSERT INTO `stock_adjustment_items` (`company_id`, `stock_adjustment_id`, `product_id`, `direction`, `quantity`, `unit_cost`, `total_cost`)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$companyId, $adjustmentId, (int) $l['product_id'], $l['direction'], $qty, $uCost, $tCost]
                );
            }

            $postedLines = $conn->select('SELECT * FROM `stock_adjustment_items` WHERE `company_id` = ? AND `stock_adjustment_id` = ? ORDER BY id ASC', [$companyId, $adjustmentId]);
            $productIds = array_values(array_unique(array_map(fn ($r) => (int) $r->product_id, $postedLines)));
            sort($productIds);

            $pwMap = [];
            foreach ($productIds as $pid) {
                $prod = $conn->selectOne('SELECT * FROM `products` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, $pid]);
                $pwMap[$pid] = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $warehouseId);
                $this->assertProductNotQuarantined($prod, $pwMap[$pid]);
            }

            foreach ($postedLines as $line) {
                $pid = (int) $line->product_id;
                $qty = BcMathHelper::normalize((string) $line->quantity, 4);
                $basis = $pwMap[$pid]['valuation_basis'] === 'ESTIMATED_LEGACY_BASELINE' ? 'ESTIMATED_LEGACY_BASELINE' : 'VERIFIED_ACQUISITION';
                if ($line->direction === 'in') {
                    $uCost = BcMathHelper::normalize((string) $line->unit_cost, 6);
                    if (bccomp($uCost, '0.000000', 6) === 0) {
                        $uCost = BcMathHelper::normalize((string) $pwMap[$pid]['moving_average_cost'], 6);
                    }
                    $tCost = BcMathHelper::roundHalfUp(bcmul($qty, $uCost, 10), 4);
                    $conn->statement('UPDATE `stock_adjustment_items` SET `unit_cost` = ?, `total_cost` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?', [$uCost, $tCost, $companyId, (int) $line->id]);
                    $this->applyWarehouseInbound($conn, $companyId, $userId, $pid, $warehouseId, $pwMap[$pid], $qty, $uCost, $tCost, 'adjustment', $basis, 'StockAdjustment', $adjustmentId, "adj-{$companyId}-{$adjustmentId}-item-{$line->id}", "Adjustment IN {$adjustmentNumber}");
                } else {
                    $out = $this->applyWarehouseOutbound($conn, $companyId, $userId, $pid, $warehouseId, $pwMap[$pid], $qty, 'adjustment', $basis, 'StockAdjustment', $adjustmentId, "adj-{$companyId}-{$adjustmentId}-item-{$line->id}", "Adjustment OUT {$adjustmentNumber}");
                    $conn->statement('UPDATE `stock_adjustment_items` SET `unit_cost` = ?, `total_cost` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?', [$out['unit_cost'], $out['total_cost'], $companyId, (int) $line->id]);
                }
            }

            $conn->statement("UPDATE `stock_adjustments` SET `status` = 'posted', `posted_at` = NOW(), `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?", [$companyId, $adjustmentId]);
            $this->maybeFail('after_status_posted');

            foreach ($productIds as $pid) {
                $this->syncProductRollup($conn, $companyId, $pid);
            }

            return ['idempotent_replay' => false, 'adjustment_id' => $adjustmentId, 'adjustment_number' => $adjustmentNumber, 'status' => 'posted'];
        });
    }

    public function reverseDocument(
        int $companyId,
        int $userId,
        string $docType,
        int $docId,
        ?string $adjustmentNumber,
        string $idempotencyKey,
        string $reason
    ): array {
        $tableMap = [
            'PurchaseReceipt' => 'purchase_receipts',
            'StockIssue'      => 'stock_issues',
            'StockTransfer'   => 'stock_transfers',
            'StockAdjustment' => 'stock_adjustments',
        ];
        if (!isset($tableMap[$docType])) {
            throw new DomainException("UNSUPPORTED_REVERSAL_TYPE: {$docType}");
        }

        return $this->runWithRetry(function (ConnectionInterface $conn) use ($companyId, $userId, $docType, $docId, $adjustmentNumber, $idempotencyKey, $reason, $tableMap): array {
            $hdrTable = $tableMap[$docType];
            $targetHdr = $conn->selectOne("SELECT * FROM `{$hdrTable}` WHERE `company_id` = ? AND `id` = ? FOR UPDATE", [$companyId, $docId]);
            if (!$targetHdr) {
                throw new DomainException("TARGET_DOCUMENT_NOT_FOUND: {$docType}#{$docId} in company={$companyId}");
            }
            if ($targetHdr->status !== 'posted') {
                throw new DomainException("ONLY_POSTED_DOCUMENTS_REVERSIBLE: status={$targetHdr->status}");
            }

            $this->assertTenantAndMembership($conn, $companyId, $userId);

            // Idempotent replay check using unique idempotency_key
            $existingByIdem = $conn->selectOne(
                'SELECT id, adjustment_number, status, reverses_type, reverses_id FROM `stock_adjustments` WHERE `company_id` = ? AND `idempotency_key` = ? FOR SHARE',
                [$companyId, $idempotencyKey]
            );
            if ($existingByIdem) {
                return ['idempotent_replay' => true, 'reversal' => (array) $existingByIdem];
            }

            // Enforce single-use reversal BEFORE creating reversal rows (also guarded by UNIQUE uq_sa_company_reverses)
            $alreadyReversed = $conn->selectOne(
                'SELECT id FROM `stock_adjustments` WHERE `company_id` = ? AND `reverses_type` = ? AND `reverses_id` = ? FOR UPDATE',
                [$companyId, $docType, $docId]
            );
            if ($alreadyReversed) {
                throw new DomainException("DOCUMENT_ALREADY_REVERSED: {$docType}#{$docId} already reversed by Adjustment #{$alreadyReversed->id}");
            }

            $primaryWhId = (int) ($targetHdr->warehouse_id ?? $targetHdr->from_warehouse_id);
            $adjustmentNumber = $this->resolveDocumentNumber(
                $conn,
                $companyId,
                DocumentNumberService::reversalTypeFor($docType),
                $adjustmentNumber
            );
            $conn->statement(
                "INSERT INTO `stock_adjustments`
                    (`company_id`, `adjustment_number`, `warehouse_id`, `status`, `user_id`, `idempotency_key`, `reverses_type`, `reverses_id`, `reason`)
                 VALUES (?, ?, ?, 'draft', ?, ?, ?, ?, ?)",
                [$companyId, $adjustmentNumber, $primaryWhId, $userId, $idempotencyKey, $docType, $docId, $reason]
            );
            $revId = (int) $conn->getPdo()->lastInsertId();

            $touchedProducts = [];

            if ($docType === 'StockIssue') {
                $items = $conn->select('SELECT * FROM `stock_issue_items` WHERE `company_id` = ? AND `stock_issue_id` = ? ORDER BY product_id ASC FOR UPDATE', [$companyId, $docId]);
                foreach ($items as $item) {
                    $pid = (int) $item->product_id;
                    $touchedProducts[] = $pid;
                    $pw = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $primaryWhId);
                    $qty = BcMathHelper::normalize((string) $item->base_quantity, 4);
                    $uCost = BcMathHelper::normalize((string) $item->unit_cost_snapshot, 6);
                    $tCost = BcMathHelper::normalize((string) $item->total_cost_snapshot, 4);

                    $conn->statement(
                        "INSERT INTO `stock_adjustment_items` (`company_id`, `stock_adjustment_id`, `product_id`, `direction`, `quantity`, `unit_cost`, `total_cost`)
                         VALUES (?, ?, ?, 'in', ?, ?, ?)",
                        [$companyId, $revId, $pid, $qty, $uCost, $tCost]
                    );
                    $saiId = (int) $conn->getPdo()->lastInsertId();
                    $this->applyWarehouseInbound(
                        $conn, $companyId, $userId, $pid, $primaryWhId, $pw,
                        $qty, $uCost, $tCost, 'reversal_in', 'REVERSAL_CARRIED_COST',
                        'StockAdjustment', $revId, "rev-{$companyId}-{$revId}-sai-{$saiId}",
                        "Reversal of StockIssue #{$docId}: {$reason}"
                    );
                }
            } elseif ($docType === 'PurchaseReceipt') {
                $po = $conn->selectOne('SELECT * FROM `purchases` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, (int) $targetHdr->purchase_id]);
                $items = $conn->select('SELECT * FROM `purchase_receipt_items` WHERE `company_id` = ? AND `purchase_receipt_id` = ? ORDER BY product_id ASC FOR UPDATE', [$companyId, $docId]);
                foreach ($items as $item) {
                    $pid = (int) $item->product_id;
                    $touchedProducts[] = $pid;
                    $pw = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $primaryWhId);
                    $baseQty = BcMathHelper::normalize((string) $item->base_quantity, 4);
                    $recvQty = BcMathHelper::normalize((string) $item->received_quantity, 4);
                    $uCost = BcMathHelper::normalize((string) $item->unit_cost_base, 6);
                    $tCost = BcMathHelper::normalize((string) $item->capitalized_total_cost, 4);

                    $conn->statement(
                        "INSERT INTO `stock_adjustment_items` (`company_id`, `stock_adjustment_id`, `product_id`, `direction`, `quantity`, `unit_cost`, `total_cost`)
                         VALUES (?, ?, ?, 'out', ?, ?, ?)",
                        [$companyId, $revId, $pid, $baseQty, $uCost, $tCost]
                    );
                    $saiId = (int) $conn->getPdo()->lastInsertId();
                    $this->applyWarehouseOutbound(
                        $conn, $companyId, $userId, $pid, $primaryWhId, $pw,
                        $baseQty, 'reversal_out', 'REVERSAL_CARRIED_COST',
                        'StockAdjustment', $revId, "rev-{$companyId}-{$revId}-sai-{$saiId}",
                        "Reversal of PurchaseReceipt #{$docId}: {$reason}",
                        $tCost, $uCost
                    );

                    // Restore open PO quantity cleanly
                    $pi = $conn->selectOne('SELECT * FROM `purchase_items` WHERE `company_id` = ? AND `id` = ? FOR UPDATE', [$companyId, (int) $item->purchase_item_id]);
                    $newRecv = bcsub(BcMathHelper::normalize((string) $pi->received_quantity, 4), $recvQty, 4);
                    if (bccomp($newRecv, '0.0000', 4) < 0) {
                        $newRecv = '0.0000';
                    }
                    $conn->statement('UPDATE `purchase_items` SET `received_quantity` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?', [$newRecv, $companyId, (int) $pi->id]);
                }

                // Recompute PO status
                $poLines = $conn->select('SELECT received_quantity FROM `purchase_items` WHERE `company_id` = ? AND `purchase_id` = ?', [$companyId, (int) $po->id]);
                $anyRecv = false;
                foreach ($poLines as $pl) {
                    if (bccomp(BcMathHelper::normalize((string) $pl->received_quantity, 4), '0.0000', 4) > 0) {
                        $anyRecv = true;
                    }
                }
                $conn->statement('UPDATE `purchases` SET `status` = ?, `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?', [$anyRecv ? 'partial' : 'ordered', $companyId, (int) $po->id]);
            } elseif ($docType === 'StockAdjustment') {
                $items = $conn->select('SELECT * FROM `stock_adjustment_items` WHERE `company_id` = ? AND `stock_adjustment_id` = ? ORDER BY product_id ASC FOR UPDATE', [$companyId, $docId]);
                foreach ($items as $item) {
                    $pid = (int) $item->product_id;
                    $touchedProducts[] = $pid;
                    $pw = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $primaryWhId);
                    $qty = BcMathHelper::normalize((string) $item->quantity, 4);
                    $uCost = BcMathHelper::normalize((string) $item->unit_cost, 6);
                    $tCost = BcMathHelper::normalize((string) $item->total_cost, 4);
                    $revDir = $item->direction === 'in' ? 'out' : 'in';

                    $conn->statement(
                        'INSERT INTO `stock_adjustment_items` (`company_id`, `stock_adjustment_id`, `product_id`, `direction`, `quantity`, `unit_cost`, `total_cost`)
                         VALUES (?, ?, ?, ?, ?, ?, ?)',
                        [$companyId, $revId, $pid, $revDir, $qty, $uCost, $tCost]
                    );
                    $saiId = (int) $conn->getPdo()->lastInsertId();

                    if ($revDir === 'in') {
                        $this->applyWarehouseInbound($conn, $companyId, $userId, $pid, $primaryWhId, $pw, $qty, $uCost, $tCost, 'reversal_in', 'REVERSAL_CARRIED_COST', 'StockAdjustment', $revId, "rev-{$companyId}-{$revId}-sai-{$saiId}", "Reversal of StockAdjustment #{$docId}");
                    } else {
                        $this->applyWarehouseOutbound($conn, $companyId, $userId, $pid, $primaryWhId, $pw, $qty, 'reversal_out', 'REVERSAL_CARRIED_COST', 'StockAdjustment', $revId, "rev-{$companyId}-{$revId}-sai-{$saiId}", "Reversal of StockAdjustment #{$docId}", $tCost, $uCost);
                    }
                }
            } elseif ($docType === 'StockTransfer') {
                $fromWh = (int) $targetHdr->from_warehouse_id;
                $toWh = (int) $targetHdr->to_warehouse_id;
                $items = $conn->select('SELECT * FROM `stock_transfer_items` WHERE `company_id` = ? AND `stock_transfer_id` = ? ORDER BY product_id ASC FOR UPDATE', [$companyId, $docId]);
                $whOrder = [$fromWh, $toWh];
                sort($whOrder);
                foreach ($items as $item) {
                    $pid = (int) $item->product_id;
                    $touchedProducts[] = $pid;
                    $pwPair = [];
                    foreach ($whOrder as $wid) {
                        $pwPair[$wid] = $this->lockOrCreateProductWarehouse($conn, $companyId, $pid, $wid);
                    }
                    $qty = BcMathHelper::normalize((string) $item->base_quantity, 4);
                    $uCost = BcMathHelper::normalize((string) $item->unit_cost_snapshot, 6);
                    $tCost = BcMathHelper::normalize((string) $item->total_cost_snapshot, 4);

                    $conn->statement(
                        "INSERT INTO `stock_adjustment_items` (`company_id`, `stock_adjustment_id`, `product_id`, `direction`, `quantity`, `unit_cost`, `total_cost`)
                         VALUES (?, ?, ?, 'out', ?, ?, ?)",
                        [$companyId, $revId, $pid, $qty, $uCost, $tCost]
                    );
                    $saiId = (int) $conn->getPdo()->lastInsertId();
                    $this->applyWarehouseOutbound($conn, $companyId, $userId, $pid, $toWh, $pwPair[$toWh], $qty, 'reversal_out', 'REVERSAL_CARRIED_COST', 'StockAdjustment', $revId, "rev-xfer-out-{$companyId}-{$revId}-{$saiId}", "Reversal Transfer OUT #{$docId}", $tCost, $uCost);
                    $this->applyWarehouseInbound($conn, $companyId, $userId, $pid, $fromWh, $pwPair[$fromWh], $qty, $uCost, $tCost, 'reversal_in', 'REVERSAL_CARRIED_COST', 'StockAdjustment', $revId, "rev-xfer-in-{$companyId}-{$revId}-{$saiId}", "Reversal Transfer IN #{$docId}");
                }
            }

            $conn->statement("UPDATE `stock_adjustments` SET `status` = 'posted', `posted_at` = NOW(), `updated_at` = NOW() WHERE `company_id` = ? AND `id` = ?", [$companyId, $revId]);
            $this->maybeFail('after_status_posted');

            foreach (array_values(array_unique($touchedProducts)) as $pid) {
                $this->syncProductRollup($conn, $companyId, $pid);
            }

            return [
                'idempotent_replay' => false,
                'reversal_adjustment_id' => $revId,
                'adjustment_number' => $adjustmentNumber,
                'reversed_document' => "{$docType}#{$docId}",
                'status' => 'posted',
            ];
        });
    }
}
