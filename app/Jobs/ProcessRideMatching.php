<?php

namespace App\Jobs;

use App\Domain\Ride\Services\RideMatchingService;
use App\Models\Ride;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRideMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Ride $ride
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(RideMatchingService $matchingService): void
    {
        $matchingService->match($this->ride);
    }
}
