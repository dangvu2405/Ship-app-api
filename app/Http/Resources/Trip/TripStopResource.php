<?php

declare(strict_types=1);

namespace App\Http\Resources\Trip;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripStopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stop_type' => $this->stop_type,
            'sequence' => $this->sequence,
            'address' => $this->address,
            'status' => $this->status,
            'scheduled_time' => $this->scheduled_time,
            'actual_time' => $this->actual_time,
        ];
    }
}
