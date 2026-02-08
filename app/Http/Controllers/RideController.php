<?php

namespace App\Http\Controllers;

use App\Domain\Ride\Services\RideCancellationService;
use App\Domain\Ride\Services\RideCreationService;
use App\Domain\Ride\Services\RideEstimationService;
use App\Domain\Ride\Services\RideMatchingService;
use App\Domain\Ride\Services\RideStateService;
use App\Http\Requests\StoreRideRequest;
use App\Jobs\ProcessRideMatching;
use App\Models\Ride;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class RideController extends Controller
{
    protected $rideCreation;
    protected $rideEstimation;

    public function __construct(
        RideCreationService $rideCreation,
        RideEstimationService $rideEstimation
    ) {
        $this->rideCreation = $rideCreation;
        $this->rideEstimation = $rideEstimation;
    }

    /**
     * Estimate ride (distance, ETA, price)
     */
    public function estimate(Request $request): JsonResponse
    {
        $request->validate([
            'pickup_location' => ['required', 'string'],
            'dropoff_location' => ['required', 'string'],
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'dropoff_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'dropoff_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $pickup = $request->pickup_latitude && $request->pickup_longitude
            ? [(float) $request->pickup_latitude, (float) $request->pickup_longitude]
            : $request->pickup_location;

        $dropoff = $request->dropoff_latitude && $request->dropoff_longitude
            ? [(float) $request->dropoff_latitude, (float) $request->dropoff_longitude]
            : $request->dropoff_location;

        $data = $this->rideEstimation->estimate($pickup, $dropoff);

        return response()->json($data);
    }

    /**
     * Create a new ride
     */
    public function store(StoreRideRequest $request): JsonResponse
    {
        // Get estimation data
        $pickup = [(float) $request->pickup_latitude, (float) $request->pickup_longitude];
        $dropoff = $request->dropoff_latitude && $request->dropoff_longitude
            ? [(float) $request->dropoff_latitude, (float) $request->dropoff_longitude]
            : $request->dropoff_location;

        $estimation = $this->rideEstimation->estimate($pickup, $dropoff);

        // Merge estimation data with request data
        $data = array_merge($request->validated(), $estimation);

        // Add coordinates from estimation if not provided
        if (isset($estimation['pickup_coordinates'])) {
            $data['pickup_latitude'] = $estimation['pickup_coordinates'][0];
            $data['pickup_longitude'] = $estimation['pickup_coordinates'][1];
        }
        if (isset($estimation['dropoff_coordinates'])) {
            $data['dropoff_latitude'] = $estimation['dropoff_coordinates'][0];
            $data['dropoff_longitude'] = $estimation['dropoff_coordinates'][1];
        }

        $ride = $this->rideCreation->create($data, Auth::id());

        // Start matching drivers asynchronously
        ProcessRideMatching::dispatch($ride);

        return response()->json($ride->load(['user', 'driver']), 201);
    }

    /**
     * Get trip history for authenticated user
     */
    public function history(Request $request): JsonResponse
    {
        $rides = Ride::where('user_id', Auth::id())
            ->with(['driver', 'events'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($rides);
    }

    /**
     * Get active ride for authenticated user
     */
    public function active(Request $request): JsonResponse
    {
        $ride = Ride::where('user_id', Auth::id())
            ->whereIn('status', ['matching', 'driver_assigned', 'arrived', 'ongoing'])
            ->with(['driver', 'events'])
            ->first();

        if (!$ride) {
            return response()->json(['message' => 'No active ride found'], 404);
        }

        return response()->json($ride);
    }

    /**
     * Cancel a ride
     */
    public function cancel(Request $request, Ride $ride, RideCancellationService $cancellationService): JsonResponse
    {
        // Verify ride belongs to user
        if ($ride->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $ride = $cancellationService->cancel($ride, $request->reason);

            // If driver was assigned, make them available again
            if ($ride->driver_id) {
                $ride->driver->is_available = true;
                $ride->driver->save();
            }

            return response()->json($ride->load(['user', 'driver', 'events']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Update ride status to ARRIVED
     */
    public function markArrived(Request $request, Ride $ride, RideStateService $stateService): JsonResponse
    {
        // Verify user is the driver
        if ($ride->driver_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $ride = $stateService->markArrived($ride);
            return response()->json($ride->load(['user', 'driver', 'events']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Update ride status to ONGOING
     */
    public function markOngoing(Request $request, Ride $ride, RideStateService $stateService): JsonResponse
    {
        // Verify user is the driver
        if ($ride->driver_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $ride = $stateService->markOngoing($ride);
            return response()->json($ride->load(['user', 'driver', 'events']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Update ride status to COMPLETED
     */
    public function markCompleted(Request $request, Ride $ride, RideStateService $stateService): JsonResponse
    {
        // Verify user is the driver
        if ($ride->driver_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $ride = $stateService->markCompleted($ride);
            return response()->json($ride->load(['user', 'driver', 'events']));
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    /**
     * Driver accepts a ride offer
     */
    public function acceptOffer(Request $request, Ride $ride): JsonResponse
    {
        // Verify user is a driver
        if (!Auth::user()->is_driver) {
            return response()->json(['message' => 'Only drivers can accept ride offers'], 403);
        }

        // Check if there's an active offer for this driver
        $offerKey = "ride_offer_{$ride->id}_driver_" . Auth::id();

        if (!Cache::has($offerKey)) {
            return response()->json(['message' => 'No active offer found or offer expired'], 404);
        }

        // Verify ride is still in MATCHING status
        $ride->refresh();
        if ($ride->status !== 'matching') {
            Cache::forget($offerKey);
            return response()->json(['message' => 'Ride is no longer available'], 400);
        }

        // Accept the offer
        Cache::forget($offerKey);

        $ride->driver_id = Auth::id();
        $ride->status = 'driver_assigned';
        $ride->assigned_at = now();
        $ride->save();

        // Make driver unavailable
        Auth::user()->is_available = false;
        Auth::user()->save();

        // Log event
        $this->rideCreation->logEvent($ride, 'driver_assigned', 'Driver ID ' . Auth::id() . ' accepted the offer');

        return response()->json($ride->load(['user', 'driver', 'events']));
    }

    /**
     * Public endpoint to share trip status
     */
    public function share(Request $request, string $token): JsonResponse
    {
        $ride = Ride::where('share_token', $token)
            ->with(['user', 'driver', 'events'])
            ->first();

        if (!$ride) {
            return response()->json(['message' => 'Ride not found'], 404);
        }

        // Return limited public information
        return response()->json([
            'id' => $ride->id,
            'status' => $ride->status,
            'pickup_location' => $ride->pickup_location,
            'dropoff_location' => $ride->dropoff_location,
            'distance_km' => $ride->distance_km,
            'eta_minutes' => $ride->eta_minutes,
            'price' => $ride->price,
            'assigned_at' => $ride->assigned_at,
            'started_at' => $ride->started_at,
            'completed_at' => $ride->completed_at,
            'driver' => $ride->driver ? [
                'name' => $ride->driver->name,
            ] : null,
            'events' => $ride->events->map(function ($event) {
                return [
                    'event' => $event->event,
                    'created_at' => $event->created_at,
                ];
            }),
        ]);
    }
}
