<?php

namespace App\Domain\Ride\Services;

use App\Domain\Location\Services\MapboxService;
use Exception;
use Illuminate\Support\Facades\Log;

class RideEstimationService
{
    protected MapboxService $mapboxService;

    public function __construct(MapboxService $mapboxService)
    {
        $this->mapboxService = $mapboxService;
    }
    /**
     * Estimate ride with distance, ETA, and price
     *
     * @param string|array $pickup Pickup address or [lat, lng]
     * @param string|array $dropoff Dropoff address or [lat, lng]
     * @return array
     */
    public function estimate($pickup, $dropoff): array
    {
        // Convert addresses to coordinates if needed
        $pickupCoords = is_array($pickup) ? $pickup : $this->geocodeAddress($pickup);
        $dropoffCoords = is_array($dropoff) ? $dropoff : $this->geocodeAddress($dropoff);

        // Calculate distance using Haversine formula
        $distance_km = $this->calculateDistance(
            $pickupCoords[0],
            $pickupCoords[1],
            $dropoffCoords[0],
            $dropoffCoords[1]
        );

        // Estimate ETA (assuming average speed of 30 km/h in city)
        $eta_minutes = max(5, (int) round(($distance_km / 30) * 60));

        // Calculate price
        $price = $this->calculatePrice($distance_km, $eta_minutes);

        return [
            'distance_km' => round($distance_km, 2),
            'duration_min' => $eta_minutes,
            'price' => $price,
            'pickup_coordinates' => $pickupCoords,
            'dropoff_coordinates' => $dropoffCoords,
        ];
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     *
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        return $distance;
    }

    /**
     * Calculate price based on distance and duration
     *
     * @param float $distance
     * @param int $duration
     * @return float
     */
    private function calculatePrice(float $distance, int $duration): float
    {
        $base_fare = 5;
        $per_km = 2;
        $per_min = 0.5;

        return round($base_fare + ($distance * $per_km) + ($duration * $per_min), 2);
    }

    /**
     * Convert address to coordinates using Mapbox
     *
     * @param string $address
     * @return array [latitude, longitude]
     * @throws Exception
     */
    private function geocodeAddress(string $address): array
    {
        try {
            return $this->mapboxService->geocode($address);
        } catch (Exception $e) {
            // Log error but don't fail the entire estimation
            // Fallback to mock coordinates for development
            Log::warning('Mapbox geocoding failed, using fallback', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            // Return mock coordinates as fallback (Casablanca)
            return [33.5731, -7.5898];
        }
    }
}
