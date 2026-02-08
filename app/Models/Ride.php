<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    // ✅ Allow mass assignment for these fields
    protected $fillable = [
        'user_id',
        'driver_id',
        'pickup_location',
        'dropoff_location',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'status',
        'distance_km',
        'eta_minutes',
        'price',
        'assigned_at',
        'started_at',
        'completed_at',
        'share_token',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'eta_minutes' => 'integer',
        'price' => 'decimal:2',
        'pickup_latitude' => 'decimal:8',
        'pickup_longitude' => 'decimal:8',
        'dropoff_latitude' => 'decimal:8',
        'dropoff_longitude' => 'decimal:8',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user (passenger) for this ride
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the driver for this ride
     */
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get all events for this ride
     */
    public function events()
    {
        return $this->hasMany(RideEvent::class);
    }

    /**
     * Generate a unique share token for this ride
     */
    public function generateShareToken(): void
    {
        if (!$this->share_token) {
            $this->share_token = \Illuminate\Support\Str::random(32);
            $this->save();
        }
    }
}
