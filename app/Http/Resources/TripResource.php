<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
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
            'code' => $this->code,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'driver_id' => $this->driver_id,
            'driver' => DriverResource::make($this->whenLoaded('driver')),
            'vehicle_id' => $this->vehicle_id,
            'vehicle' => VehicleResource::make($this->whenLoaded('vehicle')),
            'received_date' => $this->received_date?->toDateString(),
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'base_price' => $this->base_price,
            'surcharge_amount' => $this->surcharge_amount,
            'total_revenue' => $this->total_revenue,
            'status' => $this->status,
            'start_point' => $this->start_point,
            'end_point' => $this->end_point,
            'distance_km' => $this->distance_km,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'stops' => TripStopResource::collection($this->whenLoaded('stops')),
            'surcharges' => TripSurchargeResource::collection($this->whenLoaded('surcharges')),
            'costs' => TripCostResource::collection($this->whenLoaded('costs')),
            'documents' => TripDocumentResource::collection($this->whenLoaded('documents')),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
