<?php

declare(strict_types=1);

namespace App\Http\Requests\Violation;

use Illuminate\Foundation\Http\FormRequest;

final class WaiveViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'waive_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
