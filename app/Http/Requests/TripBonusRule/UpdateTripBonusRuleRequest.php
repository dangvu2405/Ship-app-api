<?php

declare(strict_types=1);

namespace App\Http\Requests\TripBonusRule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTripBonusRuleRequest extends FormRequest
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
            'min_km' => ['sometimes', 'numeric', 'min:0'],
            'max_km' => ['nullable', 'numeric'],
            'bonus_per_km' => ['sometimes', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('max_km') && $this->input('max_km') === '') {
            $this->merge(['max_km' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->has('max_km')) {
                return;
            }

            $minKm = (float) ($this->input('min_km') ?? 0);
            $maxKm = $this->input('max_km');

            if ($maxKm !== null && $maxKm !== '' && (float) $maxKm <= $minKm) {
                $validator->errors()->add('max_km', 'The max km field must be greater than min km.');
            }
        });
    }
}
