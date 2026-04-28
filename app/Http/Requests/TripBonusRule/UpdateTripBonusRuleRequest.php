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
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:effective_from'],
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
            // Validate effective range when both dates are present.
            if ($this->has('effective_to') || $this->has('effective_from')) {
                $effectiveFrom = $this->input('effective_from');
                if ($effectiveFrom === null && $this->has('trip_bonus_rule')) {
                    $effectiveFrom = (string) $this->route('trip_bonus_rule')?->effective_from?->format('Y-m-d');
                }

                $effectiveTo = $this->input('effective_to');
                if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveTo !== '' && $effectiveFrom !== '' && $effectiveTo < $effectiveFrom) {
                    $validator->errors()->add('effective_to', __('api.validation.trip_bonus_effective_to_invalid'));
                }
            }

            if (! $this->has('max_km')) {
                return;
            }

            $minKm = (float) ($this->input('min_km') ?? 0);
            $maxKm = $this->input('max_km');

            if ($maxKm !== null && $maxKm !== '' && (float) $maxKm <= $minKm) {
                $validator->errors()->add('max_km', __('api.validation.trip_bonus_max_km_invalid'));
            }
        });
    }
}
