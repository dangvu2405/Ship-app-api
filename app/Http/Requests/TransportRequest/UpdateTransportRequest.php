<?php

declare(strict_types=1);

namespace App\Http\Requests\TransportRequest;

use App\Http\Requests\AppFormRequest;

class UpdateTransportRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'pickup_location' => ['sometimes', 'string', 'max:255'],
            'delivery_location' => ['sometimes', 'string', 'max:255', 'different:pickup_location'],
            'cargo_type' => ['sometimes', 'nullable', 'string', 'max:120'],
            'cargo_weight' => ['sometimes', 'numeric', 'gt:0'],
            'cargo_volume' => ['sometimes', 'nullable', 'numeric', 'gte:0'],
            'requested_delivery_date' => ['sometimes', 'date', 'after_or_equal:today'],
            'service_type' => ['sometimes', 'nullable', 'string', 'max:60'],
            'payment_term' => ['sometimes', 'nullable', 'string', 'max:60'],
            'special_requirement' => ['sometimes', 'nullable', 'string'],
            'fragile_flag' => ['sometimes', 'boolean'],
            'cold_chain_flag' => ['sometimes', 'boolean'],
            'dangerous_goods_flag' => ['sometimes', 'boolean'],
            'loading_support_required' => ['sometimes', 'boolean'],
            'insurance_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:draft,pending_pricing,pending_approval,approved,rejected,expired'],
            'rejection_reason' => ['required_if:status,rejected', 'nullable', 'string'],
        ];
    }
}

