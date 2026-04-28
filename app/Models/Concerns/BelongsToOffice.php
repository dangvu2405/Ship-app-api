<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a global scope that restricts queries to a single office when
 * TenantContext::getOfficeId() is non-null (i.e. the current user is an office_admin).
 *
 * Apply this trait to any model that has an `office_id` column:
 *   Driver, DriverWorkSchedule, Department, Vehicle, OfficeScheduleApplication, …
 *
 * company_admin and admin users have officeId = null → scope is a no-op.
 */
trait BelongsToOffice
{
    public static function bootBelongsToOffice(): void
    {
        static::addGlobalScope('office', function (Builder $builder): void {
            $officeId = app(TenantContext::class)->getOfficeId();

            if ($officeId === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->qualifyColumn('office_id'),
                $officeId
            );
        });
    }
}
