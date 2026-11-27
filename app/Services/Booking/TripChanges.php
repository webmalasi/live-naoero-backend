<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Flights\FlightConfigService;
use App\Services\Flights\FlightSearch;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\BookingNotices;
use App\Services\Reference\ReferenceService;
use App\Services\Support\Js;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What a customer can do with a booking after it is made, following the
 * airline's functional mapping:
 *
 *   flight status   FlightStatus (the airline's departure boards, same day)
 *   passengers      PrepareBookingModification -> ModifyBooking (names stay
 *                   as booked; contact details and travel documents change)
 *   change flights  SearchFlightsForExchange -> PrepareExchange -> Exchange,
 *                   then CreateTicket (pay) or CancelPendingExchange
 *   add a trip      SearchFlightsForAdditionalItinerary ->
 *                   PrepareAdditionalItinerary -> AddItinerary, then pay
 *   split           SplitBooking: passengers move to a booking of their own
 *   check-in        PrepareCheckin -> Checkin; boarding passes from
 *                   MiscInfo.BoardingPassList
 *   undo check-in   Uncheck, only when the airline allows it
 *                   (naoero.allow_uncheck): it cancels the flights as a no-show
 *
 * Payment for a change or an added trip is the booking's own payment
 * (BookingService::startPayment): the amount due is what Zenith asks for.
 */
class TripChanges
{
    /** Passenger data customers may update on a booking. */
    private const EDITABLE_SSRS = ['CTCE', 'CTCM', 'CTCH', 'EXT-ADOB', 'DOCS'];

    /** Travel document fields offered when Zenith lists none (modification answers omit them). */
    private const DOCUMENT_FIELDS = ['DocumentTypeCode', 'DocumentNumber', 'NationalityCountryCode', 'IssueCountryCode', 'DocumentExpiryDate', 'DateOfBirth', 'Gender'];

    /** AirportFlightStatus value codes as customers read them. */
    private const FLIGHT_STATUSES = [
        'Scheduled' => 'On time',
        'Delayed' => 'Delayed',
        'Boarding' => 'Boarding',
        'BoardingClosed' => 'Boarding closed',
        'Departed' => 'Departed',
        'Landed' => 'Landed',
        'Cancelled' => 'Cancelled',
    ];

    /** CheckinStatusCode values as customers read them. */
    private const CHECKIN_STATUSES = [
        'Checkable' => 'Ready for check-in',
        'CheckedIn' => 'Checked in',
        'Boarded' => 'Boarded',
        'NotAuthorized' => 'Check in at the airport',
        'APISValidationError' => 'Passport details needed',
    ];

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly BookingService $bookings,
        private readonly FlightConfigService $flights,
        private readonly ReferenceService $reference,
        private readonly ServerLog $log,
    ) {}

    // ------------------------------------------------------------ shared

    private static function unavailable(): ApiException
    {
        return new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
    }

    /** UniqueID and Verification for a booking on the customer's account. */
    private function id(object $customer, string $pnr): array
    {
        return ['UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $pnr], 'Verification' => $this->bookings->verification($customer, $pnr)];
    }

    /**
     * Calls Zenith; a refusal becomes a 409 with $code and Zenith's own
     * message (it says why: fare rules, time limits, availability...).
     */
    private function call(string $operation, array $body, string $code, string $intro): array
    {
        try {
            return $this->zenith->call($operation, $body);
        } catch (ZenithBusinessException $e) {
            throw $this->refusal($e, $operation, $body['UniqueID']['ID'] ?? null, $code, $intro);
        } catch (ZenithUnavailableException) {
            throw self::unavailable();
        }
    }

    private function refusal(ZenithBusinessException $e, string $operation, ?string $pnr, string $code, string $intro): ApiException
    {
        if (preg_match('/quota/i', $e->zenithCode)) {
            return new ApiException(429, 'busy', 'The airline system is busy. Please try again in a minute.');
        }
        $this->log->warn('booking', "{$operation} refused", ['pnr' => $pnr, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);
        if ($e->zenithCode === 'DupePassengerFlight' || preg_match('/already (registered|booked)|already a passenger/i', $e->getMessage())) {
            return new ApiException(409, 'passenger_already_booked', 'A passenger on this booking is already booked on that flight. Choose another flight.');
        }

        return new ApiException(409, $code, trim($intro.' '.$e->getMessage()));
    }

    /**
     * The live booking, saved; refuses cancelled and expired ones, except
     * flights cancelled with the tickets kept: those tickets can still be
     * changed to another flight.
     */
    private function liveBooking(object $customer, string $pnr): array
    {
        $this->bookings->ownedBooking($customer, $pnr);
        $b = $this->bookings->loadZenithBooking($pnr, $customer);
        $summary = $this->bookings->saveBooking($customer, $b);
        if ($summary['status'] === 'expired' || ($summary['status'] === 'cancelled' && empty($summary['keptTickets']))) {
            throw new ApiException(409, 'not_modifiable', 'This booking can no longer be changed online.');
        }

        return [$b, $summary];
    }

    private function fareFamilies(): array
    {
        try {
            return FlightSearch::fareFamilies($this->flights->valueCodes('WebClass'));
        } catch (Throwable) {
            return [];
        }
    }

    /** Search results the app's flight list understands, priced for this booking's passengers. */
    private function results(array $raw, array $booking): array
    {
        $raw['Passengers'] ??= $booking['Passengers'] ?? [];

        return FlightSearch::normalize($raw, $this->fareFamilies());
    }

    /** Zenith segments of an itinerary, in order. */
    private static function itinerarySegments(array $source, ?string $itineraryRef, ?int $order = null): array
    {
        $byRef = [];
        foreach (Js::list($source['Segments'] ?? null) as $s) {
            $byRef[(string) $s['Ref']] = $s;
        }
        foreach (Js::list($source['FareInfo']['Itineraries'] ?? null) as $it) {
            if ($itineraryRef !== null && $it['Ref'] !== $itineraryRef) {
                continue;
            }
            $out = [];
            foreach (Js::list($it['AirOriginDestinations'] ?? null) as $od) {
                if ($order !== null && (int) ($od['OriginDestinationOrder'] ?? 0) !== $order) {
                    continue;
                }
                $coupons = Js::list($od['AirCoupons'] ?? null);
                usort($coupons, fn ($a, $b) => ($a['CouponOrder'] ?? 0) <=> ($b['CouponOrder'] ?? 0));
                foreach ($coupons as $c) {
                    if (isset($byRef[(string) ($c['RefSegment'] ?? '')])) {
                        $out[] = $byRef[(string) $c['RefSegment']];
                    }
                }
            }

            return $out;
        }

        return [];
    }

    /** A Zenith money amount. */
    private static function amount(?array $a): float
    {
        return Js::round2($a['TotalAmount'] ?? 0);
    }

    private static function date(string $date): string
    {
        if (! FlightSearch::isRealDate($date)) {
            throw ApiException::invalid('Choose a valid date.');
        }
        if ($date < Js::today()) {
            throw ApiException::invalid('Choose a date from today onwards.');
        }

        return "{$date}T00:00:00";
    }

    // ------------------------------------------------------------ flight status

    /** "ON  092" and "ON092" are the same flight. */
    private static function flightKey(string $number): string
    {
        $n = strtoupper(preg_replace('/\s+/', '', $number));

        return preg_match('/^([A-Z0-9]{2})0*(\d+)$/', $n, $m) ? $m[1].$m[2] : $n;
    }

    /**
     * Live status of the booking's flights from the airline's operations
     * (only available around the day of travel). Asked at most every two
     * minutes per route and day.
     *
     * @return array{flights: list<array>, checkedAt: string}
     */
    public function flightStatus(object $customer, string $pnr): array
    {
        $this->bookings->ownedBooking($customer, $pnr);
        $summary = Js::decode((string) DB::table('bookings')->where('customer_id', $customer->id)
            ->where('zenith_environment', $customer->zenith_environment)->where('pnr', $pnr)->value('summary'));
        $today = CarbonImmutable::parse(Js::today());
        $out = [];
        foreach (is_array($summary) ? ($summary['flights'] ?? []) : [] as $f) {
            $date = substr((string) ($f['departure'] ?? ''), 0, 10);
            $row = [
                'flightNumber' => $f['flightNumber'],
                'origin' => $f['origin'],
                'destination' => $f['destination'],
                'departure' => $f['departure'] ?? null,
                'status' => null,
                'statusLabel' => null,
                'scheduledDeparture' => $f['departure'] ?? null,
                'expectedDeparture' => null,
                'actualDeparture' => null,
                'scheduledArrival' => $f['arrival'] ?? null,
                'expectedArrival' => null,
                'actualArrival' => null,
                'delayReason' => null,
                'note' => null,
            ];
            if (! FlightSearch::isRealDate($date)) {
                $out[] = $row;

                continue;
            }
            $days = $today->diffInDays(CarbonImmutable::parse($date), false);
            if ($days > 1) {
                $out[] = [...$row, 'note' => 'Live status is shown from the day before the flight.'];

                continue;
            }
            if ($days < -1) {
                $out[] = [...$row, 'note' => 'This flight has already left.'];

                continue;
            }
            try {
                $answer = Cache::store(config('naoero.cache_store'))->remember(
                    "zenith:flight-status:{$f['origin']}-{$f['destination']}-{$date}",
                    120,
                    fn () => $this->zenith->call('FlightStatus', [
                        'CityPair' => ['OriginCode' => $f['origin'], 'DestinationCode' => $f['destination']],
                        'SearchDateTypeCode' => 'Departing',
                        'Date' => "{$date}T00:00:00",
                    ]),
                );
            } catch (ZenithBusinessException) {
                $answer = [];
            } catch (ZenithUnavailableException) {
                $out[] = [...$row, 'note' => 'Live status is unavailable right now.'];

                continue;
            }
            $match = null;
            foreach (Js::list($answer['FlightSummaries'] ?? null) as $s) {
                if (self::flightKey((string) ($s['FlightNumber'] ?? '')) === self::flightKey((string) $f['flightNumber'])) {
                    $match = $s;
                    break;
                }
            }
            if (! $match) {
                $out[] = [...$row, 'note' => 'No live status for this flight yet.'];

                continue;
            }
            $code = (string) ($match['AirportFlightStatusCode'] ?? '');
            $out[] = [
                ...$row,
                'status' => $code !== '' ? $code : null,
                'statusLabel' => self::FLIGHT_STATUSES[$code] ?? ($code !== '' ? FlightSearch::titleCase($code) : null),
                'scheduledDeparture' => $match['ScheduledDepartureTime'] ?? $row['scheduledDeparture'],
                'expectedDeparture' => $match['RevisedDepartureTime'] ?? null,
                'actualDeparture' => $match['RealDepartureTime'] ?? null,
                'scheduledArrival' => $match['ScheduledArrivalTime'] ?? $row['scheduledArrival'],
                'expectedArrival' => $match['EstimatedArrivalTime'] ?? null,
                'actualArrival' => $match['RealArrivalTime'] ?? null,
                'delayReason' => Js::text($match['DelayReason'] ?? null),
            ];
        }

        $this->log->info('booking', 'flight status viewed', ['pnr' => $pnr, 'statuses' => array_map(fn ($f) => $f['flightNumber'].':'.($f['status'] ?? '-'), $out)]);

        return ['flights' => $out, 'checkedAt' => Js::iso(CarbonImmutable::now())];
    }

    // ------------------------------------------------------------ passenger details

    /**
     * PrepareBookingModification as a booking-time "prepare": the editable
     * passenger SSRs, with document fields listed (modification answers
     * leave Data.Fields empty) and the flights still to fly.
     */
    private static function modificationPrep(array $res): array
    {
        $withFields = function (array $s): array {
            if ($s['Code'] === 'DOCS' && ! Js::list($s['Data']['Fields'] ?? null)) {
                $doc = $s['Data']['Docs']['Documents'][0] ?? [];
                $props = self::DOCUMENT_FIELDS;
                if (! empty($doc['DocumentIssuanceDate'])) {
                    $props[] = 'DocumentIssuanceDate';
                }
                $s['Data'] = [...(is_array($s['Data'] ?? null) ? $s['Data'] : []), 'Fields' => array_map(fn ($p) => "Documents[0].{$p}", $props)];
            }

            return $s;
        };
        $editable = fn ($s) => ! empty($s['RefPassenger']) && in_array($s['Code'], self::EDITABLE_SSRS, true);

        return [
            'Passengers' => Js::list($res['Passengers'] ?? null) ?: Js::list($res['Booking']['Passengers'] ?? null),
            'SpecialServices' => array_values(array_map($withFields, array_filter(Js::list($res['SpecialServices'] ?? null), $editable))),
            'OptionalSpecialServices' => array_values(array_map($withFields, array_filter(Js::list($res['OptionalSpecialServices'] ?? null), $editable))),
            'Segments' => BookingSummary::currentSegments($res['Booking'] ?? []),
        ];
    }

    /** What each passenger's form holds now, from the SSRs on the booking. */
    private static function currentValues(array $prep): array
    {
        $values = [];
        foreach ($prep['Passengers'] as $p) {
            $values[(string) $p['Ref']] = array_filter([
                'title' => $p['NameElement']['CivilityCode'] ?? null,
                'firstName' => $p['NameElement']['Firstname'] ?? null,
                'lastName' => $p['NameElement']['Surname'] ?? null,
            ]);
        }
        $day = fn ($v) => is_string($v) && strlen($v) >= 10 ? substr($v, 0, 10) : null;
        foreach ([...$prep['SpecialServices'], ...$prep['OptionalSpecialServices']] as $s) {
            $ref = (string) $s['RefPassenger'];
            $v = &$values[$ref];
            switch ($s['Code']) {
                case 'CTCE':
                    $v['email'] ??= Js::text($s['Text'] ?? null);
                    break;
                case 'CTCM':
                    $v['mobilePhone'] ??= Js::text($s['Text'] ?? null);
                    break;
                case 'CTCH':
                    $v['homePhone'] ??= Js::text($s['Text'] ?? null);
                    break;
                case 'EXT-ADOB':
                    $v['dateOfBirth'] ??= $day($s['Data']['Adof']['DateOfBirth'] ?? null);
                    break;
                case 'DOCS':
                    $doc = $s['Data']['Docs']['Documents'][0] ?? null;
                    if (is_array($doc)) {
                        foreach (BookingForm::DOC_KEYS as $prop => $field) {
                            $raw = $doc[$prop] ?? null;
                            $v[$field['key']] ??= $field['kind'] === 'date' ? $day($raw) : Js::text($raw);
                        }
                    }
                    break;
            }
            unset($v);
        }

        return array_map(fn ($v) => array_filter($v ?? [], fn ($x) => $x !== null && $x !== ''), $values);
    }

    /** @return array{prep: array, form: array, values: array, booking: array, ref: array} */
    private function passengerContext(object $customer, string $pnr): array
    {
        $this->bookings->ownedBooking($customer, $pnr);
        $res = $this->bookings->prepareModification($customer, $pnr);
        $summary = $this->bookings->saveBooking($customer, $res['Booking']);
        if (in_array($summary['status'], ['cancelled', 'expired'], true)) {
            throw new ApiException(409, 'not_modifiable', 'This booking can no longer be changed online.');
        }
        try {
            $ref = $this->bookings->referenceData();
        } catch (Throwable) {
            throw self::unavailable();
        }
        $prep = self::modificationPrep($res);
        $form = BookingForm::build($prep, $ref, self::EDITABLE_SSRS);
        // Names stay as booked (they are on the tickets); only the rest changes.
        $form['passengers'] = array_map(fn ($p) => [...$p, 'fields' => array_values(array_filter($p['fields'], fn ($f) => ! in_array($f['key'], ['title', 'firstName', 'lastName'], true)))], $form['passengers']);
        $form['bookingFields'] = [];

        return ['prep' => $prep, 'form' => $form, 'values' => self::currentValues($prep), 'booking' => $summary, 'ref' => $ref];
    }

    /**
     * The passenger details form, filled with what the booking holds.
     *
     * @return array<string, mixed>
     */
    public function passengers(object $customer, string $pnr): array
    {
        $c = $this->passengerContext($customer, $pnr);
        $names = BookingService::passengerNames(['Passengers' => $c['prep']['Passengers']]);
        $hidden = ['title', 'firstName', 'lastName'];

        return [
            'booking' => $c['booking'],
            'passengers' => array_map(fn ($p) => [...$p, 'name' => $names[$p['ref']] ?? ''], $c['form']['passengers']),
            'values' => Js::map(array_map(fn ($v) => array_diff_key($v, array_flip($hidden)), $c['values'])),
            'travel' => $c['form']['travel'],
            'nameChangeNote' => 'Names cannot be changed online because they are printed on the tickets. To correct a name, contact Naoero Airlines.',
        ];
    }

    /** The data an SSR carries, to tell whether an update changes it. */
    private static function ssrValue(array $s): string
    {
        if ($s['Code'] === 'DOCS') {
            $doc = $s['Data']['Docs']['Documents'][0] ?? [];
            $keep = array_intersect_key(is_array($doc) ? $doc : [], array_flip([...array_keys(BookingForm::DOC_KEYS)]));
            ksort($keep);

            return json_encode(array_map(fn ($v) => is_string($v) ? strtoupper($v) : $v, array_filter($keep, fn ($v) => $v !== null && $v !== '')));
        }
        if ($s['Code'] === 'EXT-ADOB') {
            return (string) substr((string) ($s['Data']['Adof']['DateOfBirth'] ?? ''), 0, 10);
        }

        return strtolower(trim((string) ($s['Text'] ?? '')));
    }

    /**
     * Updates contact details and travel documents (Zenith ModifyBooking).
     * Only what changed is sent; the other passengers are not revalidated.
     *
     * @param  array{passengers: list<array{ref: string, values: array}>}  $input
     * @return array{booking: array, message: string}
     */
    public function updatePassengers(object $customer, string $pnr, array $input): array
    {
        $c = $this->passengerContext($customer, $pnr);
        $known = array_column($c['form']['passengers'], 'ref');
        $merged = [];
        foreach ($c['form']['passengers'] as $p) {
            $merged[$p['ref']] = $c['values'][$p['ref']] ?? [];
        }
        foreach ($input['passengers'] as $p) {
            if (! in_array($p['ref'], $known, true)) {
                throw ApiException::invalid('Unknown passenger in the request.');
            }
            $values = array_diff_key(is_array($p['values'] ?? null) ? $p['values'] : [], array_flip(['title', 'firstName', 'lastName']));
            $merged[$p['ref']] = [...$merged[$p['ref']], ...array_map(fn ($v) => is_string($v) ? $v : null, $values)];
        }
        $request = ['passengers' => array_map(fn ($ref, $v) => ['ref' => (string) $ref, 'values' => $v], array_keys($merged), $merged)];

        try {
            $countries = array_fill_keys(array_column($this->reference->countries(), 'code'), true);
        } catch (Throwable) {
            throw self::unavailable();
        }
        $problems = BookingForm::validate($c['form'], $request, $c['ref'], $countries);
        if ($problems) {
            throw new ApiException(422, 'invalid_passengers', 'Please check the highlighted details.', problems: $problems);
        }

        $filled = BookingForm::apply($c['prep'], $c['form'], $request, self::EDITABLE_SSRS);
        $before = [];
        foreach ([...$c['prep']['SpecialServices'], ...$c['prep']['OptionalSpecialServices']] as $s) {
            $before["{$s['Code']}|{$s['RefPassenger']}"] = self::ssrValue($s);
        }
        $changed = [];
        foreach ($filled['SpecialServices'] as $s) {
            if (($before["{$s['Code']}|{$s['RefPassenger']}"] ?? null) === self::ssrValue($s)) {
                continue;
            }
            // The structured data is the update; the old free text would contradict it.
            if (in_array($s['Code'], ['DOCS', 'EXT-ADOB'], true)) {
                unset($s['Text']);
            }
            $changed[] = $s;
        }
        if (! $changed) {
            throw ApiException::invalid('Nothing has changed.');
        }

        $touched = array_unique(array_map(fn ($s) => (string) $s['RefPassenger'], $changed));
        try {
            $out = $this->zenith->call('ModifyBooking', [
                ...$this->id($customer, $pnr),
                'SpecialServices' => $changed,
                'DeferredIssuance' => true,
                'IgnorePassengerRefValidations' => array_values(array_diff(array_map('strval', $known), $touched)),
            ]);
        } catch (ZenithBusinessException $e) {
            $invalid = $e->body['InvalidData'] ?? null;
            if (is_array($invalid)) {
                $mapped = BookingService::problemsFromInvalidData($invalid, $c['form']);
                throw new ApiException(422, 'invalid_passengers', 'The airline needs some details corrected.',
                    problems: $mapped ?: [['passengerRef' => null, 'field' => null, 'message' => $e->getMessage()]]);
            }
            if (preg_match('/quota/i', $e->zenithCode)) {
                throw new ApiException(429, 'busy', 'The airline system is busy. Please try again in a minute.');
            }
            $this->log->warn('booking', 'ModifyBooking (passengers) refused', ['pnr' => $pnr, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);
            throw new ApiException(409, 'modification_refused', 'The airline could not make this change: '.$e->getMessage());
        } catch (ZenithUnavailableException) {
            throw self::unavailable();
        }
        if (is_array($out['InvalidData'] ?? null) && array_filter($out['InvalidData'])) {
            $mapped = BookingService::problemsFromInvalidData($out['InvalidData'], $c['form']);
            if ($mapped) {
                throw new ApiException(422, 'invalid_passengers', 'The airline needs some details corrected.', problems: $mapped);
            }
        }

        $summary = $this->bookings->saveBooking($customer, $this->bookings->loadZenithBooking($pnr, $customer));
        $this->log->info('booking', 'passenger details changed', ['pnr' => $pnr, 'services' => array_column($changed, 'Code')]);
        app(BookingNotices::class)->passengersUpdated($customer, $summary);

        return ['booking' => $summary, 'message' => 'Passenger details updated.'];
    }

    // ------------------------------------------------------------ change flights

    /** The exchangeable leg $order of a booking (MiscInfo.ExchangeableOriginDestinations). */
    private static function exchangeableLeg(array $b, int $order): array
    {
        foreach (Js::list($b['MiscInfo']['ExchangeableOriginDestinations'] ?? null) as $od) {
            if ((int) ($od['OriginDestinationOrder'] ?? 0) === $order) {
                return $od;
            }
        }
        throw new ApiException(409, 'change_not_allowed', 'This part of the trip cannot be changed online. Please contact Naoero Airlines.');
    }

    /**
     * What can be changed: the legs (outbound, return) Zenith allows to
     * exchange, and a change already made but not paid.
     *
     * @return array<string, mixed>
     */
    public function changeOptions(object $customer, string $pnr): array
    {
        [$b, $summary] = $this->liveBooking($customer, $pnr);
        $legs = [];
        foreach (Js::list($b['MiscInfo']['ExchangeableOriginDestinations'] ?? null) as $od) {
            $order = (int) ($od['OriginDestinationOrder'] ?? 0);
            $segments = self::itinerarySegments($b, $od['RefItinerary'] ?? null, $order);
            if (! $segments) {
                continue;
            }
            $legs[] = [
                'order' => $order,
                'origin' => $segments[0]['OriginCode'],
                'destination' => end($segments)['DestinationCode'],
                'departure' => $segments[0]['FlightInfo']['DepartureDate'] ?? null,
                'flights' => array_map(BookingSummary::flight(...), $segments),
            ];
        }
        $reason = match (true) {
            ! empty($summary['pendingChange']) => null,
            $summary['status'] !== 'ticketed' && ! $summary['tickets'] => 'Pay for this booking first. Flights can be changed once your tickets are issued.',
            ! $legs => 'The flights on this booking cannot be changed online. Please contact Naoero Airlines.',
            default => null,
        };

        return [
            'booking' => $summary,
            'canChange' => $reason === null && $legs !== [] && empty($summary['pendingChange']),
            'unavailableReason' => $reason,
            'legs' => $reason === null ? $legs : [],
            'pendingChange' => $summary['pendingChange'] ?? null,
            'policy' => 'Change fees and any fare difference depend on your fare. You will see the exact amount before you confirm, and pay it to keep the new flights.',
        ];
    }

    /** New flights for a leg, on another date (SearchFlightsForExchange). */
    public function searchChange(object $customer, string $pnr, int $order, string $date): array
    {
        [$b, $summary] = $this->liveBooking($customer, $pnr);
        if (! empty($summary['pendingChange'])) {
            throw new ApiException(409, 'change_pending', 'A flight change is waiting for payment. Pay for it or cancel it first.');
        }
        $od = self::exchangeableLeg($b, $order);
        $segments = self::itinerarySegments($b, $od['RefItinerary'] ?? null, $order);
        if (! $segments) {
            throw new ApiException(409, 'change_not_allowed', 'This part of the trip cannot be changed online. Please contact Naoero Airlines.');
        }
        $request = [
            ...$this->id($customer, $pnr),
            'TicketFaresToExchange' => Js::list($od['ETTicketFareTargets'] ?? null),
            'FareDisplaySettings' => ['ShowWebClasses' => true],
            'OriginDestinations' => [['TargetDate' => self::date($date), 'OriginCode' => $segments[0]['OriginCode'], 'DestinationCode' => end($segments)['DestinationCode']]],
        ];
        try {
            $raw = $this->zenith->call('SearchFlightsForExchange', $request);
        } catch (ZenithBusinessException $e) {
            if (in_array($e->zenithCode, ['NoAvailability', 'NoFares'], true)) {
                $this->log->info('booking', 'flight change searched', ['pnr' => $pnr, 'leg' => $order, 'date' => $date, 'journeys' => 0, 'reason' => $e->zenithCode]);

                return ['results' => FlightSearch::emptyResult($summary['currency'], $e->zenithCode), 'currentFare' => $summary['totalAmount']];
            }
            throw $this->refusal($e, 'SearchFlightsForExchange', $pnr, 'change_not_allowed', 'These flights cannot be changed:');
        } catch (ZenithUnavailableException) {
            throw self::unavailable();
        }

        $results = $this->results($raw, $b);
        $this->log->info('booking', 'flight change searched', ['pnr' => $pnr, 'leg' => $order, 'date' => $date, 'journeys' => count($results['journeys'])]);

        return ['results' => $results, 'currentFare' => $summary['totalAmount']];
    }

    /**
     * The price of a change before it is made (PrepareExchange): the change
     * fees and the fare difference. Zenith never refunds a cheaper fare, so
     * the difference is never negative.
     */
    public function quoteChange(object $customer, string $pnr, int $order, string $offerRef, string $itineraryRef): array
    {
        [$b, $summary] = $this->liveBooking($customer, $pnr);
        $od = self::exchangeableLeg($b, $order);
        $res = $this->call('PrepareExchange', [
            ...$this->id($customer, $pnr),
            'Offer' => ['Ref' => $offerRef, 'RefItinerary' => $itineraryRef],
            'TicketFaresToExchange' => Js::list($od['ETTicketFareTargets'] ?? null),
            'IncludeAllOptionalSpecialServices' => true,
            'DeferredIssuance' => true,
        ], 'offer_changed', 'This flight is no longer available:');
        if (array_filter(Js::list($res['SpecialServices'] ?? null), fn ($s) => ! empty($s['RefPassenger']) && empty($s['Data']) && empty($s['Text']))) {
            throw new ApiException(409, 'change_needs_details', 'The new flights need passenger details that can only be added by Naoero Airlines. Please contact us to make this change.');
        }
        $fare = is_array($res['FareInfo'] ?? null) ? $res['FareInfo'] : [];
        $currency = $fare['SaleCurrencyCode'] ?? $summary['currency'];

        // Zenith's own lines: change penalties, and the adjustment it adds when
        // the new fare is cheaper (the original price is kept, nothing is refunded).
        $names = BookingService::passengerNames($b);
        $lines = [];
        foreach (Js::list($fare['EMDTicketFares'] ?? null) as $e) {
            $label = trim((string) ($e['Label'] ?? '')) ?: 'Change fee';
            $key = $label.'|'.($e['RefPassenger'] ?? '');
            $lines[$key] ??= ['label' => $label, 'passenger' => $names[(string) ($e['RefPassenger'] ?? '')] ?? null, 'amount' => 0.0];
            $lines[$key]['amount'] = Js::round2($lines[$key]['amount'] + self::amount($e['SaleCurrencyAmount'] ?? null));
        }
        $lines = array_values($lines);
        $fees = Js::round2(array_sum(array_column($lines, 'amount')));

        // Fare difference between the itinerary being replaced and the new one,
        // as Zenith priced them; never negative.
        $oldFare = null;
        foreach (Js::list($b['FareInfo']['Itineraries'] ?? null) as $it) {
            if ($it['Ref'] === ($od['RefItinerary'] ?? null)) {
                $oldFare = self::amount($it['SaleCurrencyAmount'] ?? null);
            }
        }
        $oldFare ??= $summary['totalAmount'];
        $newFare = null;
        foreach (Js::list($fare['Itineraries'] ?? null) as $it) {
            if ($it['Ref'] === $itineraryRef) {
                $newFare = self::amount($it['SaleCurrencyAmount'] ?? null);
            }
        }
        $newFare ??= self::amount($fare['SaleCurrencyAmountTotal'] ?? null);
        $difference = max(0, Js::round2($newFare - $oldFare));
        $total = max(0, Js::round2($fees + $difference));
        $this->log->info('booking', 'flight change quoted', ['pnr' => $pnr, 'currency' => $currency, 'fees' => $fees, 'oldFare' => $oldFare, 'newFare' => $newFare, 'estimated' => $total]);

        return [
            'currency' => $currency,
            'lines' => $lines,
            'changeFees' => $fees,
            'currentFare' => $oldFare,
            'newFare' => $newFare,
            'fareDifference' => $difference,
            'estimatedTotal' => $total,
            'cheaper' => $newFare < $oldFare,
            'flights' => array_map(BookingSummary::flight(...), Js::list($res['Segments'] ?? null)),
            'note' => $newFare < $oldFare
                ? 'The new flights cost less, but the difference is not refunded: you keep your original fare.'
                : 'The airline confirms the exact amount when you make the change. Your current flights are kept until it is paid.',
        ];
    }

    /**
     * Makes the change (Exchange): the new flights are held, the old ones
     * kept, until the amount due is paid (BookingService::startPayment) or
     * the change is cancelled.
     *
     * @return array{booking: array, message: string}
     */
    public function confirmChange(object $customer, string $pnr, int $order, string $offerRef, string $itineraryRef): array
    {
        [$b, $summary] = $this->liveBooking($customer, $pnr);
        if (! empty($summary['pendingChange'])) {
            throw new ApiException(409, 'change_pending', 'A flight change is waiting for payment. Pay for it or cancel it first.');
        }
        $od = self::exchangeableLeg($b, $order);
        $res = $this->call('Exchange', [
            ...$this->id($customer, $pnr),
            'Offer' => ['Ref' => $offerRef, 'RefItinerary' => $itineraryRef],
            'TicketFaresToExchange' => Js::list($od['ETTicketFareTargets'] ?? null),
            'SpecialServices' => [],
            'DeferredIssuance' => true,
        ], 'offer_changed', 'This change could not be made:');
        $after = is_array($res['Booking'] ?? null) ? $res['Booking'] : $this->bookings->loadZenithBooking($pnr, $customer);
        $summary = $this->bookings->saveBooking($customer, $after);
        $this->log->info('booking', 'flight change made', ['pnr' => $pnr, 'due' => $summary['pendingChange']['amountDue'] ?? null]);
        app(BookingNotices::class)->changeHeld($customer, $summary);
        $due = $summary['pendingChange']['amountDue'] ?? $summary['amountDue'];

        return [
            'booking' => $summary,
            'message' => $due > 0
                ? 'Your new flights are held. Pay '.number_format($due, 2).' '.$summary['currency'].' to confirm the change.'
                : 'Your new flights are held. Confirm the change to get your new tickets.',
        ];
    }

    /** Drops a change that was not paid (CancelPendingExchange); the old flights stay. */
    public function cancelChange(object $customer, string $pnr): array
    {
        [$b] = $this->liveBooking($customer, $pnr);
        if (empty($b['MiscInfo']['IsPendingExchange'])) {
            throw new ApiException(409, 'no_pending_change', 'There is no flight change to cancel.');
        }
        $this->call('CancelPendingExchange', $this->id($customer, $pnr), 'change_not_cancelled', 'The change could not be cancelled:');
        $summary = $this->bookings->saveBooking($customer, $this->bookings->loadZenithBooking($pnr, $customer));
        $this->log->info('booking', 'flight change cancelled', ['pnr' => $pnr]);
        app(BookingNotices::class)->changeCancelled($customer, $summary);

        return ['booking' => $summary, 'message' => 'The change was cancelled. Your original flights are unchanged.'];
    }

    // ------------------------------------------------------------ add a trip

    /** Flights to add to the booking (SearchFlightsForAdditionalItinerary). */
    public function searchTrip(object $customer, string $pnr, string $origin, string $destination, string $date): array
    {
        [$b, $summary] = $this->liveBooking($customer, $pnr);
        $request = [
            ...$this->id($customer, $pnr),
            'FareDisplaySettings' => ['ShowWebClasses' => true],
            // A single origin-destination here, not a list.
            'OriginDestinations' => ['TargetDate' => self::date($date), 'OriginCode' => $origin, 'DestinationCode' => $destination],
        ];
        try {
            $raw = $this->zenith->call('SearchFlightsForAdditionalItinerary', $request);
        } catch (ZenithBusinessException $e) {
            if (in_array($e->zenithCode, ['NoAvailability', 'NoFares'], true)) {
                $this->log->info('booking', 'trip searched', ['pnr' => $pnr, 'route' => "{$origin}-{$destination}", 'date' => $date, 'journeys' => 0, 'reason' => $e->zenithCode]);

                return ['results' => FlightSearch::emptyResult($summary['currency'], $e->zenithCode)];
            }
            throw $this->refusal($e, 'SearchFlightsForAdditionalItinerary', $pnr, 'trip_not_allowed', 'A trip cannot be added to this booking:');
        } catch (ZenithUnavailableException) {
            throw self::unavailable();
        }

        $results = $this->results($raw, $b);
        $this->log->info('booking', 'trip searched', ['pnr' => $pnr, 'route' => "{$origin}-{$destination}", 'date' => $date, 'journeys' => count($results['journeys'])]);

        return ['results' => $results];
    }

    /** The price of the added trip (PrepareAdditionalItinerary). */
    public function quoteTrip(object $customer, string $pnr, string $offerRef, string $itineraryRef): array
    {
        [, $summary] = $this->liveBooking($customer, $pnr);
        $res = $this->call('PrepareAdditionalItinerary', [
            ...$this->id($customer, $pnr),
            'Offer' => ['Ref' => $offerRef, 'RefItinerary' => $itineraryRef],
            'IncludeAllOptionalSpecialServices' => true,
            'DeferredIssuance' => true,
        ], 'offer_changed', 'This flight is no longer available:');
        if (array_filter(Js::list($res['SpecialServices'] ?? null), fn ($s) => ! empty($s['RefPassenger']) && empty($s['Data']) && empty($s['Text']))) {
            throw new ApiException(409, 'trip_needs_details', 'This trip needs passenger details that can only be added by Naoero Airlines. Please contact us to add it.');
        }
        $fare = is_array($res['FareInfo'] ?? null) ? $res['FareInfo'] : [];
        $total = $fare['SaleCurrencyAmountTotal'] ?? null;
        $this->log->info('booking', 'trip quoted', ['pnr' => $pnr, 'currency' => $fare['SaleCurrencyCode'] ?? null, 'total' => self::amount($total)]);

        return [
            'currency' => $fare['SaleCurrencyCode'] ?? $summary['currency'],
            'total' => self::amount($total),
            'base' => Js::round2($total['BaseAmount'] ?? 0),
            'tax' => Js::round2($total['TaxAmount'] ?? 0),
            'flights' => array_map(BookingSummary::flight(...), Js::list($res['Segments'] ?? null)),
        ];
    }

    /**
     * Adds the trip (AddItinerary). It is held until paid like any
     * booking; the booking's existing tickets are not affected.
     *
     * @return array{booking: array, message: string}
     */
    public function addTrip(object $customer, string $pnr, string $offerRef, string $itineraryRef): array
    {
        $this->liveBooking($customer, $pnr);
        $res = $this->call('AddItinerary', [
            ...$this->id($customer, $pnr),
            'Offer' => ['Ref' => $offerRef, 'RefItinerary' => $itineraryRef],
            'SpecialServices' => [],
            'DeferredIssuance' => true,
        ], 'offer_changed', 'The trip could not be added:');
        $after = is_array($res['Booking'] ?? null) ? $res['Booking'] : $this->bookings->loadZenithBooking($pnr, $customer);
        $summary = $this->bookings->saveBooking($customer, $after);
        $this->log->info('booking', 'trip added', ['pnr' => $pnr, 'due' => $summary['amountDue']]);
        app(BookingNotices::class)->tripAdded($customer, $summary);

        return [
            'booking' => $summary,
            'message' => $summary['amountDue'] > 0
                ? 'The trip was added. Pay '.number_format($summary['amountDue'], 2).' '.$summary['currency'].' before the time shown to keep it.'
                : 'The trip was added.',
        ];
    }

    // ------------------------------------------------------------ split

    /**
     * Moves passengers to a booking of their own (SplitBooking), so their
     * plans can change without touching the others. The new booking is
     * added to the customer's trips.
     *
     * @param  list<string>  $passengerRefs
     * @return array{booking: array, newBooking: array, message: string}
     */
    public function split(object $customer, string $pnr, array $passengerRefs): array
    {
        $owned = $this->bookings->ownedBooking($customer, $pnr);
        [$b] = $this->liveBooking($customer, $pnr);
        $passengers = Js::list($b['Passengers'] ?? null);
        $byRef = [];
        foreach ($passengers as $p) {
            $byRef[(string) $p['Ref']] = $p;
        }
        $refs = array_values(array_unique($passengerRefs));
        if (count($passengers) < 2) {
            $this->log->info('booking', 'split refused', ['pnr' => $pnr, 'reason' => 'one passenger']);

            throw new ApiException(409, 'split_not_possible', 'This booking has only one passenger.');
        }
        foreach ($refs as $ref) {
            if (! isset($byRef[$ref])) {
                throw ApiException::invalid('Unknown passenger in the request.');
            }
        }
        if (count($refs) >= count($passengers)) {
            throw ApiException::invalid('Leave at least one passenger on this booking.');
        }
        $moving = array_map(fn ($r) => $byRef[$r], $refs);
        if (! array_filter($moving, fn ($p) => BookingForm::isAdultType((string) $p['PassengerTypeCode']))) {
            throw ApiException::invalid('Children and infants must move with an adult.');
        }
        if (! array_filter(array_diff_key($byRef, array_flip($refs)), fn ($p) => BookingForm::isAdultType((string) $p['PassengerTypeCode']))) {
            throw ApiException::invalid('An adult must stay on this booking with the children and infants.');
        }

        $res = $this->call('SplitBooking', [
            ...$this->id($customer, $pnr),
            'Comment' => 'Split by the customer in the Naoero Airlines app',
            'PassengersToSplit' => array_map(fn ($r) => ['Ref' => $r, 'Quantity' => 1], $refs),
        ], 'split_refused', 'The booking could not be split:');
        $newPnr = strtoupper((string) ($res['PnrCode'] ?? ''));
        if ($newPnr === '') {
            throw self::unavailable();
        }

        // Bookings added with a surname are verified by a passenger's name;
        // the new booking holds the moved passengers.
        $verification = $owned->verify_surname ? ['PassengerName' => (string) ($moving[0]['NameElement']['Surname'] ?? $owned->verify_surname)] : null;
        $link = $owned->verify_surname ? ['source' => $owned->source, 'verify_surname' => $verification['PassengerName'], 'linked_at' => Js::dbTime()] : ['source' => $owned->source ?: 'app'];
        $newSummary = $this->bookings->saveBooking($customer, $this->bookings->loadZenithBooking($newPnr, $customer, $verification), $link);
        $summary = $this->bookings->saveBooking($customer, $this->bookings->loadZenithBooking($pnr, $customer));
        $this->log->info('booking', 'booking split', ['pnr' => $pnr, 'newPnr' => $newPnr, 'passengers' => count($refs), 'forced' => (bool) ($res['HasForcedSplit'] ?? false)]);

        return [
            'booking' => $summary,
            'newBooking' => $newSummary,
            'message' => 'Done. '.implode(', ', array_map(fn ($p) => trim(($p['NameElement']['Firstname'] ?? '').' '.($p['NameElement']['Surname'] ?? '')), $moving)).' now '.(count($moving) > 1 ? 'have' : 'has')." booking {$newPnr}.",
        ];
    }

    // ------------------------------------------------------------ check-in

    /** Check-in groups from PrepareCheckin, with each passenger's status. */
    private static function checkinGroups(array $res): array
    {
        $b = $res['Booking'] ?? [];
        $names = BookingService::passengerNames($b);
        $tickets = [];
        foreach (Js::list($b['TicketInfo']['ETTickets'] ?? null) as $t) {
            $tickets[(string) ($t['DocumentNumber'] ?? '')] = $t;
        }
        $groups = [];
        foreach (Js::list($res['CheckinTargets'] ?? null) as $i => $target) {
            $flights = [];
            $passengers = [];
            $checkable = true;
            $any = false;
            foreach (Js::list($target['ETTicketCheckinList'] ?? null) as $tc) {
                $number = (string) ($tc['DocumentNumber'] ?? '');
                $ticket = $tickets[$number] ?? [];
                $coupons = [];
                foreach (Js::list($ticket['Coupons'] ?? null) as $cp) {
                    $coupons[(int) $cp['CouponOrder']] = $cp;
                }
                $statuses = [];
                $reasons = [];
                $seatNeeded = false;
                foreach (Js::list($tc['ETCouponCheckinList'] ?? null) as $cc) {
                    $any = true;
                    $code = (string) ($cc['CheckinStatusCode'] ?? '');
                    $statuses[] = $code;
                    $seatNeeded = $seatNeeded || ! empty($cc['SeatingMandatory']);
                    foreach ([$cc['NotAuthorizedReason'] ?? null, ...Js::list($cc['NotAuthorizedDetailedReasons'] ?? null)] as $r) {
                        if (is_string($r) && trim($r) !== '' && ! in_array(trim($r), $reasons, true)) {
                            $reasons[] = trim($r);
                        }
                    }
                    $sold = $coupons[(int) ($cc['CouponOrder'] ?? 0)]['SegmentSold'] ?? null;
                    if (is_array($sold) && ! isset($flights[(int) $cc['CouponOrder']])) {
                        $flights[(int) $cc['CouponOrder']] = BookingSummary::flight([...$sold, 'BookingClass' => ['StatusCode' => null]]);
                    }
                }
                $status = match (true) {
                    $statuses && ! array_diff($statuses, ['CheckedIn', 'Boarded']) => 'CheckedIn',
                    in_array('APISValidationError', $statuses, true) => 'APISValidationError',
                    $statuses && ! array_diff($statuses, ['Checkable']) => 'Checkable',
                    default => 'NotAuthorized',
                };
                $checkable = $checkable && $status === 'Checkable';
                $passengers[] = [
                    'ticketNumber' => $number,
                    'passengerRef' => $ticket['RefPassenger'] ?? null,
                    'name' => $names[(string) ($ticket['RefPassenger'] ?? '')] ?? null,
                    'status' => $status,
                    'statusLabel' => self::CHECKIN_STATUSES[$status],
                    'reasons' => $status === 'Checkable' || $status === 'CheckedIn' ? [] : $reasons,
                    'seatRequired' => $seatNeeded,
                ];
            }
            ksort($flights);
            $groups[] = [
                'id' => $i,
                'flights' => array_values($flights),
                'passengers' => $passengers,
                'canCheckIn' => $any && $checkable,
                'checkedIn' => $passengers && ! array_filter($passengers, fn ($p) => $p['status'] !== 'CheckedIn'),
            ];
        }

        return $groups;
    }

    /** @return list<array{ticketNumber: ?string, url: string, flights: list<array>}> */
    private static function boardingPasses(array $b): array
    {
        $out = [];
        foreach (Js::list($b['MiscInfo']['BoardingPassList'] ?? null) as $bp) {
            $url = BookingSummary::zenithLink($bp['HttpLink'] ?? null);
            if ($url) {
                $out[] = ['ticketNumber' => $bp['DocumentNumber'] ?? null, 'couponOrders' => Js::list($bp['CouponOrders'] ?? null), 'url' => $url];
            }
        }

        return $out;
    }

    private function prepareCheckin(object $customer, string $pnr): array
    {
        return $this->call('PrepareCheckin', $this->id($customer, $pnr), 'checkin_unavailable', 'Online check-in is not available for this booking:');
    }

    /**
     * Online check-in: who can check in on which flights, why not when the
     * airline refuses, and the boarding passes once checked in.
     *
     * @return array<string, mixed>
     */
    public function checkIn(object $customer, string $pnr): array
    {
        [$b, $summary] = $this->liveBooking($customer, $pnr);
        if ($summary['status'] !== 'ticketed') {
            return ['booking' => $summary, 'groups' => [], 'boardingPasses' => [], 'unavailableReason' => 'Check-in opens once your tickets are issued.'];
        }
        $res = $this->prepareCheckin($customer, $pnr);
        $groups = self::checkinGroups($res);
        $this->log->info('booking', 'check-in viewed', ['pnr' => $pnr, 'statuses' => array_merge(...array_map(fn ($g) => array_column($g['passengers'], 'status'), $groups ?: [['passengers' => []]]))]);
        $reason = null;
        if (! $groups) {
            $reason = 'Online check-in is not open for these flights yet.';
        } elseif (! array_filter($groups, fn ($g) => $g['canCheckIn'] || $g['checkedIn'])) {
            $reason = 'Online check-in is not available for these flights. Please check in at the airport.';
        }

        return [
            'booking' => $summary,
            'groups' => $groups,
            'boardingPasses' => self::boardingPasses(is_array($res['Booking'] ?? null) ? $res['Booking'] : $b),
            'unavailableReason' => $reason,
            'seatNote' => 'If a seat is required, choose one in Seats & Extras before checking in.',
            // Undoing a check-in, when the airline allows it, and what it means.
            'undo' => ['available' => (bool) config('naoero.allow_uncheck'), 'warning' => self::UNCHECK_WARNING],
        ];
    }

    private const UNCHECK_WARNING = 'Undoing check-in cancels these flights for the passengers as a no-show and gives up their seats. The booking can then no longer be changed online, and only Naoero Airlines can put the flights back.';

    /**
     * Undoes the check-in of a whole group (Uncheck), when the airline
     * allows it. Zenith sets the coupons back to open but cancels the
     * flights for those passengers as a no-show, so the customer must have
     * confirmed the warning; passengers already boarded can't be unchecked.
     *
     * @return array<string, mixed>
     */
    public function undoCheckIn(object $customer, string $pnr, int $groupId, bool $confirmed): array
    {
        if (! config('naoero.allow_uncheck')) {
            throw new ApiException(403, 'uncheck_not_offered', 'Check-in cannot be undone online. Please contact Naoero Airlines.');
        }
        if (! $confirmed) {
            throw new ApiException(400, 'confirmation_required', self::UNCHECK_WARNING.' Confirm to continue.');
        }
        [, $summary] = $this->liveBooking($customer, $pnr);
        if ($summary['status'] !== 'ticketed') {
            throw new ApiException(409, 'not_checked_in', 'These passengers are not checked in.');
        }
        $res = $this->prepareCheckin($customer, $pnr);
        $target = Js::list($res['CheckinTargets'] ?? null)[$groupId] ?? null;
        if (! $target) {
            throw new ApiException(409, 'not_checked_in', 'These flights are no longer listed for check-in. Please refresh.');
        }
        $targets = [];
        foreach (Js::list($target['ETTicketCheckinList'] ?? null) as $tc) {
            $orders = [];
            foreach (Js::list($tc['ETCouponCheckinList'] ?? null) as $cc) {
                $code = (string) ($cc['CheckinStatusCode'] ?? '');
                if ($code === 'Boarded') {
                    throw new ApiException(409, 'already_boarded', 'A passenger has already boarded: check-in can no longer be undone.');
                }
                if ($code === 'CheckedIn') {
                    $orders[] = (int) $cc['CouponOrder'];
                }
            }
            if ($orders) {
                $targets[] = ['DocumentNumber' => $tc['DocumentNumber'], 'CouponOrders' => $orders];
            }
        }
        if (! $targets) {
            throw new ApiException(409, 'not_checked_in', 'Nobody is checked in on these flights.');
        }

        $out = $this->call('Uncheck', [...$this->id($customer, $pnr), 'ETTicketTargets' => $targets], 'uncheck_refused', 'Check-in was not undone:');
        $after = is_array($out['Booking'] ?? null) ? $out['Booking'] : $this->bookings->loadZenithBooking($pnr, $customer);
        $saved = $this->bookings->saveBooking($customer, $after);
        $this->log->warn('booking', 'check-in undone', ['pnr' => $pnr, 'tickets' => count($targets), 'coupons' => array_sum(array_map(fn ($t) => count($t['CouponOrders']), $targets))]);
        app(BookingNotices::class)->checkInUndone($customer, $saved);

        return [
            'booking' => $saved,
            'message' => 'Check-in is undone. These flights are now cancelled for the passengers; please contact Naoero Airlines if you still want to travel.',
        ];
    }

    /**
     * Checks in a whole group (connecting flights and infants with their
     * adult are always checked in together, as the airline requires).
     *
     * @return array<string, mixed>
     */
    public function doCheckIn(object $customer, string $pnr, int $groupId): array
    {
        [, $summary] = $this->liveBooking($customer, $pnr);
        if ($summary['status'] !== 'ticketed') {
            throw new ApiException(409, 'checkin_unavailable', 'Check-in opens once your tickets are issued.');
        }
        $res = $this->prepareCheckin($customer, $pnr);
        $group = self::checkinGroups($res)[$groupId] ?? null;
        $target = Js::list($res['CheckinTargets'] ?? null)[$groupId] ?? null;
        if (! $group || ! $target) {
            throw new ApiException(409, 'checkin_unavailable', 'These flights are no longer open for check-in. Please refresh.');
        }
        if (! $group['canCheckIn']) {
            $why = $group['passengers'][0]['reasons'][0] ?? 'Online check-in is not available for these flights. Please check in at the airport.';
            throw new ApiException(409, 'checkin_unavailable', $why);
        }
        $seats = array_values(array_filter(Js::list($res['SpecialServices'] ?? null), fn ($s) => ($s['Code'] ?? null) === 'SEAT'));
        if ($seats) {
            throw new ApiException(409, 'seat_required', 'Choose a seat for each passenger in Seats & Extras, then check in.');
        }
        $targets = [];
        foreach (Js::list($target['ETTicketCheckinList'] ?? null) as $tc) {
            $targets[] = ['DocumentNumber' => $tc['DocumentNumber'], 'CouponOrders' => array_map(fn ($c) => (int) $c['CouponOrder'], Js::list($tc['ETCouponCheckinList'] ?? null))];
        }
        $out = $this->call('Checkin', [...$this->id($customer, $pnr), 'ETTicketTargets' => $targets], 'checkin_refused', 'Check-in was not completed:');
        $after = is_array($out['Booking'] ?? null) ? $out['Booking'] : $this->bookings->loadZenithBooking($pnr, $customer);
        $saved = $this->bookings->saveBooking($customer, $after);
        $this->log->info('booking', 'checked in', ['pnr' => $pnr, 'tickets' => count($targets)]);
        app(BookingNotices::class)->checkedIn($customer, $saved);

        return [
            'booking' => $saved,
            'boardingPasses' => self::boardingPasses($after),
            'message' => 'You are checked in. Your boarding passes are ready.',
        ];
    }
}
