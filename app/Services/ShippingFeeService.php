<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class ShippingFeeService
{
    private MapService $mapService;

    public function __construct(MapService $mapService)
    {
        $this->mapService = $mapService;
    }

    /**
     * Calculate shipping fee based on origin and destination.
     */
    public function calculate(string $origin, string $destination, ?int $vehicleTypeId = null): array
    {
        $cacheKey = 'dist_' . md5($origin . '|' . $destination);
        
        // Try to get distance from cache
        $distance = Cache::remember($cacheKey, now()->addDays(30), function () use ($origin, $destination) {
            return $this->mapService->getDistance($origin, $destination);
        });

        if ($distance === null) {
            return [
                'success' => false,
                'message' => 'Could not calculate distance.',
            ];
        }

        $fee = $this->applyFormula($distance, $vehicleTypeId);

        return [
            'success' => true,
            'distance_km' => round($distance, 2),
            'shipping_fee' => $fee,
        ];
    }

    private function applyFormula(float $distance, ?int $vehicleTypeId = null): float
    {
        // Default formula: 15,000 base + 5,000 per km
        // In a real app, this should be configurable per shop/tenant.
        $baseFee = (float) config('services.shipping.base_fee', 15000);
        $feePerKm = (float) config('services.shipping.fee_per_km', 5000);

        // Adjust based on vehicle type if available
        if ($vehicleTypeId) {
            $multiplier = DB::table('vehicle_types')
                ->where('id', $vehicleTypeId)
                ->value('price_multiplier') ?? 1.0;
            $feePerKm *= $multiplier;
        }

        return $baseFee + ($distance * $feePerKm);
    }
}
