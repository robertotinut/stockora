<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Concurrency-safe, tenant-scoped automatic document numbering service.
 */
class DocumentNumberService
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_GRN = 'grn';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_ISSUE = 'issue';
    public const TYPE_REVERSAL_GRN = 'reversal_grn';
    public const TYPE_REVERSAL_TRANSFER = 'reversal_transfer';
    public const TYPE_REVERSAL_ISSUE = 'reversal_issue';
    public const TYPE_REVERSAL_ADJUSTMENT = 'reversal_adjustment';

    /**
     * Registry mapping canonical document types to prefixes and target ledger tables.
     *
     * @var array<string, array{prefix: string, table: string, column: string}>
     */
    public const DOC_TYPES = [
        self::TYPE_PURCHASE => ['prefix' => 'PO', 'table' => 'purchases', 'column' => 'purchase_number'],
        self::TYPE_GRN => ['prefix' => 'GRN', 'table' => 'purchase_receipts', 'column' => 'receipt_number'],
        self::TYPE_ADJUSTMENT => ['prefix' => 'ADJ', 'table' => 'stock_adjustments', 'column' => 'adjustment_number'],
        self::TYPE_TRANSFER => ['prefix' => 'TRF', 'table' => 'stock_transfers', 'column' => 'transfer_number'],
        self::TYPE_ISSUE => ['prefix' => 'ISS', 'table' => 'stock_issues', 'column' => 'issue_number'],
        self::TYPE_REVERSAL_GRN => ['prefix' => 'REV-GRN', 'table' => 'stock_adjustments', 'column' => 'adjustment_number'],
        self::TYPE_REVERSAL_TRANSFER => ['prefix' => 'REV-TRF', 'table' => 'stock_adjustments', 'column' => 'adjustment_number'],
        self::TYPE_REVERSAL_ISSUE => ['prefix' => 'REV-ISS', 'table' => 'stock_adjustments', 'column' => 'adjustment_number'],
        self::TYPE_REVERSAL_ADJUSTMENT => ['prefix' => 'REV-ADJ', 'table' => 'stock_adjustments', 'column' => 'adjustment_number'],
    ];

    public static function reversalTypeFor(string $documentType): string
    {
        return match ($documentType) {
            'PurchaseReceipt' => self::TYPE_REVERSAL_GRN,
            'StockTransfer' => self::TYPE_REVERSAL_TRANSFER,
            'StockIssue' => self::TYPE_REVERSAL_ISSUE,
            'StockAdjustment' => self::TYPE_REVERSAL_ADJUSTMENT,
            default => throw new InvalidArgumentException("Unsupported reversal document type [{$documentType}]."),
        };
    }

    /**
     * Non-reserving read-only preview of the next sequential number for display in create forms.
     */
    public function previewNextNumber(int $companyId, string $docType, ?CarbonInterface $date = null): string
    {
        $spec = $this->spec($docType);
        $periodKey = ($date ? Carbon::instance($date) : now())->format('Ymd');
        $codePrefix = "{$spec['prefix']}-{$periodKey}-";
        $conn = DB::connection();

        $storedSeq = (int) ($conn->table('document_sequences')
            ->where('company_id', $companyId)
            ->where('doc_type', $docType)
            ->where('period_key', $periodKey)
            ->value('last_seq') ?? 0);

        $existingMax = $this->highestExistingSequence($conn, $companyId, $spec, $codePrefix);
        $nextSeq = max($storedSeq, $existingMax) + 1;

        while ($this->numberExists($conn, $companyId, $spec, $candidate = $this->formatNumber($codePrefix, $nextSeq))) {
            $nextSeq++;
        }

        return $candidate;
    }

    /**
     * Generate and reserve the next unique sequential document number inside a row-locked DB transaction.
     */
    public function generateNextNumber(
        int $companyId,
        string $docType,
        ?CarbonInterface $date = null,
        ?ConnectionInterface $connection = null
    ): string {
        $spec = $this->spec($docType);
        $periodKey = ($date ? Carbon::instance($date) : now())->format('Ymd');
        $codePrefix = "{$spec['prefix']}-{$periodKey}-";
        $conn = $connection ?? DB::connection();

        $reserve = function () use ($conn, $companyId, $docType, $periodKey, $spec, $codePrefix): string {
            $row = $conn->table('document_sequences')
                ->where('company_id', $companyId)
                ->where('doc_type', $docType)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                $seed = $this->highestExistingSequence($conn, $companyId, $spec, $codePrefix);
                $now = now();
                $conn->table('document_sequences')->insertOrIgnore([
                    'company_id' => $companyId,
                    'doc_type' => $docType,
                    'period_key' => $periodKey,
                    'last_seq' => $seed,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $row = $conn->table('document_sequences')
                    ->where('company_id', $companyId)
                    ->where('doc_type', $docType)
                    ->where('period_key', $periodKey)
                    ->lockForUpdate()
                    ->first();
            }

            $existingMax = $this->highestExistingSequence($conn, $companyId, $spec, $codePrefix);
            $nextSeq = max((int) ($row?->last_seq ?? 0), $existingMax);

            do {
                $nextSeq++;
                $formatted = $this->formatNumber($codePrefix, $nextSeq);
            } while ($this->numberExists($conn, $companyId, $spec, $formatted));

            $conn->table('document_sequences')
                ->where('id', $row->id)
                ->update([
                    'last_seq' => $nextSeq,
                    'updated_at' => now(),
                ]);

            return $formatted;
        };

        return $conn->transactionLevel() > 0
            ? $reserve()
            : $conn->transaction($reserve);
    }

    /**
     * @return array{prefix: string, table: string, column: string}
     */
    private function spec(string $docType): array
    {
        if (!array_key_exists($docType, self::DOC_TYPES)) {
            throw new InvalidArgumentException("Unsupported document sequence type [{$docType}].");
        }

        return self::DOC_TYPES[$docType];
    }

    /**
     * @param  array{prefix: string, table: string, column: string}  $spec
     */
    private function highestExistingSequence(
        ConnectionInterface $conn,
        int $companyId,
        array $spec,
        string $codePrefix
    ): int {
        $prefixLen = strlen($codePrefix);
        $max = 0;

        $numbers = $conn->table($spec['table'])
            ->where('company_id', $companyId)
            ->where($spec['column'], 'like', $codePrefix . '%')
            ->pluck($spec['column']);

        foreach ($numbers as $value) {
            $str = (string) $value;
            if (!str_starts_with($str, $codePrefix)) {
                continue;
            }
            $suffix = substr($str, $prefixLen);
            if ($suffix !== '' && ctype_digit($suffix)) {
                $num = (int) $suffix;
                if ($num > $max) {
                    $max = $num;
                }
            }
        }

        return $max;
    }

    /**
     * @param  array{prefix: string, table: string, column: string}  $spec
     */
    private function numberExists(
        ConnectionInterface $conn,
        int $companyId,
        array $spec,
        string $formatted
    ): bool {
        return $conn->table($spec['table'])
            ->where('company_id', $companyId)
            ->where($spec['column'], $formatted)
            ->exists();
    }

    private function formatNumber(string $codePrefix, int $seq): string
    {
        return $codePrefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
