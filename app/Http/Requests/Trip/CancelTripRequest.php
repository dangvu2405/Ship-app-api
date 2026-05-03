<?php

declare(strict_types=1);

namespace App\Http\Requests\Trip;

use App\Http\Requests\AppFormRequest;

class CancelTripRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.string' => __('api.validation.string'),
            'reason.max' => __('api.validation.max.string'),
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => __('api.attributes.trip_cancel_reason'),
        ];
    }
}
