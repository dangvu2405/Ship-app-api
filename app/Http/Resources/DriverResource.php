<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'dob' => $this->dob?->toDateString(),
            'gender' => $this->gender,
            'address' => $this->address,
            'avatar_url' => $this->avatar_url,
            'national_id_no' => $this->national_id_no,
            'national_id_issue_date' => $this->national_id_issue_date?->toDateString(),
            'national_id_issue_place' => $this->national_id_issue_place,
            'license_no' => $this->license_no,
            'license_class' => $this->license_class,
            'license_image_url' => $this->license_image_url,
            'identity_image_url' => $this->identity_image_url,
            'expired_date' => $this->expired_date?->toDateString(),
            'driver_insurance_no' => $this->driver_insurance_no,
            'driver_insurance_expired_date' => $this->driver_insurance_expired_date?->toDateString(),
            'health_certificate_no' => $this->health_certificate_no,
            'health_certificate_expired_date' => $this->health_certificate_expired_date?->toDateString(),
            'bank_name' => $this->bank_name,
            'bank_account_no' => $this->bank_account_no,
            'bank_account_name' => $this->bank_account_name,
            'available_status' => $this->available_status,
            'status' => $this->status,
            'join_date' => $this->join_date?->toDateString(),
            'resign_date' => $this->resign_date?->toDateString(),
            'current_vehicle' => $this->whenLoaded('currentVehicle', fn () => [
                'id' => $this->currentVehicle?->id,
                'plate_number' => $this->currentVehicle?->plate_number,
            ]),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
