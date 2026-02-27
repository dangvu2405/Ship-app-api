<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('driver');

        return [
            'employee_id' => 'sometimes|exists:employees,id|unique:drivers,employee_id,' . $id,
            'license_no' => 'sometimes|string|max:50',
            'license_class' => 'nullable|string|max:20',
            'expired_date' => 'nullable|date',
            'available_status' => 'sometimes|in:available,busy,offline',
        ];
    }
}
