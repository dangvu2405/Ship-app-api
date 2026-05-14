<?php

declare(strict_types=1);

namespace App\Http\Requests\Driver;

use App\Http\Requests\AppFormRequest;
use App\Models\Driver;
use Illuminate\Validation\Rule;

class UpdateDriverRequest extends AppFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizePermission('drivers', 'edit');
    }

    public function rules(): array
    {
        $driver = $this->route('driver');
        $driverId = $driver instanceof Driver ? $driver->getKey() : $driver;

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('drivers', 'email')->ignore($driverId)],
            'phone' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('drivers', 'phone')->ignore($driverId)],
            'dob' => 'sometimes|nullable|date',
            'gender' => 'sometimes|nullable|string|in:male,female,other',
            'address' => 'sometimes|nullable|string|max:255',
            'avatar_url' => 'sometimes|nullable|url|max:255',
            'national_id_no' => 'sometimes|nullable|string|max:30',
            'national_id_issue_date' => 'sometimes|nullable|date',
            'national_id_issue_place' => 'sometimes|nullable|string|max:255',
            'social_insurance_no' => 'sometimes|nullable|string|max:30',
            'status' => 'sometimes|nullable|string|in:active,inactive,resigned',
            'join_date' => 'sometimes|nullable|date',
            'resign_date' => 'sometimes|nullable|date|after_or_equal:join_date',
            'bank_name' => 'sometimes|nullable|string|max:255',
            'bank_account_no' => 'sometimes|nullable|string|max:50',
            'bank_account_name' => 'sometimes|nullable|string|max:255',
            'license_no' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('drivers', 'license_no')->ignore($driverId)],
            'license_image_url' => 'sometimes|nullable|url|max:255',
            'driver_insurance_no' => 'sometimes|nullable|string|max:30',
            'driver_insurance_expired_date' => 'sometimes|nullable|date',
            'health_certificate_no' => 'sometimes|nullable|string|max:30',
            'health_certificate_expired_date' => 'sometimes|nullable|date',
            'license_class' => 'sometimes|required|string|max:255',
            'expired_date' => 'sometimes|nullable|date',
            'license_alert_days' => 'sometimes|nullable|integer|min:0|max:365',
            'available_status' => 'sometimes|nullable|string|in:available,busy,offline',
            'annual_leave_days' => 'sometimes|nullable|integer|min:0|max:365',
        ];
    }

    public function messages(): array
    {
        return [
            'code.max' => __('api.validation.max.string'),
            'code.unique' => __('api.validation.unique'),
            'name.max' => __('api.validation.max.string'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
            'email.unique' => __('api.validation.unique'),
            'phone.unique' => __('api.validation.unique'),
            'gender.in' => __('api.validation.in'),
            'status.in' => __('api.validation.in'),
            'resign_date.after_or_equal' => __('api.validation.after_or_equal'),
            'license_no.max' => __('api.validation.max.string'),
            'license_no.unique' => __('api.validation.unique'),
            'available_status.in' => __('api.validation.in'),
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => __('api.attributes.driver_code'),
            'name' => __('api.attributes.driver_name'),
            'email' => __('api.attributes.email'),
            'phone' => __('api.attributes.driver_phone'),
            'dob' => __('api.attributes.driver_dob'),
            'gender' => __('api.attributes.driver_gender'),
            'address' => __('api.attributes.driver_address'),
            'avatar_url' => __('api.attributes.driver_avatar_url'),
            'national_id_no' => __('api.attributes.driver_national_id_no'),
            'national_id_issue_date' => __('api.attributes.driver_national_id_issue_date'),
            'national_id_issue_place' => __('api.attributes.driver_national_id_issue_place'),
            'social_insurance_no' => __('api.attributes.driver_social_insurance_no'),
            'health_insurance_no' => __('api.attributes.driver_health_insurance_no'),
            'insurance_registered_at' => __('api.attributes.driver_insurance_registered_at'),
            'office_id' => __('api.attributes.driver_office_id'),
            'department_id' => __('api.attributes.driver_department_id'),
            'position_id' => __('api.attributes.driver_position_id'),
            'status' => __('api.attributes.driver_status'),
            'join_date' => __('api.attributes.driver_join_date'),
            'resign_date' => __('api.attributes.driver_resign_date'),
            'bank_name' => __('api.attributes.driver_bank_name'),
            'bank_account_no' => __('api.attributes.driver_bank_account_no'),
            'bank_account_name' => __('api.attributes.driver_bank_account_name'),
            'license_no' => __('api.attributes.driver_license_no'),
            'license_class' => __('api.attributes.driver_license_class'),
            'expired_date' => __('api.attributes.driver_expired_date'),
            'driver_insurance_no' => __('api.attributes.driver_insurance_no'),
            'driver_insurance_expired_date' => __('api.attributes.driver_insurance_expired_date'),
            'health_certificate_no' => __('api.attributes.driver_health_certificate_no'),
            'health_certificate_expired_date' => __('api.attributes.driver_health_certificate_expired_date'),
            'available_status' => __('api.attributes.driver_available_status'),
        ];
    }
}
