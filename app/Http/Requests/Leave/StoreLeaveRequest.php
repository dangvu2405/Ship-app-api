<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('company_id')) {
            return;
        }

        $companyId = app(TenantContext::class)->getCompanyId();
        if ($companyId !== null && $companyId > 0) {
            $this->merge(['company_id' => $companyId]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'total_days' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment_urls' => ['nullable', 'array'],
            'attachment_urls.*' => ['url'],
        ];
    }
}
