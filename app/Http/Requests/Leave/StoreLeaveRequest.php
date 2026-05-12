<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('drivers', 'create');
    }

    public function prepareForValidation(): void
    {
        $companyId = $this->tenantCompanyId();
        if ($companyId !== null && $companyId > 0) {
            $this->merge(['company_id' => $companyId]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $tenantId = $this->tenantCompanyId();

        return [
            'company_id' => array_values(array_filter([
                'required',
                'integer',
                Rule::exists('companies', 'id'),
                $tenantId !== null ? Rule::in([$tenantId]) : null,
            ])),
            'driver_id' => ['required', 'integer', $this->existsInCompany('drivers')],
            'leave_type_id' => ['required', 'integer', $this->existsInCompany('leave_types')],
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'total_days' => ['required', 'numeric', 'min:0.5', 'max:365'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment_urls' => ['nullable', 'array'],
            'attachment_urls.*' => ['url'],
        ];
    }
}
