<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Services\TenantContext;
use DomainException;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            /** @var TenantContext $ctx */
            $ctx = app(TenantContext::class);
            if ($ctx->isBypassed()) {
                return;
            }
            $activeCompanyId = $ctx->requireCompanyId();
            if ($model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', $activeCompanyId);
            } elseif ((int) $model->getAttribute('company_id') !== $activeCompanyId) {
                throw new DomainException('CROSS_TENANT_WRITE_DENIED: company_id mismatch on create.');
            }
        });

        static::updating(function (Model $model): void {
            /** @var TenantContext $ctx */
            $ctx = app(TenantContext::class);
            if ($ctx->isBypassed()) {
                return;
            }
            $activeCompanyId = $ctx->requireCompanyId();
            if ($model->isDirty('company_id') || (int) $model->getAttribute('company_id') !== $activeCompanyId) {
                throw new DomainException('CROSS_TENANT_WRITE_DENIED: Cannot modify or write cross-tenant company_id.');
            }
        });
    }
}
