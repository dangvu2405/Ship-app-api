<?php

declare(strict_types=1);

namespace App\Http\Requests\PriceList;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdatePriceListRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => [
                'sometimes',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId())),
            ],
            'name' => ['sometimes', 'string', 'max:200'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
