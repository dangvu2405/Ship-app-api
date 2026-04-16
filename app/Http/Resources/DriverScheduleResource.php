<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'vehicle_id' => $this->vehicle_id,
            'office_id' => $this->office_id,
            'work_date' => $this->work_date?->toDateString(),
            'shift_code' => $this->shift_code,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'notes' => $this->notes,
            'vehicle' => $this->whenLoaded('vehicle', function (): ?array {
                if ($this->vehicle === null) {
                    return null;
                }

                return [
                    'id' => $this->vehicle->id,
                    'plate_number' => $this->vehicle->plate_number,
                ];
            }),
        ];
    }
}
