<?php

namespace App\Domain\Ride\Services;

use App\Models\Ride;
use App\Domain\Ride\Enums\RideStatus;
use Exception;

class RideCancellationService
{
    protected $rideLogger;

    public function __construct(RideCreationService $rideLogger)
    {
        $this->rideLogger = $rideLogger;
    }

    /**
     * Cancel a ride with optional reason
     *
     * @param Ride $ride
     * @param string|null $reason
     * @return Ride
     * @throws Exception
     */
    public function cancel(Ride $ride, ?string $reason = null): Ride
    {
        if (in_array($ride->status, [RideStatus::MATCHING->value])) {
            // free cancellation before driver assignment
            $ride->status = RideStatus::CANCELLED->value;
            $ride->save();
        } else {
            if (!$reason) {
                throw new Exception('Cancellation reason is required after driver assignment.');
            }
            $ride->status = RideStatus::CANCELLED->value;
            $ride->save();
        }

        // log cancellation event
        $this->rideLogger->logEvent($ride, 'cancel', $reason ?? 'Cancelled by user before driver assignment');

        return $ride;
    }
}
