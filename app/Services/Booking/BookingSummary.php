<?php

namespace App\Services\Booking;

use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Throwable;

/** What the app shows for a Zenith booking: status, amounts, flights, tickets. */
final class BookingSummary
{
    private const CANCELLED_SEGMENT = ['HX', 'XX', 'UN', 'UC', 'NO'];

    /** Zenith sends UTC times without a zone marker; returns ISO UTC or null. */
    public static function utc(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        // Zenith's "no date" (e.g. a service booking's time limit).
        if (str_starts_with($value, '0001-01-01')) {
            return null;
        }
        $withZone = preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $value) ? $value : "{$value}Z";
        try {
            return Js::iso(CarbonImmutable::parse($withZone));
        } catch (Throwable) {
            return null;
        }
    }

    /** pending_payment / ticketed / expired / cancelled */
    public static function status(array $b, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();
        $segments = Js::list($b['Segments'] ?? null);
        if ($segments && array_filter($segments, fn ($s) => ! in_array($s['BookingClass']['StatusCode'] ?? '', self::CANCELLED_SEGMENT, true)) === []) {
            return 'cancelled';
        }
        $tickets = Js::list($b['TicketInfo']['ETTickets'] ?? null);
        if ($tickets && empty($b['MiscInfo']['ContainsIssuableTickets'])) {
            return 'ticketed';
        }
        // Services bought on their own (BuyServices): paid once their EMDs are issued.
        if (self::isServiceBooking($b)) {
            return Js::list($b['TicketInfo']['EMDTickets'] ?? null) && empty($b['MiscInfo']['ContainsIssuableTickets']) ? 'ticketed' : 'pending_payment';
        }
        // Ticketed, with extras added since that still need paying.
        if ($tickets) {
            return 'pending_payment';
        }
        $limit = self::utc($b['PnrInformation']['TimeLimit'] ?? null);
        if ($limit && CarbonImmutable::parse($limit)->lessThan($now)) {
            return 'expired';
        }

        return 'pending_payment';
    }

    /**
     * The bookings table's readable columns: customer, route ("BNE → INU →
     * BNE"), first departure (local time) and passengers.
     *
     * @return array<string, mixed>
     */
    public static function readableColumns(array $summary, ?object $customer): array
    {
        $flights = $summary['flights'] ?? [];
        $route = [];
        foreach ($flights as $f) {
            if (end($route) !== $f['origin']) {
                $route[] = $f['origin'];
            }
            $route[] = $f['destination'];
        }
        $departure = $flights[0]['departure'] ?? null;
        $names = array_values(array_filter(array_column($summary['passengers'] ?? [], 'name')));

        return [
            'customer_name' => $customer ? (trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: null) : null,
            'customer_email' => $customer->email ?? $customer->zenith_login ?? null,
            'route' => $route ? implode(' → ', $route) : null,
            'departure_at' => $departure ? str_replace('T', ' ', substr($departure, 0, 19)) : null,
            'passenger_count' => count($summary['passengers'] ?? []),
            'passenger_names' => $names ? implode(', ', $names) : null,
        ];
    }

    /** Cabin classes as customers read them (Zenith CabinClass value codes). */
    private const CABINS = ['Y' => 'Economy', 'W' => 'Premium Economy', 'C' => 'Business', 'J' => 'Business', 'F' => 'First'];

    /**
     * Zenith coupon statuses (CouponStatus value codes) grouped by what they
     * mean for the traveller.
     */
    private const COUPON_STATES = [
        'F' => 'flown', 'L' => 'flown', 'NRB' => 'flown',
        'O' => 'open', 'C' => 'open', 'A' => 'open', 'S' => 'open', 'U' => 'open', 'P' => 'open', 'T' => 'open', 'I' => 'open', 'N' => 'open', '_701' => 'open',
        'R' => 'refunded', 'Y' => 'refunded',
        'E' => 'exchanged', 'X' => 'exchanged', 'G' => 'exchanged',
        'V' => 'void', 'Z' => 'void',
    ];

    /** The cabin of the booking's first flight ("Economy", "Business"). */
    public static function cabin(array $segments): ?string
    {
        $code = strtoupper((string) ($segments[0]['BookingClass']['CabinClassCode'] ?? ''));

        return $code === '' ? null : (self::CABINS[$code] ?? $code);
    }

    /**
     * How many ticket coupons were flown, are still open, or were refunded,
     * exchanged or voided.
     *
     * @return array{flown: int, open: int, refunded: int, exchanged: int, void: int, total: int}
     */
    public static function coupons(array $tickets): array
    {
        $out = ['flown' => 0, 'open' => 0, 'refunded' => 0, 'exchanged' => 0, 'void' => 0, 'total' => 0];
        foreach ($tickets as $t) {
            foreach (Js::list($t['Coupons'] ?? null) as $c) {
                $state = self::COUPON_STATES[strtoupper((string) ($c['StatusCode'] ?? ''))] ?? 'open';
                $out[$state]++;
                $out['total']++;
            }
        }

        return $out;
    }

    /**
     * Zenith gives document links (e-tickets, boarding passes) relative to
     * its own host; the app needs a full address.
     */
    public static function zenithLink(?string $link): ?string
    {
        if (! is_string($link) || $link === '') {
            return null;
        }
        if (! str_starts_with($link, '/')) {
            return $link;
        }
        $endpoint = parse_url((string) config('naoero.zenith.endpoint'));
        if (empty($endpoint['host'])) {
            return $link;
        }

        return ($endpoint['scheme'] ?? 'https').'://'.$endpoint['host'].(isset($endpoint['port']) ? ':'.$endpoint['port'] : '').$link;
    }

    /**
     * Segment refs of the itinerary a pending flight change would bring in
     * (Itinerary.RefExchangingItin is set on it); empty when none is pending.
     *
     * @return list<string>
     */
    public static function pendingChangeSegmentRefs(array $b): array
    {
        if (empty($b['MiscInfo']['IsPendingExchange'])) {
            return [];
        }
        $refs = [];
        foreach (Js::list($b['FareInfo']['Itineraries'] ?? null) as $it) {
            if (empty($it['RefExchangingItin'])) {
                continue;
            }
            foreach (Js::list($it['AirOriginDestinations'] ?? null) as $od) {
                foreach (Js::list($od['AirCoupons'] ?? null) as $c) {
                    $refs[] = (string) ($c['RefSegment'] ?? '');
                }
            }
        }

        return array_values(array_filter($refs));
    }

    /** A segment as the app shows it. */
    public static function flight(array $s): array
    {
        return [
            'flightNumber' => ($s['AirlineDesignator'] ?? '').($s['FlightInfo']['FlightNumber'] ?? ''),
            'origin' => $s['OriginCode'],
            'destination' => $s['DestinationCode'],
            'departure' => $s['FlightInfo']['DepartureDate'] ?? null,
            'arrival' => $s['FlightInfo']['ArrivalDate'] ?? null,
            'status' => $s['BookingClass']['StatusCode'] ?? null,
        ];
    }

    /**
     * The flights the booking holds now: cancelled segments (a flight change
     * cancelled, or replaced) are left out unless nothing else remains, and
     * the new flights of a pending change are shown apart.
     *
     * @return list<array>
     */
    public static function currentSegments(array $b): array
    {
        $segments = Js::list($b['Segments'] ?? null);
        $pending = self::pendingChangeSegmentRefs($b);
        $kept = array_values(array_filter($segments, fn ($s) => ! in_array($s['BookingClass']['StatusCode'] ?? '', self::CANCELLED_SEGMENT, true)
            && ! in_array((string) ($s['Ref'] ?? ''), $pending, true)));

        return $kept ?: $segments;
    }

    /**
     * Flights cancelled, tickets kept: every flight was cancelled (an
     * itinerary cancel releases the seats) but tickets are still open, so
     * their value can go towards another flight (exchange) or be refunded.
     * Segments and tickets have separate lifecycles in Zenith. Null when
     * that is not the booking's state.
     *
     * @return ?array{amount: float, currency: string, tickets: list<string>, canChange: bool, canRefund: bool}
     */
    public static function keptTickets(array $b): ?array
    {
        $segments = Js::list($b['Segments'] ?? null);
        if (! $segments || array_filter($segments, fn ($s) => ! in_array($s['BookingClass']['StatusCode'] ?? '', self::CANCELLED_SEGMENT, true)) !== []) {
            return null;
        }
        $value = self::openTicketValue($b);

        return $value === null ? null : [
            ...$value,
            'canChange' => Js::list($b['MiscInfo']['ExchangeableOriginDestinations'] ?? null) !== [],
            'canRefund' => ! empty($b['MiscInfo']['CancelInfo']['CanRefund']),
        ];
    }

    /**
     * The tickets with a coupon still open (status O) and what they are
     * worth: each one's fare, taxes included.
     *
     * @return ?array{amount: float, currency: string, tickets: list<string>}
     */
    public static function openTicketValue(array $b): ?array
    {
        $fares = [];
        foreach (Js::list($b['FareInfo']['ETTicketFares'] ?? null) as $f) {
            $fares[(string) ($f['Ref'] ?? '')] = (float) ($f['SaleCurrencyAmount']['TotalAmount'] ?? 0);
        }
        $amount = 0.0;
        $numbers = [];
        foreach (Js::list($b['TicketInfo']['ETTickets'] ?? null) as $t) {
            $open = array_filter(Js::list($t['Coupons'] ?? null), fn ($c) => ($c['StatusCode'] ?? null) === 'O');
            if ($open && ! empty($t['DocumentNumber'])) {
                $numbers[] = (string) $t['DocumentNumber'];
                $amount += $fares[(string) ($t['RefETTicketFare'] ?? '')] ?? 0;
            }
        }

        return $numbers ? ['amount' => Js::round2($amount), 'currency' => self::currency($b), 'tickets' => $numbers] : null;
    }

    /** A booking of services only, bought with BuyServices (no flights). */
    public static function isServiceBooking(array $b): bool
    {
        return ! empty($b['PnrInformation']['IsServiceBooking']);
    }

    /** The booking's sale currency; a service booking may give it only on its fares. */
    public static function currency(array $b): string
    {
        return (string) ($b['FareInfo']['SaleCurrencyCode']
            ?? Js::list($b['FareInfo']['EMDTicketFares'] ?? null)[0]['AmountDetails']['FareCurrencyCode']
            ?? '');
    }

    /**
     * The services of a service booking: each one bought, with its EMD
     * number once paid.
     *
     * @return list<array{label: string, serviceCode: ?string, amount: float, emdNumber: ?string}>
     */
    public static function services(array $b): array
    {
        // One EMD per service bought, issued in the same order.
        $issued = array_values(array_map(fn ($t) => (string) ($t['DocumentNumber'] ?? ''), Js::list($b['TicketInfo']['EMDTickets'] ?? null)));
        $out = [];
        foreach (Js::list($b['FareInfo']['EMDTicketFares'] ?? null) as $i => $f) {
            $out[] = [
                'label' => trim((string) ($f['Label'] ?? '')) ?: 'Service',
                'serviceCode' => $f['ServiceCode'] ?? null,
                'amount' => Js::round2($f['SaleCurrencyAmount']['TotalAmount'] ?? 0),
                'emdNumber' => ($issued[$i] ?? '') !== '' ? $issued[$i] : null,
            ];
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function summarize(array $b, ?CarbonImmutable $now = null): array
    {
        $passengers = array_map(fn ($p) => [
            'ref' => $p['Ref'],
            'type' => $p['PassengerTypeCode'],
            'name' => implode(' ', array_filter([$p['NameElement']['Firstname'] ?? null, $p['NameElement']['Surname'] ?? null])),
        ], Js::list($b['Passengers'] ?? null));
        $names = array_column($passengers, 'name', 'ref');
        $status = self::status($b, $now);
        $pending = self::pendingChangeSegmentRefs($b);

        $tickets = [];
        foreach (Js::list($b['TicketInfo']['ETTickets'] ?? null) as $t) {
            if (! empty($t['DocumentNumber'])) {
                $tickets[] = [
                    'passengerRef' => $t['RefPassenger'] ?? null,
                    'passengerName' => $names[$t['RefPassenger'] ?? ''] ?? null,
                    'number' => $t['DocumentNumber'],
                ];
            }
        }

        return [
            'pnr' => $b['PnrInformation']['PnrCode'] ?? '',
            'status' => $status,
            // flights, or services bought on their own (no flights)
            'kind' => self::isServiceBooking($b) ? 'services' : 'flights',
            // status "cancelled" with tickets still open: their value can be used or refunded
            'keptTickets' => self::keptTickets($b),
            'services' => self::isServiceBooking($b) ? self::services($b) : [],
            'currency' => self::currency($b),
            'totalAmount' => Js::round2($b['FareInfo']['SaleCurrencyAmountTotal']['TotalAmount'] ?? 0),
            'amountDue' => $status === 'ticketed' ? 0 : Js::round2($b['FareInfo']['SaleCurrencyTravelerAmountToPay']['TotalAmount'] ?? 0),
            'timeLimit' => self::utc($b['PnrInformation']['TimeLimit'] ?? null),
            'flights' => array_map(self::flight(...), self::currentSegments($b)),
            // A flight change made but not paid yet: its new flights and what it costs.
            'pendingChange' => $pending ? [
                'flights' => array_map(self::flight(...), array_values(array_filter(Js::list($b['Segments'] ?? null), fn ($s) => in_array((string) ($s['Ref'] ?? ''), $pending, true)))),
                'amountDue' => Js::round2($b['FareInfo']['SaleCurrencyTravelerAmountToPay']['TotalAmount'] ?? 0),
                'timeLimit' => self::utc($b['PnrInformation']['TimeLimit'] ?? null),
            ] : null,
            'passengers' => $passengers,
            'tickets' => $tickets,
            'ticketPrintUrl' => self::zenithLink($b['MiscInfo']['TicketPrintHttpLink'] ?? null),
            // Special services, seats and paid extras on the booking.
            'extras' => BookingExtras::current($b),
            // For travel history: the cabin and what happened to each ticket coupon.
            'cabin' => self::cabin(self::currentSegments($b)),
            'coupons' => self::coupons(Js::list($b['TicketInfo']['ETTickets'] ?? null)),
        ];
    }
}
