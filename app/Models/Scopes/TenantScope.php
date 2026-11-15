<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $ctx */
        $ctx = app(TenantContext::class);

        if ($ctx->isBypassed()) {
            return;
        }

        $companyId = $ctx->id();
        if ($companyId === null) {
            // Fail-closed: never leak cross-tenant rows when tenant context is unset
            $builder->whereRaw('1 = 0');
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), '=', $companyId);
    }
}
