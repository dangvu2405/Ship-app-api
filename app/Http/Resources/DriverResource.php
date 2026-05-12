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
            'license_no' => $this->license_no,
            'license_class' => $this->license_class,
            'expired_date' => $this->expired_date?->toDateString(),
            'available_status' => $this->available_status,
            'status' => $this->status,
            'join_date' => $this->join_date?->toDateString(),
            'resign_date' => $this->resign_date?->toDateString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
