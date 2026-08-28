<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::bypassed()) {
            return;
        }

        $tenantId = TenantContext::id();
        if ($tenantId) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
