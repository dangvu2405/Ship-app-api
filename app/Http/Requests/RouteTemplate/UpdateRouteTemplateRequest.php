<?php

declare(strict_types=1);

namespace App\Http\Requests\RouteTemplate;

use App\Http\Requests\AppFormRequest;

final class UpdateRouteTemplateRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'origin_location_id' => ['sometimes', 'integer', 'exists:locations,id', 'different:destination_location_id'],
            'destination_location_id' => ['sometimes', 'integer', 'exists:locations,id'],
            'distance_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'default_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fuel_norm_liter' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'toll_norm' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
