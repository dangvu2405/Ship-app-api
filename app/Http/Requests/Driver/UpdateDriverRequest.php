<?php

declare(strict_types=1);

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
            // Personal info
            'code' => 'sometimes|string|max:50|unique:drivers,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|unique:drivers,email,' . $id,
            'phone' => 'nullable|string|max:20|regex:/^0[0-9]{9,10}$/',
            'dob' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string',
            'avatar_url' => 'nullable|url|max:255',
            'national_id_no' => 'nullable|string|max:30',
            'national_id_issue_date' => 'nullable|date',
            'national_id_issue_place' => 'nullable|string|max:255',
            'social_insurance_no' => 'nullable|string|max:30',
            'health_insurance_no' => 'nullable|string|max:30',
            'insurance_registered_at' => 'nullable|date',
            // Organization
            'office_id' => 'sometimes|exists:offices,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'sometimes|exists:positions,id',
            // Status
            'status' => 'sometimes|in:active,inactive,resigned',
            'join_date' => 'sometimes|date',
            'resign_date' => 'nullable|date|after_or_equal:join_date',
            // Bank
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            // Driver-specific
            'license_no' => 'sometimes|string|max:50',
            'license_image_url' => 'nullable|url|max:255',
            'identity_image_url' => 'nullable|url|max:255',
            'driver_insurance_no' => 'nullable|string|max:30',
            'driver_insurance_expired_date' => 'nullable|date',
            'health_certificate_no' => 'nullable|string|max:30',
            'health_certificate_expired_date' => 'nullable|date',
            'license_class' => 'nullable|string|max:20',
            'expired_date' => 'nullable|date',
            'available_status' => 'sometimes|in:available,busy,offline',
        ];
    }
}
