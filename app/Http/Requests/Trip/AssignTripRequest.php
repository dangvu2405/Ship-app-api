<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use Illuminate\Foundation\Http\FormRequest;

final class AssignTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id' => ['required', 'integer', 'exists:drivers,id'],
        ];
    }
}
