<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Applies a global scope when {@see TenantContext::getCompanyId()} is non-null.
 *
 * Models may override {@see static::getTenantThroughRelation()} to filter via a relation
 * when the table has no `company_id` column yet (e.g. before a pending migration).
 */
trait BelongsToTenant
{
    /**
     * Tables known to have a `company_id` column (avoids repeat schema lookups).
     *
     * @var array<string, true>
     */
    private static array $tenantCompanyColumnKnown = [];

    /**
     * Relation name whose related table has `company_id` (for example `driver`).
     */
    public static function getTenantThroughRelation(): ?string
    {
        return null;
    }

    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $companyId = app(TenantContext::class)->getCompanyId();

            if ($companyId === null) {
                return;
            }

            /** @var Model $model */
            $model = $builder->getModel();
            $table = $model->getTable();
            $through = $model::getTenantThroughRelation();

            if ($through !== null) {
                if (! isset(self::$tenantCompanyColumnKnown[$table])) {
                    if (! Schema::hasColumn($table, 'company_id')) {
                        $builder->whereHas($through, function (Builder $q) use ($companyId): void {
                            $q->withoutGlobalScopes();
                            $q->where($q->getModel()->qualifyColumn('company_id'), $companyId);
                        });

                        return;
                    }

                    self::$tenantCompanyColumnKnown[$table] = true;
                }
            }

            $builder->where($model->qualifyColumn('company_id'), $companyId);
        });
    }
}
