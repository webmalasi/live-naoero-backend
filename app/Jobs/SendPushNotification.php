<?php

namespace App\Jobs;

use App\Services\Logging\ServerLog;
use App\Services\Push\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Pushes a new notice to the customer's phones (see PushNotifier). Tried
 * again when Apple or Google were briefly unavailable; phones already
 * pushed are not pushed twice.
 */
class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> seconds between attempts */
    public array $backoff = [30, 120];

    public function __construct(public readonly string $notificationId) {}

    public function handle(PushNotifier $notifier, ServerLog $log): void
    {
        try {
            $totals = $notifier->forNotification($this->notificationId);
        } finally {
            $log->flush();
        }
        if ($totals['retryable'] > 0 && $this->attempts() < $this->tries) {
            throw new RuntimeException("{$totals['retryable']} push(es) to try again");
        }
    }
}
