<?php

declare(strict_types=1);

namespace App\Http\Requests\PublicHoliday;

use Illuminate\Foundation\Http\FormRequest;

final class IndexPublicHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('year') || $this->input('year') === '' || $this->input('year') === null) {
            $this->merge(['year' => (int) now()->year]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'country_code' => ['nullable', 'string', 'max:5', 'in:VN,US,UK,JP,KR,CN,TH,SG,FR,DE,AU,CA'],
        ];
    }
}
