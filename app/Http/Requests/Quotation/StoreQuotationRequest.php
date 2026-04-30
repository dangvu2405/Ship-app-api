<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotation;

use App\Http\Requests\AppFormRequest;

class StoreQuotationRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'transport_request_id' => ['required', 'integer', 'exists:transport_requests,id'],
            'pricing_rule_id' => ['nullable', 'integer', 'exists:pricing_rules,id'],
            'distance_km' => ['nullable', 'numeric', 'gte:0'],
            'cost_amount' => ['nullable', 'numeric', 'gte:0'],
            'status' => ['sometimes', 'in:pending_pricing,need_manual_pricing,pending_approval,approved,rejected'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

