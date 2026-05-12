<?php

declare(strict_types=1);

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class BaseService
{
    public function __construct(protected readonly TenantContext $tenantContext) {}

    protected function companyId(): int
    {
        $companyId = $this->tenantContext->getCompanyId();
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        $user = auth()->user();
        if ($user && isset($user->driver_id) && $user->driver_id !== null) {
            $resolved = DB::table('drivers')->where('id', $user->driver_id)->value('company_id');
            if ($resolved !== null) {
                return (int) $resolved;
            }
        }

        abort(403, 'Không thể xác định company_id.');
    }

    protected function scopedQuery(string $table): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull("{$table}.deleted_at");
        }
        if (Schema::hasColumn($table, 'company_id')) {
            $query->where("{$table}.company_id", $this->companyId());
        }

        return $query;
    }
}
