<?php

declare(strict_types=1);

namespace App\Http\Requests\TransportRequest;

use App\Http\Requests\AppFormRequest;

class StoreTransportRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'pickup_location' => ['required', 'string', 'max:255'],
            'delivery_location' => ['required', 'string', 'max:255', 'different:pickup_location'],
            'cargo_type' => ['nullable', 'string', 'max:120'],
            'cargo_weight' => ['required', 'numeric', 'gt:0'],
            'cargo_volume' => ['nullable', 'numeric', 'gte:0'],
            'requested_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'service_type' => ['nullable', 'string', 'max:60'],
            'payment_term' => ['nullable', 'string', 'max:60'],
            'special_requirement' => ['nullable', 'string'],
            'fragile_flag' => ['sometimes', 'boolean'],
            'cold_chain_flag' => ['sometimes', 'boolean'],
            'dangerous_goods_flag' => ['sometimes', 'boolean'],
            'loading_support_required' => ['sometimes', 'boolean'],
            'insurance_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'in:draft,pending_pricing,pending_approval,approved,rejected,expired'],
        ];
    }
}

