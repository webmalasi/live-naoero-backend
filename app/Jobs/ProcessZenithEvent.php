<?php

namespace App\Jobs;

use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Turns a stored Zenith event into customer notices (see FlightEvents). */
class ProcessZenithEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds between attempts */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $eventId) {}

    public function handle(FlightEvents $events, ServerLog $log): void
    {
        try {
            $events->process($this->eventId);
        } finally {
            $log->flush();
        }
    }

    public function failed(Throwable $e): void
    {
        app(FlightEvents::class)->failed($this->eventId, $e);
        app(ServerLog::class)->flush();
    }
}
