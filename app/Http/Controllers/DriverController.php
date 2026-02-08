<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DriverController extends Controller
{
    /**
     * Update driver location
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user = Auth::user();

        if (!$user->is_driver) {
            return response()->json(['message' => 'Only drivers can update location'], 403);
        }

        $user->latitude = $request->latitude;
        $user->longitude = $request->longitude;
        $user->save();

        return response()->json([
            'message' => 'Location updated successfully',
            'latitude' => $user->latitude,
            'longitude' => $user->longitude,
        ]);
    }

    /**
     * Update driver availability status
     */
    public function updateAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $user = Auth::user();

        if (!$user->is_driver) {
            return response()->json(['message' => 'Only drivers can update availability'], 403);
        }

        $user->is_available = $request->is_available;
        $user->save();

        return response()->json([
            'message' => 'Availability updated successfully',
            'is_available' => $user->is_available,
        ]);
    }

    /**
     * Get driver's ride history
     */
    public function rideHistory(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->is_driver) {
            return response()->json(['message' => 'Only drivers can view driver ride history'], 403);
        }

        $rides = $user->driverRides()
            ->with(['user', 'events'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($rides);
    }
}
