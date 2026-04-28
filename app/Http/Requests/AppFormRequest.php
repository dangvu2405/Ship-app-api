<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

abstract class AppFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Lấy company_id từ TenantContext → user driver → null */
    protected function tenantCompanyId(): ?int
    {
        $tenantId = app(TenantContext::class)->getCompanyId();
        if ($tenantId !== null && $tenantId > 0) {
            return $tenantId;
        }

        return $this->user()?->driver?->company_id;
    }
}
