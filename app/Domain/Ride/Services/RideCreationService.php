<?php

namespace App\Domain\Ride\Services;

use App\Models\Ride;
use App\Models\RideEvent;
use Carbon\Carbon;
use Exception;
use App\Domain\Ride\Enums\RideStatus;

class RideCreationService
{
    /**
     * Create a new ride with event logging
     * 
     * Rules:
     * - Location permission is required (coordinates must be provided)
     * - Pickup is auto-detected only (no manual pickup)
     * - Max 3 ride requests per 10 minutes
     * - Only 1 active ride at a time per user
     *
     * @param array $data ['pickup_location', 'dropoff_location', 'pickup_latitude', 'pickup_longitude', 'dropoff_latitude', 'dropoff_longitude']
     * @param int $userId
     * @return Ride
     * @throws Exception
     */
    public function create(array $data, int $userId): Ride
    {
        // 1️⃣ Check if user has an active ride
        $activeRide = Ride::where('user_id', $userId)
            ->whereIn('status', [
                RideStatus::MATCHING->value,
                RideStatus::DRIVER_ASSIGNED->value,
                RideStatus::ARRIVED->value,
                RideStatus::ONGOING->value
            ])
            ->first();

        if ($activeRide) {
            throw new Exception('You already have an active ride.');
        }

        // 2️⃣ Check rate limit: max 3 rides in 10 minutes
        $tenMinutesAgo = Carbon::now()->subMinutes(10);
        $recentRides = Ride::where('user_id', $userId)
            ->where('created_at', '>=', $tenMinutesAgo)
            ->count();

        if ($recentRides >= 3) {
            throw new Exception('You have reached the maximum ride requests in 10 minutes.');
        }

        // 3️⃣ Location permission is required - pickup coordinates must be provided (auto-detected)
        if (empty($data['pickup_latitude']) || empty($data['pickup_longitude'])) {
            throw new Exception('Location permission is required. Pickup location must be auto-detected.');
        }

        // 4️⃣ Create the ride with estimation data
        $ride = Ride::create([
            'user_id' => $userId,
            'pickup_location' => $data['pickup_location'],
            'dropoff_location' => $data['dropoff_location'],
            'pickup_latitude' => $data['pickup_latitude'],
            'pickup_longitude' => $data['pickup_longitude'],
            'dropoff_latitude' => $data['dropoff_latitude'] ?? null,
            'dropoff_longitude' => $data['dropoff_longitude'] ?? null,
            'distance_km' => $data['distance_km'] ?? null,
            'eta_minutes' => $data['eta_minutes'] ?? null,
            'price' => $data['price'] ?? null,
            'status' => RideStatus::MATCHING->value,
        ]);

        // 5️⃣ Generate share token
        $ride->generateShareToken();

        // 6️⃣ Log ride request event
        RideEvent::create([
            'ride_id' => $ride->id,
            'event' => 'request',
            'note' => 'Ride requested by user ID ' . $userId,
        ]);

        return $ride;
    }

    /**
     * Log a custom ride event
     *
     * @param Ride $ride
     * @param string $event
     * @param string|null $note
     */
    public function logEvent(Ride $ride, string $event, ?string $note = null): void
    {
        RideEvent::create([
            'ride_id' => $ride->id,
            'event' => $event,
            'note' => $note,
        ]);
    }
}
