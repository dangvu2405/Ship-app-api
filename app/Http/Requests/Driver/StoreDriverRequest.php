<?php

declare(strict_types=1);

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id|unique:drivers,employee_id',
            'license_no' => 'required|string|max:50',
            'license_image_url' => 'nullable|url|max:255',
            'identity_image_url' => 'nullable|url|max:255',
            'driver_insurance_no' => 'nullable|string|max:30',
            'driver_insurance_expired_date' => 'nullable|date',
            'health_certificate_no' => 'nullable|string|max:30',
            'health_certificate_expired_date' => 'nullable|date',
            'license_class' => 'nullable|string|max:20',
            'expired_date' => 'nullable|date',
            'available_status' => 'required|in:available,busy,offline',
        ];
    }
}
