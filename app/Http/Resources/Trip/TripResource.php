<?php

declare(strict_types=1);

namespace App\Http\Resources\Trip;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $status
 * @property float $total_revenue
 * @property \Illuminate\Support\Collection $stops
 * @property \Illuminate\Support\Collection $surcharges
 */
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
            'code' => $this->whenHas('code'),
            'status' => $this->status,
            'customer' => [
                'id' => $this->customer_id,
                'name' => $this->whenLoaded('customer', fn() => $this->customer->name),
            ],
            'revenue' => [
                'base_price' => (float) $this->base_price,
                'surcharge_amount' => (float) $this->surcharge_amount,
                'total_revenue' => (float) $this->total_revenue,
            ],
            'dates' => [
                'received_date' => $this->received_date,
                'scheduled_date' => $this->scheduled_date,
                'start_time' => $this->start_time,
                'end_time' => $this->end_time,
            ],
            'stops' => TripStopResource::collection($this->whenLoaded('stops')),
            'surcharges' => TripSurchargeResource::collection($this->whenLoaded('surcharges')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
