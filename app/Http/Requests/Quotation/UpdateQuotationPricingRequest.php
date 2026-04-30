<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use App\Http\Requests\AppFormRequest;

class UpdateQuotationPricingRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'distance_km' => ['required', 'numeric', 'gte:0'],
            'base_freight' => ['required', 'numeric', 'gte:0'],
            'surcharges_total' => ['required', 'numeric', 'gte:0'],
            'special_fees_total' => ['required', 'numeric', 'gte:0'],
            'discount_total' => ['required', 'numeric', 'gte:0'],
            'vat_amount' => ['required', 'numeric', 'gte:0'],
            'cost_amount' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.type' => ['required_with:items', 'in:surcharge,special_fee,discount,vat'],
            'items.*.code' => ['nullable', 'string', 'max:80'],
            'items.*.label' => ['required_with:items', 'string', 'max:120'],
            'items.*.amount' => ['required_with:items', 'numeric'],
            'items.*.is_mandatory' => ['sometimes', 'boolean'],
        ];
    }
}

