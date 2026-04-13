<?php

declare(strict_types=1);

namespace App\Http\Requests\Violation;

use Illuminate\Foundation\Http\FormRequest;

class StoreViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'driver_id'      => ['required', 'integer', 'exists:drivers,id'],
            'company_id'     => ['required', 'integer', 'exists:companies,id'],
            'trip_id'        => ['nullable', 'integer', 'exists:trips,id'],
            'type'           => ['required', 'string', 'in:speeding,route_deviation,fuel_misuse,behavior,accident,other'],
            'occurred_at'    => ['required', 'date'],
            'description'    => ['required', 'string', 'max:2000'],
            'penalty_amount' => ['required', 'numeric', 'min:0'],
            'evidence_urls'  => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
        ];
    }
}
