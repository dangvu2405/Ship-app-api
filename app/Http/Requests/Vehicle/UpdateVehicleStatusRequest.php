<?php

declare(strict_types=1);

namespace App\Http\Requests\Vehicle;

use App\Http\Requests\AppFormRequest;

class UpdateVehicleStatusRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('vehicles', 'edit');
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:active,maintenance,inactive,broken',
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('api.validation.required'),
            'status.in' => __('api.validation.in'),
        ];
    }
}
