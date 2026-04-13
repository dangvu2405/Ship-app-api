<?php

declare(strict_types=1);

namespace App\Http\Requests\Violation;

use Illuminate\Foundation\Http\FormRequest;

class DisputeViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason'         => ['required', 'string', 'max:2000'],
            'evidence_urls'  => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
        ];
    }
}
