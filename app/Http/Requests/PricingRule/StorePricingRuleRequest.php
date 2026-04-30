<?php

declare(strict_types=1);

namespace App\Http\Requests\PricingRule;

use App\Http\Requests\AppFormRequest;

final class StorePricingRuleRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'office_id' => ['nullable', 'integer', 'exists:offices,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'base_freight' => ['required', 'numeric', 'gte:0'],
            'rate_per_km' => ['required', 'numeric', 'gte:0'],
            'fuel_adjustment' => ['nullable', 'numeric'],
            'max_discount_percent' => ['nullable', 'numeric', 'gte:0'],
            'minimum_margin_percent' => ['nullable', 'numeric', 'gte:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
