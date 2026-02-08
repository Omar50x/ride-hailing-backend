<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'ride_id',
        'event',
        'note',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
