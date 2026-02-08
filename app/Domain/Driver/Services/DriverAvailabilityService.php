<?php

namespace App\Domain\Driver\Services;

use App\Models\User;

class DriverAvailabilityService
{
    /**
     * Find the nearest available driver to pickup location
     *
     * @param array $pickup [latitude, longitude]
     * @param array $excludeDriverIds Driver IDs to exclude (already tried)
     * @return User|null
     */
    public function findNearestDriver(array $pickup, array $excludeDriverIds = []): ?User
    {
        $pickupLat = $pickup[0];
        $pickupLng = $pickup[1];

        $drivers = User::where('is_driver', true)
            ->where('is_available', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotIn('id', $excludeDriverIds)
            ->get();

        if ($drivers->isEmpty()) {
            return null;
        }

        // Calculate distance for each driver and find nearest
        $nearestDriver = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($drivers as $driver) {
            $distance = $this->calculateDistance(
                $pickupLat,
                $pickupLng,
                (float) $driver->latitude,
                (float) $driver->longitude
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestDriver = $driver;
            }
        }

        return $nearestDriver;
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
}
