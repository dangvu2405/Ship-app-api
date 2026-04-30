<?php

declare(strict_types=1);

namespace App\Http\Requests\VehicleDocument;

use App\Http\Requests\AppFormRequest;
use Illuminate\Validation\Rule;

final class UpdateVehicleDocumentRequest extends AppFormRequest
{
    public function rules(): array
    {
        return [
            'vehicle_id' => ['sometimes', 'integer', Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('company_id', $this->tenantCompanyId()))],
            'doc_type' => ['sometimes', 'in:registration,inspection,liability_insurance,vehicle_insurance,badge,photo,other'],
            'doc_name' => ['sometimes', 'string', 'max:200'],
            'doc_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'issued_date' => ['sometimes', 'nullable', 'date'],
            'expiry_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:issued_date'],
            'issuer' => ['sometimes', 'nullable', 'string', 'max:200'],
            'file_url' => ['sometimes', 'string', 'max:500', 'regex:/\.(pdf|png|jpg|jpeg|webp)$/i'],
            'alert_before_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
