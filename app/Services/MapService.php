<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class MapService
{
    private string $driver;
    private ?string $googleKey;
    private ?string $mapboxToken;
    private ?string $goongKey;

    public function __construct()
    {
        $this->driver = config('services.map.driver', 'google');
        $this->googleKey = config('services.map.google_key');
        $this->mapboxToken = config('services.map.mapbox_token');
        $this->goongKey = config('services.map.goong_key');
    }

    /**
     * Get distance between two points in kilometers.
     */
    public function getDistance(string $origin, string $destination): ?float
    {
        return match ($this->driver) {
            'google' => $this->getGoogleDistance($origin, $destination),
            'mapbox' => $this->getMapboxDistance($origin, $destination),
            'goong' => $this->getGoongDistance($origin, $destination),
            default => null,
        };
    }

    private function getGoogleDistance(string $origin, string $destination): ?float
    {
        if (!$this->googleKey) {
            Log::warning('Google Maps API Key is not set.');
            return null;
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => $origin,
                'destinations' => $destination,
                'key' => $this->googleKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['status'] === 'OK' && isset($data['rows'][0]['elements'][0]['distance']['value'])) {
                    // Distance is in meters, convert to km
                    return $data['rows'][0]['elements'][0]['distance']['value'] / 1000;
                }
            }
            Log::error('Google Distance Matrix API error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('Google Distance Matrix API exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    private function getMapboxDistance(string $origin, string $destination): ?float
    {
        // Placeholder for Mapbox implementation
        // Mapbox usually expects coordinates (longitude,latitude)
        return null;
    }

    private function getGoongDistance(string $origin, string $destination): ?float
    {
        if (!$this->goongKey) {
            Log::warning('Goong API Key is not set.');
            return null;
        }

        try {
            $response = Http::get('https://rsapi.goong.io/DistanceMatrix', [
                'origins' => $origin,
                'destinations' => $destination,
                'api_key' => $this->goongKey,
                'vehicle' => 'car',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rows'][0]['elements'][0]['distance']['value'])) {
                    return $data['rows'][0]['elements'][0]['distance']['value'] / 1000;
                }
            }
            Log::error('Goong Distance Matrix API error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('Goong Distance Matrix API exception', ['message' => $e->getMessage()]);
        }

        return null;
    }
}
