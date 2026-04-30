<?php

declare(strict_types=1);

namespace App\Http\Requests\TripBonusRule;

use Illuminate\Foundation\Http\FormRequest;

class StoreTripBonusRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'min_km' => ['required', 'numeric', 'min:0'],
            'max_km' => ['nullable', 'numeric', 'gt:min_km'],
            'bonus_per_km' => ['required', 'numeric', 'min:0'],
        ];
    }
}
