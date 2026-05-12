<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Http\Requests\AppFormRequest;

final class AssignTripRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('orders', 'edit');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', $this->existsInCompany('drivers')],
            'vehicle_id' => ['required', 'integer', $this->existsInCompany('vehicles')],
        ];
    }
}
