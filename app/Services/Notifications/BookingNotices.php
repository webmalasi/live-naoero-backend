<?php

namespace App\Services\Notifications;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Notices about the customer's own bookings: made, paid and ticketed,
 * changed, extended, checked in, expired. (Cancellations send their own,
 * and flight changes from the airline come through FlightEvents.)
 */
class BookingNotices
{
    public function __construct(private readonly CustomerNotices $notices) {}

    /** "NRU → BNE on Tue 3 Nov, 08:30" */
    public static function trip(array $summary): string
    {
        $flights = $summary['flights'] ?? [];
        if (! $flights) {
            return "booking {$summary['pnr']}";
        }
        $route = [];
        foreach ($flights as $f) {
            if (end($route) !== $f['origin']) {
                $route[] = $f['origin'];
            }
            $route[] = $f['destination'];
        }
        $when = '';
        try {
            $when = $flights[0]['departure'] ? ' on '.CarbonImmutable::parse($flights[0]['departure'])->format('D j M, H:i') : '';
        } catch (Throwable) {
        }

        return implode(' → ', array_values(array_unique($route))).$when;
    }

    private function send(object $customer, array $summary, string $type, string $title, string $body, ?string $dedupe = null, array $data = []): void
    {
        $pnr = (string) ($summary['pnr'] ?? '');
        if ($pnr === '') {
            return;
        }
        $this->notices->send($customer->id, $type, $title, $body, $data, pnr: $pnr, dedupeKey: $dedupe ? "{$type}:{$pnr}:{$dedupe}" : null);
    }

    public function created(object $customer, array $summary): void
    {
        $due = $summary['amountDue'] ?? 0;
        $by = '';
        if (! empty($summary['timeLimit'])) {
            try {
                $by = ' by '.CarbonImmutable::parse($summary['timeLimit'])->timezone('Pacific/Nauru')->format('D j M, H:i').' (Nauru time)';
            } catch (Throwable) {
            }
        }
        $this->send($customer, $summary, 'booking_created', "Booking {$summary['pnr']} is reserved",
            'Your seats for '.self::trip($summary).' are held.'.($due > 0 ? ' Pay '.number_format((float) $due, 2)." {$summary['currency']}{$by} to get your tickets." : ''),
            'created');
    }

    /**
     * Compares the booking before and after it was saved and tells the
     * customer what changed: tickets issued, new tickets after a change,
     * or a hold that expired.
     */
    public function transitions(object $customer, ?array $before, array $after): void
    {
        if ($before === null) {
            return;
        }
        $was = $before['status'] ?? null;
        $now = $after['status'] ?? null;
        $oldTickets = array_column($before['tickets'] ?? [], 'number');
        $newTickets = array_column($after['tickets'] ?? [], 'number');
        sort($oldTickets);
        sort($newTickets);
        if ($now === 'ticketed' && $was !== 'ticketed') {
            $this->send($customer, $after, 'booking_confirmed', "Booking {$after['pnr']} is confirmed",
                'Payment received and your tickets are issued for '.self::trip($after).'. Have a great trip!', 'ticketed:'.implode(',', $newTickets));
        } elseif ($now === 'ticketed' && $was === 'ticketed' && $newTickets && $newTickets !== $oldTickets) {
            $this->send($customer, $after, 'flight_change_confirmed', "Your flight change is confirmed ({$after['pnr']})",
                'Your new tickets are issued for '.self::trip($after).'.', 'tickets:'.implode(',', $newTickets));
        } elseif ($now === 'expired' && $was !== 'expired') {
            $this->send($customer, $after, 'booking_expired', "Booking {$after['pnr']} has expired",
                'It was not paid in time, so the seats were released. You can book again from the app.', 'expired');
        }
    }

    public function paymentFailed(object $customer, array $summary, string $paymentId): void
    {
        $this->send($customer, $summary, 'booking_payment_failed', "Payment not completed ({$summary['pnr']})",
            'Your payment for '.self::trip($summary).' did not go through. Your seats stay held until the time limit: try again from My Trips.', $paymentId);
    }

    public function changeHeld(object $customer, array $summary): void
    {
        $due = $summary['pendingChange']['amountDue'] ?? $summary['amountDue'] ?? 0;
        $this->send($customer, $summary, 'flight_change_held', "New flights held ({$summary['pnr']})",
            'Your new flights are held.'.($due > 0 ? ' Pay '.number_format((float) $due, 2)." {$summary['currency']} to confirm the change." : ' Confirm the change to get your new tickets.'));
    }

    public function changeCancelled(object $customer, array $summary): void
    {
        $this->send($customer, $summary, 'flight_change_cancelled', "Flight change cancelled ({$summary['pnr']})",
            'The change was cancelled. Your original flights are unchanged: '.self::trip($summary).'.');
    }

    public function tripAdded(object $customer, array $summary): void
    {
        $this->send($customer, $summary, 'trip_added', "Flights added to {$summary['pnr']}",
            'Your booking now covers '.self::trip($summary).'.'.(($summary['amountDue'] ?? 0) > 0 ? ' Pay '.number_format((float) $summary['amountDue'], 2)." {$summary['currency']} to confirm." : ''));
    }

    public function extrasAdded(object $customer, array $summary): void
    {
        $this->send($customer, $summary, 'extras_added', "Extras updated ({$summary['pnr']})",
            'Your seats and extras for '.self::trip($summary).' were updated.');
    }

    public function passengersUpdated(object $customer, array $summary): void
    {
        $this->send($customer, $summary, 'passengers_updated', "Passenger details updated ({$summary['pnr']})",
            'The passenger details for '.self::trip($summary).' were saved with the airline.');
    }

    public function checkedIn(object $customer, array $summary): void
    {
        $this->send($customer, $summary, 'checked_in', "You're checked in ({$summary['pnr']})",
            'Your boarding passes for '.self::trip($summary).' are ready in the app.');
    }

    public function checkInUndone(object $customer, array $summary): void
    {
        $this->send($customer, $summary, 'checkin_undone', "Check-in undone ({$summary['pnr']})",
            'Check-in was undone for '.self::trip($summary).'. These flights are now cancelled for the passengers: contact Naoero Airlines if you still want to travel.');
    }

    public function refundRequested(object $customer, string $pnr, string $reference): void
    {
        $this->notices->send($customer->id, 'refund_request_received', "Refund request received ({$pnr})",
            "We have your refund request {$reference}. Our team will reply by email.", ['reference' => $reference], pnr: $pnr, dedupeKey: "refund_request:{$reference}");
    }
}
