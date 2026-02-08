<?php

namespace App\Domain\Ride\Services;

use App\Models\Ride;
use App\Domain\Ride\Enums\RideStatus;
use Exception;

class RideStateService
{
    protected $rideLogger;

    public function __construct(RideCreationService $rideLogger)
    {
        $this->rideLogger = $rideLogger;
    }

    /**
     * Update ride status to ARRIVED
     *
     * @param Ride $ride
     * @return Ride
     * @throws Exception
     */
    public function markArrived(Ride $ride): Ride
    {
        if ($ride->status !== RideStatus::DRIVER_ASSIGNED->value) {
            throw new Exception('Ride must be in DRIVER_ASSIGNED status to mark as arrived.');
        }

        $ride->status = RideStatus::ARRIVED->value;
        $ride->save();

        $this->rideLogger->logEvent($ride, 'state_change', 'Ride status changed to ARRIVED');

        return $ride;
    }

    /**
     * Update ride status to ONGOING
     *
     * @param Ride $ride
     * @return Ride
     * @throws Exception
     */
    public function markOngoing(Ride $ride): Ride
    {
        if ($ride->status !== RideStatus::ARRIVED->value) {
            throw new Exception('Ride must be in ARRIVED status to mark as ongoing.');
        }

        $ride->status = RideStatus::ONGOING->value;
        $ride->started_at = now();
        $ride->save();

        $this->rideLogger->logEvent($ride, 'state_change', 'Ride status changed to ONGOING');

        return $ride;
    }

    /**
     * Update ride status to COMPLETED
     *
     * @param Ride $ride
     * @return Ride
     * @throws Exception
     */
    public function markCompleted(Ride $ride): Ride
    {
        if ($ride->status !== RideStatus::ONGOING->value) {
            throw new Exception('Ride must be in ONGOING status to mark as completed.');
        }

        $ride->status = RideStatus::COMPLETED->value;
        $ride->completed_at = now();
        $ride->save();

        // Make driver available again
        if ($ride->driver) {
            $ride->driver->is_available = true;
            $ride->driver->save();
        }

        $this->rideLogger->logEvent($ride, 'state_change', 'Ride status changed to COMPLETED');

        return $ride;
    }
}

