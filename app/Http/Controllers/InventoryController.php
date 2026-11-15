<?php

namespace App\Http\Controllers;

use App\Exceptions\QuotaExceededException;
use App\Http\Requests\ReverseTransactionRequest;
use App\Http\Requests\StoreGrnRequest;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Requests\StoreStockIssueRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Company;
use App\Services\InventoryService;
use App\Services\SubscriptionPlanService;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private TenantContext $tenantContext,
        private SubscriptionPlanService $plans,
    ) {}

    public function storeGrn(StoreGrnRequest $request): JsonResponse
    {
        return $this->executeDomainAction(function () use ($request) {
            $v = $request->validated();

            return $this->inventory->postPurchaseReceipt(
                $this->tenantContext->requireCompanyId(),
                (int) $request->user()->id,
                (int) $v['purchase_id'],
                (int) $v['warehouse_id'],
                null,
                $v['idempotency_key'],
                $v['lines'],
                $v['notes'] ?? ''
            );
        });
    }

    public function storeTransfer(StoreTransferRequest $request): JsonResponse
    {
        return $this->executeDomainAction(function () use ($request) {
            $v = $request->validated();

            return $this->inventory->postStockTransfer(
                $this->tenantContext->requireCompanyId(),
                (int) $request->user()->id,
                (int) $v['from_warehouse_id'],
                (int) $v['to_warehouse_id'],
                null,
                $v['idempotency_key'],
                $v['lines'],
                $v['notes'] ?? ''
            );
        });
    }

    public function storeIssue(StoreStockIssueRequest $request): JsonResponse
    {
        return $this->executeDomainAction(function () use ($request) {
            $v = $request->validated();

            return $this->inventory->postStockIssue(
                $this->tenantContext->requireCompanyId(),
                (int) $request->user()->id,
                (int) $v['warehouse_id'],
                null,
                $v['idempotency_key'],
                $v['lines'],
                $v['notes'] ?? ''
            );
        });
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): JsonResponse
    {
        return $this->executeDomainAction(function () use ($request) {
            $v = $request->validated();

            return $this->inventory->postStockAdjustment(
                $this->tenantContext->requireCompanyId(),
                (int) $request->user()->id,
                (int) $v['warehouse_id'],
                null,
                $v['idempotency_key'],
                $v['reason'],
                $v['lines']
            );
        });
    }

    public function storeReversal(ReverseTransactionRequest $request): JsonResponse
    {
        $v = $request->validated();
        $companyId = $this->tenantContext->requireCompanyId();
        $company = Company::with(['subscriptionPlan.features' => fn ($q) => $q->where('subscription_features.is_active', 1)])->findOrFail($companyId);

        $featureKey = match ($v['document_type']) {
            'PurchaseReceipt' => 'module_grn',
            'StockTransfer' => 'module_stock_transfers',
            'StockIssue' => 'module_stock_issues',
            'StockAdjustment' => 'module_stock_adjustments',
            default => null,
        };

        if ($featureKey !== null && !$this->plans->hasFeature($company, $featureKey)) {
            $plan = $this->plans->getCompanyPlan($company);
            $planName = $plan?->name ?? ($company->access_type ?: 'Free');
            $featureLabel = __("subscription.features.{$featureKey}");
            $message = __('subscription.feature_locked_message', [
                'feature' => $featureLabel === "subscription.features.{$featureKey}" ? $featureKey : $featureLabel,
                'plan' => $planName,
            ]);

            return response()->json([
                'code' => 'FEATURE_LOCKED',
                'feature' => $featureKey,
                'plan' => $planName,
                'message' => $message,
                'error' => $message,
            ], 403);
        }

        return $this->executeDomainAction(function () use ($v, $request, $companyId) {
            return $this->inventory->reverseDocument(
                $companyId,
                (int) $request->user()->id,
                $v['document_type'],
                (int) $v['document_id'],
                null,
                $v['idempotency_key'],
                $v['reason']
            );
        });
    }

    private function executeDomainAction(callable $callback): JsonResponse
    {
        try {
            $result = $callback();

            return response()->json($result, ($result['idempotent_replay'] ?? false) ? 200 : 201);
        } catch (QuotaExceededException $e) {
            return response()->json($e->toArray(), 422);
        } catch (DomainException $e) {
            $status = str_contains($e->getMessage(), 'UNAUTHORIZED_TENANT_ACCESS') ? 403 : 422;

            return response()->json(['error' => $e->getMessage()], $status);
        }
    }
}
