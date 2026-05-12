<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Http\Requests\AppFormRequest;

class UpdateTripDetailsRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('orders', 'edit');
    }

    public function rules(): array
    {
        return [
            'distance_km' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }
}
