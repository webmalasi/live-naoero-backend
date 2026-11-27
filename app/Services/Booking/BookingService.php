<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Auth\CustomerStore;
use App\Services\Flights\FlightConfigService;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\BookingNotices;
use App\Services\Reference\ReferenceService;
use App\Services\Support\Js;
use App\Services\Support\Phones;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Booking and payment, following the airline's functional mapping:
 *   PrepareFlights -> passenger form -> CreateBooking (PNR saved at once)
 *   LoadBooking -> CreateTicket (online FOP, in-app payment page)
 *   -> app reports accept / decline / cancel -> LoadBooking confirms tickets.
 *
 * Additional services (meals, assistance, seats, paid extras such as
 * baggage) are offered with the passenger form, sent with CreateBooking, and
 * can be changed later with PrepareBookingModification / ModifyBooking; see
 * BookingExtras.
 */
class BookingService
{
    private const DRAFT_MINUTES = 25;

    /** Where a booking came from, as customers read it. */
    public const SOURCES = [
        'app' => 'Booked with Naoero Airlines',
        'account' => 'Found on your Naoero Airlines account',
        'reference' => 'Added with your booking reference',
    ];

    /** Fake return addresses the app's payment WebView intercepts; never resolved. */
    public const PAYMENT_RETURN_BASE = 'https://payment-return.naoero.invalid';

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly FlightConfigService $flights,
        private readonly ReferenceService $reference,
        private readonly CustomerStore $customers,
        private readonly SavedTravellers $travellers,
        private readonly ServerLog $log,
    ) {}

    // ------------------------------------------------------------ errors

    private static function unavailable(): ApiException
    {
        return new ApiException(503, 'unavailable', 'Booking is temporarily unavailable. Please try again shortly.');
    }

    private static function offerChanged(): ApiException
    {
        return new ApiException(409, 'offer_changed', 'This fare is no longer available at this price. Please search again.');
    }

    private static function draftExpired(): ApiException
    {
        return new ApiException(410, 'draft_expired', 'Your selection has expired. Please search again to see current prices.');
    }

    private static function notFound(): ApiException
    {
        return new ApiException(404, 'booking_not_found', "We couldn't find that booking on your account.");
    }

    private static function expired(): ApiException
    {
        return new ApiException(409, 'booking_expired', 'This booking has expired and its seats were released. Please book again.');
    }

    private static function cannotModify(): ApiException
    {
        return new ApiException(409, 'not_modifiable', 'This booking can no longer be changed online.');
    }

    /**
     * The airline's ancillary codes (labels, colours, refund rules), for
     * naming paid seats and extras. Never fails a booking.
     *
     * @return array<string, array{label: string, color: ?string, refundable: ?bool, exchangeable: ?bool}>
     */
    private function ancillaryCodes(): array
    {
        try {
            $codes = $this->flights->valueCodes('AncillaryCode');
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach (Js::list($codes['Codes'] ?? null) as $c) {
            $props = [];
            foreach (Js::list($c['ValueCodeProperties'] ?? null) as $p) {
                $props[$p['Name'] ?? ''] = $p['StringValue'] ?? $p['BooleanValue'] ?? $p['NumberValue'] ?? null;
            }
            $bool = fn ($v) => $v === null ? null : filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $out[(string) $c['Code']] = [
                'label' => trim((string) ($c['Label'] ?? $c['Code'])),
                'color' => is_string($props['Color'] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $props['Color']) ? $props['Color'] : null,
                'refundable' => $bool($props['IsRefundable'] ?? null),
                'exchangeable' => $bool($props['IsExchangeable'] ?? null),
            ];
        }

        return $out;
    }

    private function zenithFailure(Throwable $e): never
    {
        if ($e instanceof ApiException) {
            throw $e;
        }
        if ($e instanceof ZenithBusinessException && preg_match('/quota/i', $e->zenithCode)) {
            throw new ApiException(429, 'busy', 'The airline system is busy. Please try again in a minute.');
        }
        if ($e instanceof ZenithUnavailableException || $e instanceof ZenithBusinessException) {
            throw self::unavailable();
        }
        throw $e;
    }

    /**
     * The customer's unpaid booking on one of the flights being booked, so a
     * duplicate refusal can name it. Null when none matches: the duplicate
     * may be on another account, or with the airline directly.
     */
    private function pendingBookingOnSameFlight(object $customer, array $prepare): ?string
    {
        $wanted = self::flightKeys($prepare);
        if (! $wanted) {
            return null;
        }
        $rows = DB::table('bookings')->where('customer_id', $customer->id)->where('status', 'pending_payment')
            ->where('time_limit', '>', Js::dbTime())->orderByDesc('created_at')->get(['pnr', 'zenith_booking']);
        foreach ($rows as $row) {
            $booking = Js::decode($row->zenith_booking);
            if (is_array($booking) && array_intersect($wanted, self::flightKeys($booking))) {
                return $row->pnr;
            }
        }

        return null;
    }

    /**
     * "ON42|2026-11-09T06:20:00" for each flight in a PrepareFlights response
     * or a booking.
     *
     * @return list<string>
     */
    private static function flightKeys(array $source): array
    {
        $segments = Js::list($source['Segments'] ?? null) ?: Js::list($source['Booking']['Segments'] ?? null);
        $keys = [];
        foreach ($segments as $s) {
            $number = trim(($s['AirlineDesignator'] ?? $s['FlightInfo']['OperatingAirlineDesignator'] ?? '').($s['FlightInfo']['FlightNumber'] ?? ''));
            $departure = (string) ($s['FlightInfo']['DepartureDate'] ?? '');
            if ($number !== '' && $departure !== '') {
                $keys[] = "{$number}|{$departure}";
            }
        }

        return $keys;
    }

    /** @return array{titles: array, documentTypes: array, genders: array, passengerTypes: array} */
    public function referenceData(): array
    {
        return [
            'titles' => $this->reference->titles(),
            'documentTypes' => $this->reference->documentTypes(),
            'genders' => $this->reference->genders(),
            'passengerTypes' => $this->flights->config()['passengerTypes'],
        ];
    }

    // ------------------------------------------------------------ prepare

    /** @return array<string, mixed> */
    public function prepare(object $customer, string $offerRef, string $itineraryRef): array
    {
        try {
            // Every optional service: meals, assistance, seats, paid extras.
            $prep = $this->zenith->call('PrepareFlights', ['Offer' => ['Ref' => $offerRef, 'RefItinerary' => $itineraryRef], 'IncludeAllOptionalSpecialServices' => true]);
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException && ! preg_match('/quota/i', $e->zenithCode)) {
                $this->log->warn('booking', 'PrepareFlights refused', ['code' => $e->zenithCode, 'message' => $e->getMessage()]);
                throw self::offerChanged();
            }
            $this->zenithFailure($e);
        }
        if (! Js::list($prep['Passengers'] ?? null)) {
            throw self::offerChanged();
        }

        try {
            $ref = $this->referenceData();
        } catch (Throwable) {
            throw self::unavailable();
        }
        $form = BookingForm::build($prep, $ref);
        $fare = is_array($prep['FareInfo'] ?? null) ? $prep['FareInfo'] : [];
        $total = $fare['SaleCurrencyAmountTotal'] ?? [];
        $perPassenger = [];
        foreach (Js::list($fare['ETTicketFares'] ?? null) as $f) {
            if (! empty($f['RefPassenger'])) {
                $perPassenger[] = ['ref' => $f['RefPassenger'], 'total' => 0 + ($f['SaleCurrencyAmount']['TotalAmount'] ?? 0)];
            }
        }
        $price = [
            'currency' => $fare['SaleCurrencyCode'] ?? '',
            'total' => 0 + ($total['TotalAmount'] ?? 0),
            'base' => 0 + ($total['BaseAmount'] ?? 0),
            'tax' => 0 + ($total['TaxAmount'] ?? 0),
            'discount' => 0 + ($total['DiscountAmount'] ?? 0),
            'perPassenger' => $perPassenger,
        ];
        $flights = BookingSummary::summarize(['Segments' => $prep['Segments'] ?? null])['flights'];
        $prefill = $this->profilePrefill($customer, $form);
        try {
            $saved = $this->travellers->list($customer->id);
        } catch (Throwable) {
            $saved = [];
        }

        $expiresAt = CarbonImmutable::now()->addMinutes(self::DRAFT_MINUTES);
        $draftId = (string) Str::uuid();
        DB::table('booking_drafts')->insert([
            'id' => $draftId,
            'customer_id' => $customer->id,
            'offer_ref' => $offerRef,
            'itinerary_ref' => $itineraryRef,
            'prepare' => Js::encode($prep),
            'form' => Js::encode($form),
            'expires_at' => Js::dbTime($expiresAt),
            'created_at' => Js::dbTime(),
        ]);

        return [
            'draftId' => $draftId,
            'expiresAt' => Js::iso($expiresAt),
            'form' => $form,
            'price' => $price,
            'flights' => $flights,
            'prefill' => Js::map($prefill),
            'savedTravellers' => SavedTravellers::eligibility($saved, $form, $ref),
            'extras' => BookingExtras::offer($prep, [], $this->ancillaryCodes()),
        ];
    }

    /** The lead adult is usually the account holder: fill what we know. */
    private function profilePrefill(object $customer, array $form): array
    {
        $lead = null;
        foreach ($form['passengers'] as $p) {
            if (in_array('title', array_column($p['fields'], 'key'), true)) {
                $lead = $p;
                break;
            }
        }
        if (! $lead) {
            return [];
        }
        try {
            $profile = $this->customers->profile($customer->id);
        } catch (Throwable) {
            $profile = null;
        }
        if (! $profile) {
            return [];
        }
        $passport = $profile['passport'] ?? null;
        $known = [
            'title' => $profile['title'],
            'firstName' => $profile['firstName'],
            'lastName' => $profile['lastName'],
            'dateOfBirth' => $profile['birthDate'],
            'email' => $profile['email'],
            'mobilePhone' => $profile['phone'],
            'docType' => ! empty($passport['number']) ? ($passport['documentType'] ?? 'PP') : null,
            'docNumber' => $passport['number'] ?? null,
            'nationality' => $passport['nationality'] ?? null,
            'docIssueCountry' => $passport['issuingCountry'] ?? null,
            'docIssueDate' => $passport['issueDate'] ?? null,
            'docExpiry' => $passport['expiryDate'] ?? null,
        ];
        $values = [];
        foreach ($lead['fields'] as $f) {
            $v = $known[$f['key']] ?? null;
            if (! $v) {
                continue;
            }
            if (isset($f['options']) && ! in_array($v, array_column($f['options'], 'code'), true)) {
                continue;
            }
            $values[$f['key']] = $v;
        }

        return $values ? [$lead['ref'] => $values] : [];
    }

    // ------------------------------------------------------------ create

    /** @return list<array{passengerRef: ?string, field: ?string, message: string}> */
    public static function problemsFromInvalidData(array $data, array $form): array
    {
        $out = [];
        foreach (Js::list($data['Global'] ?? null) as $g) {
            if (! empty($g['Message'])) {
                $out[] = ['passengerRef' => null, 'field' => null, 'message' => $g['Message']];
            }
        }
        foreach (Js::list($data['Passengers'] ?? null) as $p) {
            foreach (Js::list($p['Messages'] ?? null) as $m) {
                $field = match (true) {
                    (bool) preg_match('/firstname/i', $m) => 'firstName',
                    (bool) preg_match('/surname/i', $m) => 'lastName',
                    (bool) preg_match('/civility|title/i', $m) => 'title',
                    (bool) preg_match('/gender/i', $m) => 'gender',
                    default => null,
                };
                $out[] = ['passengerRef' => $p['Data']['Ref'] ?? null, 'field' => $field, 'message' => preg_replace('/^Passengers\[\d+\]\./', '', $m)];
            }
        }
        foreach (Js::list($data['SpecialServices'] ?? null) as $s) {
            $ref = $s['Data']['RefPassenger'] ?? null;
            $pf = null;
            foreach ($form['passengers'] as $p) {
                if ($p['ref'] === $ref) {
                    $pf = $p;
                    break;
                }
            }
            foreach (Js::list($s['Messages'] ?? null) as $m) {
                $out[] = [
                    'passengerRef' => $ref,
                    'field' => is_array($s['Data'] ?? null) && isset($s['Data']['Code']) ? BookingForm::fieldForSsr($s['Data']['Code'], $pf, $m) : null,
                    'message' => $m,
                ];
            }
        }
        foreach (Js::list($data['EMDTicketFares'] ?? null) as $e) {
            foreach (Js::list($e['Messages'] ?? null) as $m) {
                $out[] = ['passengerRef' => null, 'field' => null, 'message' => $m];
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function loadZenithBooking(string $pnr, object $customer, ?array $verification = null): array
    {
        try {
            $res = $this->zenith->call('LoadBooking', [
                'UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $pnr],
                'Verification' => $verification ?? $this->verification($customer, $pnr),
            ]);
            if (! is_array($res['Booking'] ?? null)) {
                throw self::notFound();
            }

            return $res['Booking'];
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException && preg_match('/not ?found|unknown|invalid/i', "{$e->zenithCode} {$e->getMessage()}")) {
                throw self::notFound();
            }
            $this->zenithFailure($e);
        }
    }

    /**
     * Stores the booking against its customer; never moves it to another
     * customer. $link sets where it came from (source, verify_surname,
     * linked_at, hidden_at) for bookings made elsewhere.
     */
    public function saveBooking(object $customer, array $b, array $link = []): array
    {
        $summary = BookingSummary::summarize($b);
        $now = Js::dbTime();
        $fields = [
            'status' => $summary['status'],
            'currency' => $summary['currency'] !== '' ? $summary['currency'] : DB::table('bookings')
                ->where('zenith_environment', $customer->zenith_environment)->where('pnr', $summary['pnr'])->value('currency'),
            'total_amount' => $summary['totalAmount'],
            'amount_due' => $summary['amountDue'],
            'time_limit' => $summary['timeLimit'] ? Js::dbTime(CarbonImmutable::parse($summary['timeLimit'])) : null,
            'summary' => Js::encode($summary),
            'ticket_numbers' => Js::encode(array_column($summary['tickets'], 'number')),
            'ticket_print_url' => $summary['ticketPrintUrl'],
            'zenith_booking' => Js::encode($b),
            'synced_at' => $now,
            'updated_at' => $now,
            // Readable at a glance (pgAdmin, exports): who, where, when.
            ...BookingSummary::readableColumns($summary, DB::table('customers')->where('id', $customer->id)->first(['first_name', 'last_name', 'zenith_login', 'email'])),
            // Who to text about the trip.
            ...self::contactPhone($b),
        ];
        $existing = DB::table('bookings')
            ->where('zenith_environment', $customer->zenith_environment)
            ->where('pnr', $summary['pnr'])
            ->first(['id', 'customer_id', 'source', 'summary']);
        if ($existing) {
            if ($existing->customer_id === $customer->id) {
                DB::table('bookings')->where('id', $existing->id)->update([...$fields, ...$link]);
                // Tickets issued, reissued after a change, or the hold expired: tell the customer.
                app(BookingNotices::class)->transitions($customer, is_string($existing->summary) ? json_decode($existing->summary, true) : null, $summary);
            }
        } else {
            DB::table('bookings')->insert([
                'id' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'zenith_environment' => $customer->zenith_environment,
                'pnr' => $summary['pnr'],
                'created_at' => $now,
                ...$fields,
                ...$link,
            ]);
        }

        return self::withSource($summary, $link['source'] ?? $existing->source ?? 'app');
    }

    /**
     * @param  array{passengers: list<array{ref: string, values: array}>, booking?: array|null, saveTravellers?: list<string>|null}  $input
     * @return array<string, mixed>
     */
    public function create(object $customer, string $draftId, array $input): array
    {
        $draft = DB::table('booking_drafts')->where('id', $draftId)->where('customer_id', $customer->id)
            ->first(['prepare', 'form', 'offer_ref', 'itinerary_ref', 'expires_at', 'used_at']);
        if (! $draft || $draft->used_at || CarbonImmutable::parse($draft->expires_at)->lessThan(CarbonImmutable::now())) {
            throw self::draftExpired();
        }
        $prepare = Js::decode($draft->prepare);
        $form = Js::decode($draft->form);

        try {
            $ref = $this->referenceData();
            $countries = array_fill_keys(array_column($this->reference->countries(), 'code'), true);
        } catch (Throwable) {
            throw self::unavailable();
        }
        $problems = BookingForm::validate($form, $input, $ref, $countries);
        if ($problems) {
            throw new ApiException(422, 'invalid_passengers', 'Please check the highlighted details.', problems: $problems);
        }

        $extras = ['SpecialServices' => [], 'EMDTicketFares' => []];
        if (! empty($input['extras'])) {
            $offer = BookingExtras::offer($prepare, [], $this->ancillaryCodes());
            $wrong = BookingExtras::validate($offer, $input['extras']);
            if ($wrong) {
                throw new ApiException(422, 'invalid_extras', 'Please check your extras.', problems: $wrong);
            }
            $extras = BookingExtras::toZenith($prepare, $input['extras']);
        }

        $filled = BookingForm::apply($prepare, $form, $input);
        try {
            $request = [
                'Offer' => ['Ref' => $draft->offer_ref, 'RefItinerary' => $draft->itinerary_ref],
                'Passengers' => $filled['Passengers'],
                'SpecialServices' => [...$filled['SpecialServices'], ...$extras['SpecialServices']],
                'RefCustomer' => $customer->zenith_customer_ref,
            ];
            if ($extras['EMDTicketFares']) {
                $request['FareInfo'] = ['EMDTicketFares' => $extras['EMDTicketFares']];
            }
            $res = $this->zenith->call('CreateBooking', $request);
            if (empty($res['Booking']['PnrInformation']['PnrCode'])) {
                throw self::unavailable();
            }
            $booking = $res['Booking'];
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException) {
                $invalid = $e->body['InvalidData'] ?? null;
                if (is_array($invalid)) {
                    $mapped = self::problemsFromInvalidData($invalid, $form);
                    throw new ApiException(422, 'invalid_passengers', 'The airline needs some details corrected.',
                        problems: $mapped ?: [['passengerRef' => null, 'field' => null, 'message' => $e->getMessage()]]);
                }
                // Zenith refuses a second booking for the same passenger on a
                // flight: DupePassengerFlight ("These passengers are already
                // booked on a flight."; older versions said "already registered").
                if ($e->zenithCode === 'DupePassengerFlight' || preg_match('/already (registered|booked)/i', $e->getMessage())) {
                    $pending = $this->pendingBookingOnSameFlight($customer, $prepare);
                    throw new ApiException(
                        409,
                        'passenger_already_booked',
                        $pending
                            ? "A passenger is already booked on this flight in booking {$pending}. Pay for that booking from My Trips, or wait for it to expire."
                            : 'A passenger is already booked on this flight. Check your existing bookings or contact Naoero Airlines.',
                        extra: $pending ? ['pendingPnr' => $pending] : [],
                    );
                }
                if (preg_match('/offer|expired|availab|fare|price/i', "{$e->zenithCode} {$e->getMessage()}")) {
                    throw self::offerChanged();
                }
            }
            $this->zenithFailure($e);
        }

        // Partner airlines must confirm before tickets can be issued (up to 1 min).
        $pnr = (string) $booking['PnrInformation']['PnrCode'];
        if (! empty($booking['PnrInformation']['PartnerInformation']['HasPartners'])) {
            $deadline = microtime(true) + 60;
            while (! empty($booking['PnrInformation']['MissingTravelerRecordLocator']) && microtime(true) < $deadline) {
                sleep(10);
                try {
                    $booking = $this->loadZenithBooking($pnr, $customer);
                } catch (Throwable) {
                    // keep the last answer
                }
            }
        }

        $summary = $this->saveBooking($customer, $booking);
        DB::table('booking_drafts')->where('id', $draftId)->update(['used_at' => Js::dbTime()]);
        app(BookingNotices::class)->created($customer, $summary);

        // "Save this passenger": as soon as the airline has accepted the booking.
        foreach (array_unique($input['saveTravellers'] ?? []) as $passengerRef) {
            $pf = null;
            foreach ($form['passengers'] as $p) {
                if ($p['ref'] === $passengerRef) {
                    $pf = $p;
                    break;
                }
            }
            $values = null;
            foreach ($input['passengers'] as $p) {
                if ($p['ref'] === $passengerRef) {
                    $values = $p['values'];
                }
            }
            if (! $pf || $values === null) {
                continue;
            }
            try {
                $this->travellers->save($customer->id, $pf['type'], $values);
            } catch (Throwable $e) {
                $this->log->warn('booking', 'could not save traveller', ['error' => $e->getMessage()]);
            }
        }
        if (! empty($booking['PnrInformation']['MissingTravelerRecordLocator'])) {
            throw new ApiException(409, 'partner_not_confirmed', 'A partner airline has not confirmed this booking yet. Please try paying again in a few minutes.', extra: ['pnr' => $pnr]);
        }

        return $summary;
    }

    // ------------------------------------------------------------ extras after booking

    /** PrepareBookingModification: what can still be added to the booking. */
    public function prepareModification(object $customer, string $pnr): array
    {
        try {
            $res = $this->zenith->call('PrepareBookingModification', [
                'UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $pnr],
                'Verification' => $this->verification($customer, $pnr),
                'IncludeAllOptionalSpecialServices' => true,
                'DeferredIssuance' => true,
            ]);
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException && preg_match('/not ?found|unknown/i', "{$e->zenithCode} {$e->getMessage()}")) {
                throw self::notFound();
            }
            if ($e instanceof ZenithBusinessException && ! preg_match('/quota/i', $e->zenithCode)) {
                $this->log->warn('booking', 'PrepareBookingModification refused', ['pnr' => $pnr, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);
                throw self::cannotModify();
            }
            $this->zenithFailure($e);
        }
        if (! is_array($res['Booking'] ?? null)) {
            throw self::notFound();
        }

        return $res;
    }

    /** @return array<string, string> passenger ref => full name */
    public static function passengerNames(array $booking): array
    {
        $names = [];
        foreach (Js::list($booking['Passengers'] ?? null) as $p) {
            $names[(string) $p['Ref']] = trim(($p['NameElement']['Firstname'] ?? '').' '.($p['NameElement']['Surname'] ?? ''));
        }

        return $names;
    }

    /**
     * The extras page of a booking: what it holds now and what can be added.
     *
     * @return array{booking: array, current: array, offer: array}
     */
    public function extras(object $customer, string $pnr): array
    {
        $this->ownedBooking($customer, $pnr);
        $code = strtoupper($pnr);
        $res = $this->prepareModification($customer, $code);
        $summary = $this->saveBooking($customer, $res['Booking']);
        if (in_array($summary['status'], ['cancelled', 'expired'], true)) {
            throw self::cannotModify();
        }

        return [
            'booking' => $summary,
            'current' => BookingExtras::current($res['Booking']),
            'offer' => BookingExtras::offer($res, self::passengerNames($res['Booking']), $this->ancillaryCodes()),
        ];
    }

    /**
     * Adds and removes special services, seats and paid extras on a booking
     * (Zenith ModifyBooking). New paid extras are added to the amount due and
     * paid like the booking itself.
     *
     * @param  array{add?: array, remove?: array{services?: list<array>, seats?: list<array>, ancillaries?: list<string>}}  $input
     * @return array{booking: array, current: array}
     */
    public function modifyExtras(object $customer, string $pnr, array $input): array
    {
        $this->ownedBooking($customer, $pnr);
        $code = strtoupper($pnr);
        $res = $this->prepareModification($customer, $code);
        $booking = $res['Booking'];
        $status = BookingSummary::status($booking);
        if (in_array($status, ['cancelled', 'expired'], true)) {
            throw self::cannotModify();
        }
        $current = BookingExtras::current($booking);
        $offer = BookingExtras::offer($res, self::passengerNames($booking), $this->ancillaryCodes());
        $add = is_array($input['add'] ?? null) ? $input['add'] : [];
        $remove = is_array($input['remove'] ?? null) ? $input['remove'] : [];

        // Seat restrictions consider the services the passenger already holds.
        $held = [];
        foreach ($current['services'] as $s) {
            if ($s['passengerRef'] !== null) {
                $held[$s['passengerRef']][] = $s['code'];
            }
        }
        $problems = BookingExtras::validate($offer, $add, $held);

        $removedSsrs = [];
        foreach ($remove['services'] ?? [] as $r) {
            $match = collect($current['services'])->first(fn ($s) => $s['code'] === ($r['code'] ?? null)
                && $s['passengerRef'] === ($r['passengerRef'] ?? null) && $s['segmentRef'] === ($r['segmentRef'] ?? null));
            if (! $match) {
                $problems[] = ['passengerRef' => $r['passengerRef'] ?? null, 'segmentRef' => $r['segmentRef'] ?? null, 'field' => 'extras', 'message' => 'That service is not on this booking.'];

                continue;
            }
            $removedSsrs[] = array_filter(['Code' => $match['code'], 'RefPassenger' => $match['passengerRef'], 'RefSegment' => $match['segmentRef']], fn ($v) => $v !== null);
        }
        foreach ($remove['seats'] ?? [] as $r) {
            $match = collect($current['seats'])->first(fn ($s) => $s['passengerRef'] === ($r['passengerRef'] ?? null) && $s['segmentRef'] === ($r['segmentRef'] ?? null));
            if (! $match) {
                $problems[] = ['passengerRef' => $r['passengerRef'] ?? null, 'segmentRef' => $r['segmentRef'] ?? null, 'field' => 'extras', 'message' => 'No seat is held for that passenger on that flight.'];

                continue;
            }
            // Choosing a new seat for the same flight replaces the old one.
            if (! collect($add['seats'] ?? [])->contains(fn ($s) => ($s['passengerRef'] ?? null) === $match['passengerRef'] && ($s['segmentRef'] ?? null) === $match['segmentRef'])) {
                $removedSsrs[] = ['Code' => 'SEAT', 'RefPassenger' => $match['passengerRef'], 'RefSegment' => $match['segmentRef']];
            }
        }
        $removedEmds = [];
        foreach ($remove['ancillaries'] ?? [] as $ref) {
            $match = collect($current['ancillaries'])->firstWhere('ref', $ref);
            if (! $match) {
                $problems[] = ['passengerRef' => null, 'segmentRef' => null, 'field' => 'extras', 'message' => 'That extra is not on this booking.'];
            } elseif (! $match['removable']) {
                $problems[] = ['passengerRef' => $match['passengerRef'], 'segmentRef' => $match['segmentRef'], 'field' => 'extras', 'message' => "{$match['label']} is already paid and cannot be removed online. Please contact Naoero Airlines."];
            } else {
                $removedEmds[] = $ref;
            }
        }
        if ($problems) {
            throw new ApiException(422, 'invalid_extras', 'Please check your extras.', problems: $problems);
        }
        $entries = BookingExtras::toZenith($res, $add);
        if (! $entries['SpecialServices'] && ! $entries['EMDTicketFares'] && ! $removedSsrs && ! $removedEmds) {
            throw ApiException::invalid('Nothing to change.');
        }

        $request = [
            'UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $code],
            'Verification' => $this->verification($customer, $code),
            'DeferredIssuance' => true,
            // Only the extras change: other passengers' data is not revalidated.
            'IgnorePassengerRefValidations' => array_map(fn ($p) => (string) $p['Ref'], Js::list($booking['Passengers'] ?? null)),
        ];
        foreach (['SpecialServices' => $entries['SpecialServices'], 'RemovedSpecialServices' => $removedSsrs, 'EMDTicketFares' => $entries['EMDTicketFares'], 'RemovedEMDTicketFares' => $removedEmds] as $key => $value) {
            if ($value) {
                $request[$key] = $value;
            }
        }
        try {
            $out = $this->zenith->call('ModifyBooking', $request);
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException) {
                $invalid = $e->body['InvalidData'] ?? null;
                if (is_array($invalid)) {
                    $mapped = array_map(fn ($p) => [...$p, 'segmentRef' => null, 'field' => 'extras'], self::problemsFromInvalidData($invalid, ['passengers' => []]));
                    throw new ApiException(422, 'invalid_extras', 'The airline could not accept these changes.',
                        problems: $mapped ?: [['passengerRef' => null, 'segmentRef' => null, 'field' => 'extras', 'message' => $e->getMessage()]]);
                }
                if (! preg_match('/quota/i', $e->zenithCode)) {
                    $this->log->warn('booking', 'ModifyBooking refused', ['pnr' => $code, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);
                    throw new ApiException(409, 'modification_refused', 'The airline could not make this change: '.$e->getMessage());
                }
            }
            $this->zenithFailure($e);
        }
        if (is_array($out['InvalidData'] ?? null) && array_filter($out['InvalidData'])) {
            $mapped = array_map(fn ($p) => [...$p, 'segmentRef' => null, 'field' => 'extras'], self::problemsFromInvalidData($out['InvalidData'], ['passengers' => []]));
            if ($mapped) {
                throw new ApiException(422, 'invalid_extras', 'The airline could not accept these changes.', problems: $mapped);
            }
        }

        // Always the airline's own view of the result.
        $after = $this->loadZenithBooking($code, $customer);
        $summary = $this->saveBooking($customer, $after);
        app(BookingNotices::class)->extrasAdded($customer, $summary);
        $this->log->info('booking', 'extras changed', [
            'pnr' => $code,
            'added' => count($entries['SpecialServices']) + count($entries['EMDTicketFares']),
            'removed' => count($removedSsrs) + count($removedEmds),
        ]);

        return ['booking' => $summary, 'current' => BookingExtras::current($after)];
    }

    // ------------------------------------------------------------ read

    public function ownedBooking(object $customer, string $pnr): object
    {
        return DB::table('bookings')
            ->where('customer_id', $customer->id)
            ->where('zenith_environment', $customer->zenith_environment)
            ->where('pnr', strtoupper($pnr))
            ->whereNull('hidden_at')
            ->first(['id', 'source', 'verify_surname']) ?? throw self::notFound();
    }

    /**
     * How the airline verifies access to the booking: the customer reference
     * for bookings on the customer's account, a passenger surname for
     * bookings added with their reference (Zenith requires the name for
     * every later call on those).
     */
    public function verification(object $customer, string $pnr): array
    {
        $surname = DB::table('bookings')
            ->where('customer_id', $customer->id)
            ->where('zenith_environment', $customer->zenith_environment)
            ->where('pnr', strtoupper($pnr))
            ->value('verify_surname');

        return $surname ? ['PassengerName' => $surname] : ['RefCustomer' => $customer->zenith_customer_ref];
    }

    /**
     * The booking's contact mobile from Zenith's passenger data: the first
     * CTCM (mobile), else the first CTCH (home), in E.164 with its country.
     *
     * @return array{contact_phone: ?string, contact_phone_country: ?string}
     */
    public static function contactPhone(array $b): array
    {
        $ssrs = Js::list($b['SpecialServices'] ?? null);
        foreach (['CTCM', 'CTCH'] as $code) {
            foreach ($ssrs as $s) {
                if (($s['Code'] ?? null) === $code && ($parsed = Phones::parse(Js::text($s['Text'] ?? null)))) {
                    return ['contact_phone' => $parsed['e164'], 'contact_phone_country' => $parsed['country']];
                }
            }
        }

        return ['contact_phone' => null, 'contact_phone_country' => null];
    }

    /** Adds where the booking came from to what the app receives. */
    public static function withSource(array $summary, ?string $source): array
    {
        $source = $source ?: 'app';

        return [...$summary, 'source' => $source, 'sourceLabel' => self::SOURCES[$source] ?? self::SOURCES['app']];
    }

    public function refresh(object $customer, string $pnr): array
    {
        $this->ownedBooking($customer, $pnr);

        return $this->saveBooking($customer, $this->loadZenithBooking(strtoupper($pnr), $customer));
    }

    /** @return list<array<string, mixed>> */
    public function list(object $customer): array
    {
        $now = CarbonImmutable::now();

        return DB::table('bookings')
            ->where('customer_id', $customer->id)
            ->where('zenith_environment', $customer->zenith_environment)
            ->whereNull('hidden_at')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['summary', 'status', 'time_limit', 'created_at', 'source', 'linked_at'])
            ->map(function ($r) use ($now) {
                $summary = Js::decode($r->summary);
                $summary = is_array($summary) ? $summary : [];
                // An unpaid booking past its time limit is released by the airline.
                $expired = $r->status === 'pending_payment' && $r->time_limit && CarbonImmutable::parse($r->time_limit)->lessThan($now);

                return [...self::withSource($summary, $r->source), 'status' => $expired ? 'expired' : $r->status, 'createdAt' => Js::iso($r->created_at), 'linkedAt' => Js::iso($r->linked_at)];
            })
            ->all();
    }

    // ------------------------------------------------------------ payment

    /**
     * The app gets return addresses on PAYMENT_RETURN_BASE, which it catches
     * itself. A website passes $returnUrl (checked by the caller): the
     * payment page then returns to our own address, which sends the browser
     * on to that page (see paymentReturnTarget()).
     *
     * @return array<string, mixed>
     */
    public function startPayment(object $customer, string $pnr, ?string $returnUrl = null, ?string $fop = null): array
    {
        $owned = $this->ownedBooking($customer, $pnr);
        $code = strtoupper($pnr);
        // Always the live amount: never a cached one.
        $b = $this->loadZenithBooking($code, $customer);
        $summary = $this->saveBooking($customer, $b);
        if ($summary['status'] === 'ticketed') {
            throw new ApiException(409, 'already_paid', 'This booking is already paid and ticketed.');
        }
        if ($summary['status'] !== 'pending_payment') {
            throw self::expired();
        }
        if (empty($b['MiscInfo']['ContainsIssuableTickets'])) {
            throw new ApiException(409, 'nothing_to_pay', 'There is nothing to pay on this booking.');
        }

        $amount = 0 + ($b['FareInfo']['SaleCurrencyTravelerAmountToPay']['TotalAmount'] ?? 0);
        // A service booking may not repeat its currency: the one saved with it.
        $currency = BookingSummary::currency($b) ?: (string) DB::table('bookings')->where('id', $owned->id)->value('currency');
        $paymentId = (string) Str::uuid();
        $returnUrlPrefix = $returnUrl === null
            ? self::PAYMENT_RETURN_BASE."/{$paymentId}/"
            : rtrim((string) config('app.url'), '/')."/api/v1/bookings/payments/{$paymentId}/return/";

        $fops = [];
        $fopCode = 'NONE';
        if ($amount > 0) {
            try {
                $sale = $this->flights->saleConfiguration();
            } catch (Throwable) {
                throw self::unavailable();
            }
            $forms = self::onlineForms($sale, $currency);
            if (! $forms) {
                throw new ApiException(503, 'payment_unavailable', "Online payment in {$currency} is not available right now. Please contact Naoero Airlines.");
            }
            // The customer's choice, else the airline's first (website payments and older apps).
            $online = $fop === null ? $forms[0] : collect($forms)->first(fn ($f) => self::fopCode($f) === $fop);
            if (! $online) {
                throw new ApiException(409, 'payment_option_unavailable', 'That way of paying is no longer available. Please choose another.');
            }
            $fopCode = (string) ($online['FopTemplate']['Code'] ?? $online['FormOfPaymentCode'] ?? 'ONLINE');
            $fops = [[
                ...$online['FopTemplate'],
                'Amount' => $amount,
                'RedirectInfo' => [
                    'AcceptUrl' => "{$returnUrlPrefix}accept",
                    'DeclineUrl' => "{$returnUrlPrefix}decline",
                    'CancelUrl' => "{$returnUrlPrefix}cancel",
                ],
            ]];
        }

        $now = Js::dbTime();
        DB::table('booking_payments')->insert([
            'id' => $paymentId, 'booking_id' => $owned->id, 'pnr' => $code, 'fop_code' => $fopCode, 'amount' => $amount,
            'currency' => $currency, 'status' => 'started', 'return_url' => $returnUrl, 'created_at' => $now, 'updated_at' => $now,
        ]);

        try {
            $res = $this->zenith->call('CreateTicket', [
                'UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $code],
                'Verification' => $this->verification($customer, $code),
                'Fops' => $fops,
            ]);
        } catch (Throwable $e) {
            $this->setPayment($paymentId, 'failed', mb_substr($e->getMessage(), 0, 300));
            $this->log->warn('booking', 'CreateTicket refused', ['pnr' => $code, 'paymentId' => $paymentId, 'amount' => $amount, 'message' => $e->getMessage()]);
            if ($e instanceof ZenithBusinessException && preg_match('/time ?limit|expired/i', $e->getMessage())) {
                throw self::expired();
            }
            $this->zenithFailure($e);
        }

        $redirectUrl = Js::text($res['RedirectResult']['UrlGet'] ?? null);
        $html = $redirectUrl ? null : self::mobileFriendly(Js::text($res['RedirectResult']['HtmlForm'] ?? null));
        $done = null;
        if (! $redirectUrl && ! $html) {
            // Nothing to redirect to: either issued directly (zero due) or a fault.
            $after = is_array($res['Booking'] ?? null) ? $res['Booking'] : $this->loadZenithBooking($code, $customer);
            $done = $this->saveBooking($customer, $after);
            if ($done['status'] !== 'ticketed') {
                $this->setPayment($paymentId, 'failed', 'no payment page returned');
                throw self::unavailable();
            }
            $this->setPayment($paymentId, 'confirmed');
        }
        // The airline's card form is served from our own HTTPS page, so the
        // app and the browser show our address (not about:blank) and the
        // client simply opens redirectUrl.
        $served = false;
        if ($html) {
            $secret = Str::random(40);
            DB::table('booking_payments')->where('id', $paymentId)->update([
                'form_html' => self::servedForm($html),
                'form_token_hash' => hash('sha256', $secret),
                'updated_at' => Js::dbTime(),
            ]);
            $redirectUrl = rtrim((string) config('app.url'), '/')."/api/v1/bookings/payments/{$paymentId}/form?t={$secret}";
            $html = null;
            $served = true;
        }
        $this->log->info('booking', $done ? 'issued with nothing to pay' : 'payment started', [
            'pnr' => $code, 'paymentId' => $paymentId, 'amount' => $amount, 'currency' => $currency, 'fop' => $fopCode,
            'flightChange' => ! empty($b['MiscInfo']['IsPendingExchange']), 'website' => $returnUrl !== null, 'formServed' => $served,
        ]);

        return [
            'paymentId' => $paymentId,
            'amount' => $amount,
            'currency' => $currency,
            'redirectUrl' => $redirectUrl,
            'html' => $html,
            'returnUrlPrefix' => $returnUrlPrefix,
            'booking' => $done,
        ];
    }

    /**
     * The ways this booking can be paid online, straight from the airline's
     * sale configuration: an option the airline adds shows up by itself, and
     * one it removes disappears (the configuration is kept for 20 minutes).
     *
     * @return array<string, mixed>
     */
    public function paymentOptions(object $customer, string $pnr): array
    {
        $this->ownedBooking($customer, $pnr);
        $b = $this->loadZenithBooking(strtoupper($pnr), $customer);
        $currency = (string) ($b['FareInfo']['SaleCurrencyCode'] ?? '');
        try {
            $sale = $this->flights->saleConfiguration();
        } catch (Throwable) {
            throw self::unavailable();
        }

        return [
            'currency' => $currency,
            'amount' => 0 + ($b['FareInfo']['SaleCurrencyTravelerAmountToPay']['TotalAmount'] ?? 0),
            'options' => array_map(fn ($f) => self::paymentOption($f), self::onlineForms($sale, $currency)),
        ];
    }

    /** Online forms of payment the airline accepts in [currency], in its order. */
    private static function onlineForms(array $sale, string $currency): array
    {
        return array_values(array_filter(Js::list($sale['AllowedFormsOfPayment'] ?? null), function ($f) use ($currency) {
            $currencies = Js::list($f['AllowedCurrencyCodes'] ?? null);

            return ! empty($f['OnlinePaymentInfo']) && is_array($f['FopTemplate'] ?? null) && self::fopCode($f) !== ''
                && (! $currencies || in_array($currency, $currencies, true));
        }));
    }

    private static function fopCode(array $form): string
    {
        return (string) ($form['FopTemplate']['Code'] ?? $form['FormOfPaymentCode'] ?? '');
    }

    /**
     * Friendly words for the options we know. Zenith only sends a code, so an
     * option we have not met yet gets a name made from it and still works.
     */
    private const PAYMENT_OPTION_WORDS = [
        'NAB' => ['name' => 'Credit or debit card', 'description' => 'Secure payments enabled by NAB', 'kind' => 'card'],
        'TTI_TEST' => ['name' => 'Test payment', 'description' => 'For testing: no card needed, choose the result on the next page', 'kind' => 'test'],
    ];

    private static function paymentOption(array $form): array
    {
        $code = self::fopCode($form);
        $known = self::PAYMENT_OPTION_WORDS[$code] ?? null;

        return [
            'code' => $code,
            'name' => $known['name'] ?? Str::headline(strtolower($code)),
            'description' => $known['description'] ?? 'Secure online payment',
            'kind' => $known['kind'] ?? (preg_match('/test/i', $code) ? 'test' : 'online'),
            'logoUrl' => Js::text($form['OnlinePaymentInfo']['LogoUrl'] ?? null),
        ];
    }

    /**
     * Makes the airline's payment form (Zenith's HTML, a fixed 700 px table
     * built for desktops) usable on a phone: a mobile viewport and a
     * stylesheet that stacks each label above a full-width field. Fields,
     * scripts and where the form posts are left exactly as they are.
     */
    public static function mobileFriendly(?string $html): ?string
    {
        if ($html === null || $html === '' || str_contains($html, 'naoero-mobile-payment')) {
            return $html;
        }
        $head = '<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<style id="naoero-mobile-payment">'.self::PAYMENT_FORM_CSS.'</style>';
        if (preg_match('~</head>~i', $html, $m, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, $head, $m[0][1], 0);
        }

        return $head.$html;
    }

    private const PAYMENT_FORM_CSS = <<<'CSS'
        body { margin: 0; background: #f3f6fb; color: #1b2a4a; font: 15px/1.45 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: 100%; }
        #main { width: auto !important; max-width: 720px; margin: 0 auto; padding: 16px 12px 28px; box-sizing: border-box; }
        #main > div, .title, .content, #form1, .usrOrderControlPanel, .usrCreditCardInfoControlPanel, .content h2, .CBtdValidation { width: auto !important; max-width: 100%; box-sizing: border-box; }
        table.ui-tableList th, table.ui-tableList td { width: auto !important; height: auto !important; vertical-align: middle; }
        .divCreditCardExpiryDateControl { float: none !important; width: 100% !important; max-width: 300px; }
        .divCreditCardExpiryDateControl > div { display: flex; align-items: center; gap: 8px; width: 100%; }
        .CBtdValidation { display: block !important; float: none !important; }
        .CBtdValidation ul.ui-forward { float: none !important; }
        .divCreditCardExpiryDateControl select { flex: 1 1 0; min-width: 0; }
        #main > div[align] img { max-width: 220px; height: auto; }
        .title { background: #012169 !important; color: #fff !important; border-radius: 12px 12px 0 0; padding: 12px 16px !important; font-weight: 700; }
        .content { background: #fff; border: 0 !important; border-radius: 0 0 16px 16px; box-shadow: 0 8px 24px rgba(1,33,105,.08); padding: 4px 16px 18px !important; }
        .content h2 { color: #012169 !important; font-size: 18px !important; margin: 18px 0 10px !important; }
        table.ui-tableList { width: 100% !important; border-collapse: collapse; border: 1px solid #e3e8f2 !important; }
        table.ui-tableList th, table.ui-tableList td { border: 1px solid #e3e8f2 !important; padding: 10px !important; font-size: 14px; }
        table.ui-tableList th { background: #f3f6fb !important; color: #012169 !important; }
        #UsrCreditCardInfoControl_fvCreditCardInfo { width: 100% !important; }
        input.CBinput, .divCreditCardExpiryDateControl select, input[name="EPS_CCV"] { box-sizing: border-box; height: 48px; font-size: 16px; padding: 0 12px; border: 1px solid #c9d3ec; border-radius: 10px; background: #fff; color: #1b2a4a; }
        input.CBinput:focus, .divCreditCardExpiryDateControl select:focus, input[name="EPS_CCV"]:focus { outline: none; border-color: #012169; box-shadow: 0 0 0 3px rgba(1,33,105,.15); }
        input.CBinput { width: 100%; max-width: 420px; }
        input.CBinput[name="EPS_CCV"] { width: 120px; }
        .CBtdValidation ul.ui-forward { list-style: none; margin: 18px 0 0; padding: 0; display: flex; gap: 10px; justify-content: flex-end; }
        .CBtdValidation ul.ui-forward li { margin: 0; padding: 0; }
        .CBtdValidation ul.ui-forward li:nth-child(2) { display: none; }
        .ui-state-error { display: block; margin-top: 4px; font-size: 13px; }
        #btValidate, #btCancel { height: 50px; padding: 0 22px; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; border: 0; }
        #btValidate { background: #012169 !important; color: #fff !important; }
        #btCancel { background: #fff !important; color: #012169 !important; border: 1px solid #c9d3ec !important; }
        @media (max-width: 640px) {
            #main { padding: 10px 10px 24px; }
            .usrCreditCardInfoControlPanel table, .usrCreditCardInfoControlPanel tbody, .usrCreditCardInfoControlPanel tr, .usrCreditCardInfoControlPanel td, .usrCreditCardInfoControlPanel th { display: block; width: 100% !important; box-sizing: border-box; }
            .usrCreditCardInfoControlPanel table.ui-tableList, .usrCreditCardInfoControlPanel table.ui-tableList td, .usrCreditCardInfoControlPanel table.ui-tableList th { border: 0 !important; }
            .usrCreditCardInfoControlPanel table.ui-tableList th { background: transparent !important; padding: 0 0 6px !important; font-size: 14px; }
            .usrCreditCardInfoControlPanel table.ui-tableList td { padding: 2px 0 !important; }
            .usrCreditCardInfoControlPanel table.ui-tableList tr { display: flex; flex-direction: column; }
            .usrCreditCardInfoControlPanel table.ui-tableList tr > td:first-child { padding-top: 14px !important; font-weight: 600; color: #012169; }
            .divCreditCardExpiryDateControl { max-width: none; }
            .usrCreditCardInfoControlPanel table.ui-tableList tr > td { line-height: 1.3; }
            input.CBinput { display: block; }
            .divPaymentLogos { display: flex; flex-wrap: wrap; gap: 4px; }
            .CBtdValidation ul.ui-forward li { width: 100% !important; float: none !important; display: block !important; }
            input.CBinput { max-width: none; }
            .CBtdValidation ul.ui-forward { flex-direction: column; }
            #btValidate, #btCancel { display: block; width: 100%; }
            table.ui-tableList th, table.ui-tableList td { font-size: 13px; }
        }
        CSS;

    /** Placeholders a website return address can use. */
    public const RETURN_PLACEHOLDERS = ['{pnr}', '{paymentId}', '{outcome}'];

    /**
     * Where the payment page's return address sends a browser: the website
     * page given when paying, with the booking reference, payment id and
     * where the page ended (accept / decline / cancel). The page holds
     * {pnr}, {paymentId} and {outcome}, or gets ?pnr=&paymentId=&outcome=
     * added. Null for an unknown payment or one started by the app.
     */
    public static function paymentReturnTarget(string $paymentId, string $outcome): ?string
    {
        if (! Str::isUuid($paymentId)) {
            return null;
        }
        $payment = DB::table('booking_payments')->where('id', $paymentId)->first(['pnr', 'return_url']);
        $url = $payment->return_url ?? null;
        if (! $url) {
            return null;
        }
        $values = [rawurlencode($payment->pnr), rawurlencode($paymentId), rawurlencode($outcome)];
        if (str_contains($url, '{')) {
            return str_replace(self::RETURN_PLACEHOLDERS, $values, $url);
        }
        $fragment = '';
        if (($hash = strpos($url, '#')) !== false) {
            [$url, $fragment] = [substr($url, 0, $hash), substr($url, $hash)];
        }
        $query = http_build_query(['pnr' => $payment->pnr, 'paymentId' => $paymentId, 'outcome' => $outcome]);

        return $url.(str_contains($url, '?') ? '&' : '?').$query.$fragment;
    }

    private function setPayment(string $paymentId, string $status, ?string $detail = null): void
    {
        $fields = ['status' => $status, 'updated_at' => Js::dbTime()];
        if ($status !== 'started') {
            // The payment has ended: its form is deleted. The link's hash stays,
            // so the real link can still be told apart from a guessed one.
            $fields['form_html'] = null;
        }
        if ($detail !== null) {
            $fields['detail'] = $detail;
        }
        DB::table('booking_payments')->where('id', $paymentId)->update($fields);
    }

    /** How long a payment's form page stays open after the payment starts. */
    public const FORM_MINUTES = 60;

    /**
     * The airline's form as served from our domain: a <base> at the airline
     * keeps any relative address (images, styles, the form target) pointing
     * at the airline, as it did when the form was rendered from memory.
     */
    public static function servedForm(string $html): string
    {
        if (preg_match('~<base\s~i', $html)
            || ! preg_match('~<form[^>]+action="(https://[^/"]+)/~i', $html, $m)) {
            return $html;
        }
        $base = '<base href="'.htmlspecialchars($m[1], ENT_QUOTES).'/">';
        if (preg_match('~<head[^>]*>~i', $html, $h, PREG_OFFSET_CAPTURE)) {
            return substr_replace($html, $base, $h[0][1] + strlen($h[0][0]), 0);
        }

        return $base.$html;
    }

    /**
     * The form page for GET /api/v1/bookings/payments/{id}/form. The link's
     * secret is checked against its stored hash; the page only opens while
     * the payment is still running and for FORM_MINUTES after it started.
     *
     * @return array{status: int, html: string}
     */
    public function paymentForm(string $paymentId, string $secret): array
    {
        $row = Str::isUuid($paymentId)
            ? DB::table('booking_payments')->where('id', $paymentId)->first(['pnr', 'status', 'form_html', 'form_token_hash', 'created_at'])
            : null;
        $valid = $row && $row->form_token_hash && $secret !== '' && hash_equals($row->form_token_hash, hash('sha256', $secret));
        if (! $valid) {
            // Unknown payment or wrong secret: the same answer, telling nothing.
            $this->log->warn('booking', 'payment form refused', ['paymentId' => Str::isUuid($paymentId) ? $paymentId : null, 'reason' => $row ? 'wrong link' : 'unknown payment']);

            return ['status' => 404, 'html' => self::formMessage('This payment link is not valid', 'Return to the app or website and start the payment from your booking.')];
        }
        if ($row->status !== 'started' || ! $row->form_html) {
            return ['status' => 410, 'html' => self::formMessage(
                $row->status === 'confirmed' ? 'This booking is paid' : 'This payment page has closed',
                $row->status === 'confirmed'
                    ? 'Your tickets have been issued. Return to the app or website to see your booking.'
                    : 'Return to the app or website and start the payment again from your booking.',
            )];
        }
        if (CarbonImmutable::parse($row->created_at)->diffInMinutes(CarbonImmutable::now()) > self::FORM_MINUTES) {
            return ['status' => 410, 'html' => self::formMessage('This payment page has expired', 'For your security it is only open for '.self::FORM_MINUTES.' minutes. Return to the app or website and start the payment again from your booking.')];
        }
        $this->log->info('booking', 'payment form opened', ['pnr' => $row->pnr, 'paymentId' => $paymentId]);

        return ['status' => 200, 'html' => $row->form_html];
    }

    /** A small branded page for a form link that can't be used. */
    private static function formMessage(string $title, string $text): string
    {
        $e = fn ($s) => htmlspecialchars($s, ENT_QUOTES);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<meta name="robots" content="noindex"><title>'.$e($title).' · Naoero Airlines</title><style>'
            .'body{margin:0;background:#f3f6fb;color:#1b2a4a;font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
            .'.box{max-width:460px;margin:12vh auto 0;background:#fff;border-radius:18px;box-shadow:0 10px 30px rgba(1,33,105,.10);padding:28px 24px;text-align:center}'
            .'h1{color:#012169;font-size:20px;margin:0 0 10px}p{color:#6b7690;margin:0}'
            .'.mark{width:54px;height:54px;border-radius:50%;background:#eef2fb;color:#012169;display:grid;place-items:center;margin:0 auto 16px;font-size:26px;font-weight:800}'
            .'</style></head><body><div class="box"><div class="mark">!</div><h1>'.$e($title).'</h1><p>'.$e($text).'</p></div></body></html>';
    }

    /**
     * The app's word is never proof of payment: Zenith decides whether
     * tickets exist.
     *
     * @return array{booking: array, payment: array{status: string}}
     */
    public function finishPayment(object $customer, string $pnr, string $paymentId, string $outcome): array
    {
        $owned = $this->ownedBooking($customer, $pnr);
        $current = DB::table('booking_payments')->where('id', $paymentId)->where('booking_id', $owned->id)->value('status');
        if ($current === null) {
            throw self::notFound();
        }
        $reported = ['accept' => 'accepted', 'decline' => 'declined', 'cancel' => 'cancelled', 'closed' => 'closed'][$outcome];
        if ($current === 'started') {
            $this->setPayment($paymentId, $reported);
        }

        $code = strtoupper($pnr);
        $b = $this->loadZenithBooking($code, $customer);
        if ($outcome === 'accept' || $outcome === 'closed') {
            $deadline = microtime(true) + ($outcome === 'accept' ? 30 : 8);
            while (BookingSummary::status($b) === 'pending_payment' && microtime(true) < $deadline) {
                sleep(3);
                $b = $this->loadZenithBooking($code, $customer);
            }
        }
        $booking = $this->saveBooking($customer, $b);
        $status = $booking['status'] === 'ticketed' ? 'confirmed' : $reported;
        DB::table('booking_payments')->where('id', $paymentId)->where('status', '<>', $status)
            ->update(['status' => $status, 'updated_at' => Js::dbTime()]);
        // Whatever the outcome, this payment's form is deleted now.
        DB::table('booking_payments')->where('id', $paymentId)->whereNotNull('form_html')->update(['form_html' => null]);

        $this->log->info('booking', 'payment finished', ['pnr' => $code, 'paymentId' => $paymentId, 'page' => $outcome, 'status' => $status, 'booking' => $booking['status']]);
        if ($status === 'declined') {
            app(BookingNotices::class)->paymentFailed($customer, $booking, $paymentId);
        }

        return ['booking' => $booking, 'payment' => ['status' => $status]];
    }

    public function resendConfirmation(object $customer, string $pnr): void
    {
        $this->ownedBooking($customer, $pnr);
        try {
            $this->zenith->call('SendTicketConfirmation', [
                'UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => strtoupper($pnr)],
                'Verification' => $this->verification($customer, $pnr),
            ]);
        } catch (Throwable $e) {
            if ($e instanceof ZenithBusinessException) {
                throw new ApiException(409, 'resend_not_allowed', 'The confirmation email cannot be resent for this booking.');
            }
            $this->zenithFailure($e);
        }
    }
}
