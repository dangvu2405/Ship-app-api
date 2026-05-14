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
            'base_price' => (float) $this->base_price,
            'surcharge_amount' => (float) $this->surcharge_amount,
            'total_revenue' => (float) $this->total_revenue,
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
            'assigned_at'         => $this->assigned_at?->toDateTimeString(),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at'        => $this->cancelled_at?->toDateTimeString(),
            'cancelled_by'        => $this->cancelled_by,
            'actual_delivered_at' => $this->actual_delivered_at?->toDateTimeString(),
            'payment_status'      => $this->payment_status,
            'dispatcher_id'       => $this->dispatcher_id,
            'status_histories'    => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'from_status' => $h->from_status,
                'to_status'   => $h->to_status,
                'changed_by'  => $h->changed_by,
                'changed_at'  => $h->changed_at,
                'note'        => $h->note,
            ])),
            'created_at'          => $this->created_at?->toDateTimeString(),
            'updated_at'          => $this->updated_at?->toDateTimeString(),
        ];
    }
}
