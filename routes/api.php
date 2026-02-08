<?php

use App\Http\Controllers\DriverController;
use App\Http\Controllers\RideController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Public routes
Route::get('/rides/share/{token}', [RideController::class, 'share']);
Route::post('/rides/estimate', [RideController::class, 'estimate']); // Public for estimation

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Ride creation
    Route::post('/rides', [RideController::class, 'store']);
    
    // Ride management
    Route::get('/rides/history', [RideController::class, 'history']);
    Route::get('/rides/active', [RideController::class, 'active']);
    Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel']);
    
    // Driver actions
    Route::post('/rides/{ride}/accept', [RideController::class, 'acceptOffer']);
    Route::post('/rides/{ride}/arrived', [RideController::class, 'markArrived']);
    Route::post('/rides/{ride}/ongoing', [RideController::class, 'markOngoing']);
    Route::post('/rides/{ride}/completed', [RideController::class, 'markCompleted']);
    
    // Driver management
    Route::prefix('driver')->group(function () {
        Route::post('/location', [DriverController::class, 'updateLocation']);
        Route::post('/availability', [DriverController::class, 'updateAvailability']);
        Route::get('/rides/history', [DriverController::class, 'rideHistory']);
    });
});
