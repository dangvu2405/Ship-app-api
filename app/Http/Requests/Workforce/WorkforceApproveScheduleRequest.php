<?php

declare(strict_types=1);

namespace App\Http\Requests\Workforce;

use Illuminate\Foundation\Http\FormRequest;

final class WorkforceApproveScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('schedule.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hos_override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500', 'required_if:hos_override,true'],
        ];
    }
}
