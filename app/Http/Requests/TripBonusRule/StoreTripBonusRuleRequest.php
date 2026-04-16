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

    public function prepareForValidation(): void
    {
        if (! $this->filled('company_id') && $this->user()?->driver?->company_id !== null) {
            $this->merge(['company_id' => $this->user()->driver->company_id]);
        }

        if (! $this->filled('effective_from')) {
            $this->merge(['effective_from' => now()->toDateString()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'min_km' => ['required', 'numeric', 'min:0'],
            'max_km' => ['nullable', 'numeric', 'gt:min_km'],
            'bonus_per_km' => ['required', 'numeric', 'min:0'],
        ];
    }
}
