<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Driver;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

abstract class AppFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Lấy company_id từ TenantContext → users.driver_id → null */
    protected function tenantCompanyId(): ?int
    {
        $tenantId = app(TenantContext::class)->getCompanyId();
        if ($tenantId !== null && $tenantId > 0) {
            return $tenantId;
        }

        $driverId = $this->user()?->getAttribute('driver_id');
        if (is_int($driverId) || ctype_digit((string) $driverId)) {
            $companyId = Driver::query()->whereKey((int) $driverId)->value('company_id');

            return $companyId !== null ? (int) $companyId : null;
        }

        return null;
    }

    protected function authorizePermission(string $module, string $action): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return $user->hasPermission("{$module}:{$action}", $this->tenantCompanyId());
    }

    protected function existsInCompany(string $table, string $column = 'id'): Rule
    {
        $rule = Rule::exists($table, $column);
        $companyId = $this->tenantCompanyId();

        if ($companyId !== null && Schema::hasColumn($table, 'company_id')) {
            $rule->where('company_id', $companyId);
        }

        return $rule;
    }
}
