<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Http\Requests\AppFormRequest;

final class RejectLeaveRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('drivers', 'approve');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
