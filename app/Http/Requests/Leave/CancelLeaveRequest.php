<?php

declare(strict_types=1);

namespace App\Http\Requests\Leave;

use App\Http\Requests\AppFormRequest;

class CancelLeaveRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('drivers', 'edit');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
