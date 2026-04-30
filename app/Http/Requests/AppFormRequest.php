<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Driver;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

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
}
