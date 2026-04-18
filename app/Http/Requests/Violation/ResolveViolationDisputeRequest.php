<?php

declare(strict_types=1);

namespace App\Http\Requests\Violation;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveViolationDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'in:upheld,overturned'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
