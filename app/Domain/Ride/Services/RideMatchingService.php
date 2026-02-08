<?php

namespace App\Domain\Ride\Services;

use App\Domain\Driver\Services\DriverAvailabilityService;
use App\Domain\Ride\Services\RideCreationService;
use App\Domain\Location\Services\MapboxService;
use App\Models\Ride;
use Illuminate\Support\Facades\Cache;
use App\Domain\Ride\Enums\RideStatus;
use Exception;
use Illuminate\Support\Facades\Log;

class RideMatchingService
{
    protected $driverService;
    protected $rideLogger;
    protected MapboxService $mapboxService;

    public function __construct(
        DriverAvailabilityService $driverService,
        RideCreationService $rideLogger,
        MapboxService $mapboxService
    ) {
        $this->driverService = $driverService;
        $this->rideLogger = $rideLogger;
        $this->mapboxService = $mapboxService;
    }

    /**
     * Start driver matching for the ride with TTL offers
     * This method should be called asynchronously via queue
     *
     * @param Ride $ride
     */
    public function match(Ride $ride): void
    {
        // Refresh ride to get latest status
        $ride->refresh();

        // If ride is no longer in MATCHING status, stop
        if ($ride->status !== RideStatus::MATCHING->value) {
            return;
        }

        $driversTried = [];
        $maxTries = 5; // maximum attempts for available drivers

        // Use stored coordinates or geocode address
        $pickupCoordinates = $ride->pickup_latitude && $ride->pickup_longitude
            ? [(float) $ride->pickup_latitude, (float) $ride->pickup_longitude]
            : $this->geocodeAddress($ride->pickup_location);

        for ($i = 0; $i < $maxTries; $i++) {
            $driver = $this->driverService->findNearestDriver($pickupCoordinates, $driversTried);

            if (!$driver) {
                break; // no more drivers available
            }

            $driversTried[] = $driver->id;

            // Create offer in cache with TTL 15 seconds
            $offerKey = "ride_offer_{$ride->id}_driver_{$driver->id}";
            Cache::put($offerKey, [
                'ride_id' => $ride->id,
                'driver_id' => $driver->id,
                'created_at' => now()->toIso8601String(),
            ], now()->addSeconds(15));

            // Log event for driver offer
            $this->rideLogger->logEvent($ride, 'match', 'Offer sent to driver ID ' . $driver->id);

            // Wait for driver to accept (check every second for 15 seconds)
            $accepted = false;
            for ($j = 0; $j < 15; $j++) {
                sleep(1);

                // Check if offer still exists (if not, driver accepted)
                if (!Cache::has($offerKey)) {
                    $accepted = true;
                    break;
                }

                // Refresh ride to check if it was assigned by another process
                $ride->refresh();
                if ($ride->status !== RideStatus::MATCHING->value) {
                    Cache::forget($offerKey);
                    return; // Ride was assigned or cancelled
                }
            }

            if (!$accepted) {
                // Driver did not accept → try next driver
                Cache::forget($offerKey);
                continue;
            }

            // Driver accepted → assign to ride
            $ride->refresh();
            if ($ride->status === RideStatus::MATCHING->value) {
                $ride->driver_id = $driver->id;
                $ride->status = RideStatus::DRIVER_ASSIGNED->value;
                $ride->assigned_at = now();
                $ride->save();

                // Make driver unavailable
                $driver->is_available = false;
                $driver->save();

                // Log driver assignment
                $this->rideLogger->logEvent($ride, 'driver_assigned', 'Driver ID ' . $driver->id . ' assigned');

                break; // Driver assigned successfully
            }
        }
    }

    /**
     * Convert an address string to [lat, lng] using Mapbox
     *
     * @param string $address
     * @return array [latitude, longitude]
     */
    private function geocodeAddress(string $address): array
    {
        try {
            return $this->mapboxService->geocode($address);
        } catch (Exception $e) {
            // Log error but don't fail the entire matching process
            Log::warning('Mapbox geocoding failed, using fallback', [
                'address' => $address,
                'error' => $e->getMessage(),
            ]);

            // Return mock coordinates as fallback (Casablanca)
            return [33.5731, -7.5898];
        }
    }
}
