<?php

namespace App\Domain\Ride\Enums;

enum RideStatus: string
{
    case MATCHING = 'matching';
    case DRIVER_ASSIGNED = 'driver_assigned';
    case ARRIVED = 'arrived';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
