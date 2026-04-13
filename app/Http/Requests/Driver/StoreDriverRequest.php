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
            // Personal info
            'code' => 'required|string|max:50|unique:drivers,code',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:drivers,email',
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
            'office_id' => 'required|exists:offices,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            // Status
            'status' => 'required|in:active,inactive,resigned',
            'join_date' => 'required|date',
            'resign_date' => 'nullable|date|after_or_equal:join_date',
            // Bank
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            // Driver-specific
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
