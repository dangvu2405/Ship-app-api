<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingFeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'distance_km' => $this->distance_km,
            'shipping_fee' => $this->shipping_fee,
        ];
    }
}