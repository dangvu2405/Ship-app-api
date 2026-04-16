<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class RevenueSummaryRequest extends FormRequest
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
            'company_id' => 'nullable|integer|exists:companies,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ];
    }
}
