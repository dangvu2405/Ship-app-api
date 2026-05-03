<?php

declare(strict_types=1);

namespace App\Http\Requests\Driver;

use App\Http\Requests\AppFormRequest;

class UpdateDriverRequest extends AppFormRequest
{
    public function rules(): array
    {
        $id = $this->route('driver');

        return [
            // Thông tin cá nhân
            'code' => 'sometimes|string|max:50|unique:drivers,code,'.$id,
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255|unique:drivers,email,'.$id,
            'phone' => 'nullable|string|max:20|regex:/^0[0-9]{9,10}$/',
            'dob' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'avatar_url' => 'nullable|url|max:255',
            'national_id_no' => 'nullable|string|max:30',
            'national_id_issue_date' => 'nullable|date',
            'national_id_issue_place' => 'nullable|string|max:255',
            'social_insurance_no' => 'nullable|string|max:30',
            'health_insurance_no' => 'nullable|string|max:30',
            'insurance_registered_at' => 'nullable|date',
            // Tổ chức
            'office_id' => 'sometimes|exists:offices,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'sometimes|exists:positions,id',
            // Trạng thái
            'status' => 'sometimes|in:active,inactive,resigned',
            'join_date' => 'sometimes|date',
            'resign_date' => 'nullable|date|after_or_equal:join_date',
            // Ngân hàng
            'bank_name' => 'nullable|string|max:255',
            'bank_account_no' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            // Thông tin tài xế
            'license_no' => 'sometimes|string|max:50|unique:drivers,license_no,'.$id,
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

    public function messages(): array
    {
        return [
            'code.max' => __('api.validation.max.string'),
            'code.unique' => __('api.validation.unique'),
            'name.max' => __('api.validation.max.string'),
            'email.email' => __('api.validation.email'),
            'email.max' => __('api.validation.max.string'),
            'email.unique' => __('api.validation.unique'),
            'phone.regex' => __('api.validation.phone_vn'),
            'gender.in' => __('api.validation.in'),
            'office_id.exists' => __('api.validation.exists'),
            'position_id.exists' => __('api.validation.exists'),
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
