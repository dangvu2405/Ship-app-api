<?php

declare(strict_types=1);

namespace App\Http\Requests\Location;

use App\Http\Requests\AppFormRequest;

final class UpdateLocationRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'],
            'address' => ['sometimes', 'string'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
