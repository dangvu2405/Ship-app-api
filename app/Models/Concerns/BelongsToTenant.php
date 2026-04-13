<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies a global scope when {@see TenantContext::getCompanyId()} is non-null.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $companyId = app(TenantContext::class)->getCompanyId();

            if ($companyId === null) {
                return;
            }

            /** @var Model $model */
            $model = $builder->getModel();
            $builder->where($model->qualifyColumn('company_id'), $companyId);
        });
    }
}
