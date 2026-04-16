<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver_id' => $this->driver_id,
            'leave_type_id' => $this->leave_type_id,
            'from_date' => $this->from_date?->toDateString(),
            'to_date' => $this->to_date?->toDateString(),
            'total_days' => (float) $this->total_days,
            'status' => $this->status,
            'reason' => $this->reason,
            'leave_type' => $this->whenLoaded('leaveType', function (): ?array {
                if ($this->leaveType === null) {
                    return null;
                }

                return [
                    'id' => $this->leaveType->id,
                    'name' => $this->leaveType->name,
                    'is_paid' => (bool) $this->leaveType->is_paid,
                ];
            }),
        ];
    }
}
