<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * An in-memory stand-in for the Zenith Sales API: customer accounts,
 * reference data and the real preproduction responses in tests/Fixtures.
 */
class FakeZenith
{
    /** @var array<string, array{password: string, customer: array}> by lower-case login */
    public array $accounts = [];

    /** @var list<array{0: string, 1: array}> */
    public array $calls = [];

    /** @var array<string, array|string> operation => response body, or "down" */
    public array $failures = [];

    /** What LoadBooking returns (defaults to the CreateBooking fixture's booking). */
    public ?array $booking = null;

    /** What PrepareFlights returns (defaults to the PrepareFlights fixture). */
    public ?array $prepare = null;

    /** Bookings by PNR for LoadBooking; when set, other PNRs are unknown. */
    public array $pnrBookings = [];

    /** What SearchBooking returns (BookingSummaries). */
    public array $searchResults = [];

    /** What PrepareBookingModification returns, when not built from the booking. */
    public ?array $modification = null;

    /** What FlightStatus returns (defaults to the FlightStatus fixture). */
    public ?array $flightStatus = null;

    /** What PrepareCheckin returns (defaults to the PrepareCheckin fixture: not allowed online). */
    public ?array $checkin = null;

    /** Forms of payment the airline offers on top of the fixture: [code => currencies, ...] or with a logo. */
    public array $extraForms = [];

    /** What CreateTicket returns when set (e.g. the airline's HTML card form). */
    public ?array $createTicket = null;

    /** What PrepareCancel/Cancel refund: [ticket number => amount], and the penalty kept. */
    public array $refunds = [];

    public float $refundPenalty = 50.0;

    /** SaleConfiguration RefundConfig.RefundFopCodes (the airline's key has none yet). */
    public array $refundFopCodes = [];

    /** Credit vouchers (MCOTickets) by customer reference. */
    public array $mcos = [];

    /** What SearchServices offers (EMDTicketFareOptions). */
    public ?array $services = null;

    /** What FlownCoupons returns (ETCouponFlowns). */
    public array $flown = [];

    /** What Calendar returns per route: "ORG-DST" => [date => status code]. */
    public array $calendar = [];

    private int $nextRef = 5000;

    public static function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__."/../../../Fixtures/Zenith/{$name}.json"), true);
    }

    public function install(): self
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => $this->handle($request));

        return $this;
    }

    public static function error(string $code, string $message, array $extra = []): array
    {
        return ['ResponseInfo' => ['Error' => ['Code' => $code, 'Message' => $message, 'FullText' => 'stack trace']]] + $extra;
    }

    public function calls(string $operation): array
    {
        return array_values(array_map(fn ($c) => $c[1], array_filter($this->calls, fn ($c) => $c[0] === $operation)));
    }

    public function addAccount(string $email, string $password, array $extra = []): array
    {
        $customer = [
            'Ref' => (string) $this->nextRef++,
            'Login' => $email,
            'TypeCode' => 'EndCustomer',
            'CivilityCode' => 'MR',
            'Firstname' => 'Ana',
            'Surname' => 'Citizen',
            'Emails' => [['Ref' => 'E1', 'Email' => $email]],
            'Phones' => [['Ref' => 'P1', 'PhoneLocationTypeCode' => 'Home', 'PhoneTypeCode' => 'Mobile', 'PhoneNumber' => '+61400000001']],
            'Addresses' => [],
            'Documents' => [],
            'CultureName' => 'en-GB',
            'Currency' => 'AUD',
            ...$extra,
        ];
        $this->accounts[strtolower($email)] = ['password' => $password, 'customer' => $customer];

        return $customer;
    }

    private function codes(array $pairs): array
    {
        return ['Codes' => array_map(fn ($code, $label) => ['Code' => $code, 'Label' => $label], array_keys($pairs), $pairs)];
    }

    private function handle(Request $request)
    {
        // Other services (NAB) have their own fakes.
        if (! str_starts_with($request->url(), config('naoero.zenith.endpoint'))) {
            return null;
        }
        $operation = basename(parse_url($request->url(), PHP_URL_PATH));
        $body = $request->data();
        $this->calls[] = [$operation, $body];

        if (isset($this->failures[$operation])) {
            $failure = $this->failures[$operation];

            return $failure === 'down' ? Http::response('Service Unavailable', 503) : Http::response($failure);
        }

        return Http::response($this->answer($operation, $body));
    }

    private function answer(string $operation, array $body): array
    {
        switch ($operation) {
            case 'SaleConfiguration':
                $sale = self::fixture('SaleConfiguration');
                foreach ($this->extraForms as $code => [$currencies, $logo]) {
                    $form = $sale['AllowedFormsOfPayment'][0];
                    $form['FormOfPaymentCode'] = $form['FopTemplate']['Code'] = $code;
                    $form['AllowedCurrencyCodes'] = $currencies;
                    $form['OnlinePaymentInfo']['LogoUrl'] = $logo;
                    $sale['AllowedFormsOfPayment'][] = $form;
                }
                $sale['RefundConfig']['RefundFopCodes'] = $this->refundFopCodes;

                return $sale;
            case 'EligibleOriginDestinations':
                return self::fixture('eod');
            case 'GetValueCodes':
                return match ($body['ValueCodeName']) {
                    'Airport' => self::fixture('vc_Airport'),
                    'WebClass' => self::fixture('vc_WebClass'),
                    'Civility' => $this->codes(['MR' => 'Mr.', 'MRS' => 'Mrs', 'MS' => 'Ms', 'MISS' => 'Miss', 'CHD' => 'Child', 'INF' => 'Infant']),
                    'Gender' => $this->codes(['M' => 'Male', 'F' => 'Female']),
                    'DocumentType' => $this->codes(['DOI' => 'Document of Identity', 'NI' => 'ID Card', 'PP' => 'Passport', 'RT' => 'Refugee travel document']),
                    'Country' => $this->codes(['AU' => 'Australia', 'NR' => 'Nauru', 'FJ' => 'Fiji', 'XXX' => 'Not a country']),
                };
            case 'CustomerPasswordPolicies':
                return ['Policies' => []];
            case 'NewCustomer':
                $login = strtolower($body['Customer']['Login']);
                if (isset($this->accounts[$login])) {
                    return self::error('', 'Login already exists.');
                }
                $customer = ['Ref' => (string) $this->nextRef++, ...$body['Customer']];
                $this->accounts[$login] = ['password' => $body['Password'], 'customer' => $customer];

                return ['Customer' => $customer];
            case 'LoadCustomer':
                if (isset($body['ByLoginAndPassword'])) {
                    $account = $this->accounts[strtolower($body['ByLoginAndPassword']['Login'])] ?? null;
                    // Zenith compares passwords case-insensitively.
                    $ok = $account && strcasecmp($account['password'], $body['ByLoginAndPassword']['Password']) === 0;

                    return ['Customer' => $ok ? $account['customer'] : null];
                }

                return ['Customer' => $this->byRef($body['ByReference']['RefCustomer'])['customer'] ?? null];
            case 'UpdateCustomer':
                $account = $this->byRef($body['Customer']['Ref']);
                $saved = $body['Customer'];
                // As the airline's Zenith does: the street number and state are not kept.
                foreach ($saved['Addresses'] ?? [] as $i => $a) {
                    $saved['Addresses'][$i] = ['Ref' => $a['Ref'] ?? (string) (400 + $i), ...$a, 'StreetNumber' => null, 'State' => null];
                }
                $this->accounts[strtolower($account['customer']['Login'])]['customer'] = $saved;

                return ['Customer' => $saved];
            case 'ChangeCustomerPassword':
                $login = strtolower($body['ByLoginAndPassword']['Login']);
                if (strcasecmp($this->accounts[$login]['password'] ?? '', $body['ByLoginAndPassword']['Password']) !== 0) {
                    return self::error('WrongVerificationCustomer', 'Wrong password');
                }
                $this->accounts[$login]['password'] = $body['NewPassword'];

                return ['Customer' => $this->accounts[$login]['customer']];
            case 'LostCustomerPassword':
            case 'SendCustomerRemovalRequest':
            case 'SendTicketConfirmation':
                return [];
            case 'SearchFlights':
                return self::fixture('search_owweb');
            case 'PrepareFlights':
                return $this->prepare ?? self::fixture('PrepareFlights');
            case 'PrepareBookingModification':
                return $this->modification ?? $this->modificationOffer($this->booking ?? self::fixture('CreateBooking')['Booking']);
            case 'ModifyBooking':
                $this->booking = $this->applyModification($this->booking ?? self::fixture('CreateBooking')['Booking'], $body);

                return ['Booking' => $this->booking, 'InvalidData' => null];
            case 'CreateBooking':
                return self::fixture('CreateBooking');
            case 'LoadBooking':
                if ($this->pnrBookings) {
                    $pnr = strtoupper($body['UniqueID']['ID'] ?? '');
                    // By e-ticket number: the booking holding that ticket.
                    if (($body['UniqueID']['TypeCode'] ?? null) === 'ETTicketNumber') {
                        foreach ($this->pnrBookings as $code => $b) {
                            if (in_array($pnr, array_column($b['TicketInfo']['ETTickets'] ?? [], 'DocumentNumber'), true)) {
                                return ['Booking' => $b];
                            }
                        }

                        return self::error('', "Ticket {$pnr} does not exist");
                    }

                    return isset($this->pnrBookings[$pnr]) ? ['Booking' => $this->pnrBookings[$pnr]] : self::error('', "Unknown PNR {$pnr} does not exist");
                }

                return ['Booking' => $this->booking ?? self::fixture('CreateBooking')['Booking']];
            case 'SearchBooking':
                return ['BookingSummaries' => $this->searchResults];
            case 'FlightStatus':
                return $this->flightStatus ?? self::fixture('FlightStatus');
            case 'SearchFlightsForExchange':
                return self::fixture('SearchFlightsForExchange');
            case 'PrepareExchange':
                return self::fixture('PrepareExchange');
            case 'Exchange':
                $this->booking = self::fixture('Exchange')['Booking'];

                return ['Booking' => $this->booking, 'InvalidData' => null];
            case 'CancelPendingExchange':
                $this->booking = self::fixture('LoadBookingChangeCancelled')['Booking'];

                return ['Booking' => $this->booking];
            case 'SearchFlightsForAdditionalItinerary':
                return self::fixture('search_owweb');
            case 'PrepareAdditionalItinerary':
                // Passenger data is already on the booking: nothing mandatory is missing.
                return ['SpecialServices' => []] + self::fixture('PrepareFlights');
            case 'AddItinerary':
                $booking = $this->booking ?? self::fixture('LoadBookingTicketed')['Booking'];
                $booking['MiscInfo']['ContainsIssuableTickets'] = true;
                $booking['FareInfo']['SaleCurrencyTravelerAmountToPay'] = ['TotalAmount' => 452.3];
                $this->booking = $booking;

                return ['Booking' => $booking, 'InvalidData' => null];
            case 'SplitBooking':
                return ['PnrCode' => 'SPL001', 'HasForcedSplit' => false];
            case 'PrepareCheckin':
                return $this->checkin ?? self::fixture('PrepareCheckin');
            case 'Checkin':
                $booking = ($this->checkin ?? self::fixture('PrepareCheckin'))['Booking'];
                foreach ($booking['TicketInfo']['ETTickets'] as &$ticket) {
                    foreach ($ticket['Coupons'] as &$coupon) {
                        $coupon['StatusCode'] = 'C';
                    }
                    unset($coupon);
                    $booking['MiscInfo']['BoardingPassList'][] = ['DocumentNumber' => $ticket['DocumentNumber'], 'CouponOrders' => [1, 2], 'HttpLink' => '/Zenith/BoardingPass?arg=abc'];
                }
                unset($ticket);
                $this->booking = $booking;

                return ['Booking' => $booking];
            case 'CreateTicket':
                if ($this->createTicket !== null) {
                    return $this->createTicket;
                }
                if (($body['Fops'] ?? []) === [] && $this->booking) {
                    // Nothing to pay (a free flight change): issued at once.
                    $this->booking['MiscInfo']['ContainsIssuableTickets'] = false;
                    $this->booking['MiscInfo']['IsPendingExchange'] = false;

                    return ['Booking' => $this->booking, 'RedirectResult' => null];
                }

                return ['RedirectResult' => ['UrlGet' => 'https://payments.example/checkout/abc', 'HtmlForm' => null]];
            case 'PrepareCancel':
            case 'Cancel':
                return $this->cancel($operation, $body);
            case 'LoadMCOTickets':
                $ref = $body['CustomerReference']['RefCustomer'] ?? null;
                $owner = $this->byRef((string) $ref);
                // Both the reference and the login, as Zenith requires.
                if (! $owner || strcasecmp($owner['customer']['Login'], (string) ($body['CustomerReference']['Login'] ?? '')) !== 0) {
                    return ['MCOTickets' => []];
                }
                $list = $this->mcos[$ref] ?? [];

                return ['MCOTickets' => ! empty($body['UsableOnly']) ? array_values(array_filter($list, fn ($m) => $m['StatusCode'] === 'Issued')) : $list];
            case 'SearchServices':
                return ['EMDTicketFareOptions' => $this->services ?? [self::serviceOption()], 'SaleCurrencyCode' => $body['SaleCurrencyCode'] ?? 'AUD'];
            case 'BuyServices':
                $pnr = 'SRV'.str_pad((string) (count($this->pnrBookings) + 1), 3, '0', STR_PAD_LEFT);
                $options = collect($this->services ?? [self::serviceOption()])->keyBy('Ref');
                $fares = array_map(fn ($f) => [...$options[$f['Ref']], 'Ref' => 'TMP_'.$f['Ref'].'_'.$f['Ref']], $body['EMDTicketFares']);
                $total = array_sum(array_map(fn ($f) => $f['SaleCurrencyAmount']['TotalAmount'], $fares));
                $booking = [
                    'PnrInformation' => ['PnrCode' => $pnr, 'PnrStatusCode' => 'Option', 'TimeLimit' => '0001-01-01T00:00:00', 'RefCustomer' => $body['RefCustomer'], 'IsServiceBooking' => true],
                    'Passengers' => [], 'Segments' => [], 'SpecialServices' => [],
                    'FareInfo' => ['EMDTicketFares' => $fares, 'SaleCurrencyAmountToPay' => ['TotalAmount' => $total], 'SaleCurrencyTravelerAmountToPay' => ['TotalAmount' => $total], 'SaleCurrencyAmountTotal' => ['TotalAmount' => $total]],
                    'TicketInfo' => ['ETTickets' => [], 'EMDTickets' => [], 'Fops' => []],
                    'MiscInfo' => ['ContainsIssuableTickets' => true],
                ];
                $this->pnrBookings[$pnr] = $booking;

                return ['Booking' => $booking];
            case 'Uncheck':
                $booking = $this->booking ?? self::fixture('LoadBookingTicketed')['Booking'];
                $numbers = array_column($body['ETTicketTargets'], 'DocumentNumber');
                foreach ($booking['TicketInfo']['ETTickets'] as &$ticket) {
                    if (in_array($ticket['DocumentNumber'], $numbers, true)) {
                        foreach ($ticket['Coupons'] as &$coupon) {
                            $coupon['StatusCode'] = 'O';
                        }
                        unset($coupon);
                    }
                }
                unset($ticket);
                // Zenith cancels the flights of an unchecked passenger (a no-show).
                foreach ($booking['Segments'] as &$segment) {
                    $segment['BookingClass']['StatusCode'] = 'HX';
                }
                unset($segment);
                $this->booking = $booking;

                return ['Booking' => $booking];
            case 'FlownCoupons':
                $from = substr($body['StartFlightDate'], 0, 10);
                $to = substr($body['EndFlightDate'], 0, 10);

                return ['ETCouponFlowns' => array_values(array_filter($this->flown, fn ($f) => substr($f['DepartureDate'], 0, 10) >= $from && substr($f['DepartureDate'], 0, 10) <= $to))];
            case 'Calendar':
                $q = $body['OriginDestinationQueries'][0];
                $days = [];
                foreach ($this->calendar["{$q['OriginCode']}-{$q['DestinationCode']}"] ?? [] as $date => $code) {
                    if ($date >= substr($q['StartDate'], 0, 10) && $date <= substr($q['EndDate'], 0, 10)) {
                        $days[] = ['Date' => "{$date}T00:00:00", 'CalendarDayStatusCode' => $code];
                    }
                }

                return ['OriginDestinationResults' => [['OriginCode' => $q['OriginCode'], 'DestinationCode' => $q['DestinationCode'], 'Days' => $days]]];
            case 'GetValueCodeList':
                return ['CodeList' => ['Airport', 'CabinClass', 'MCOStatusCode', 'WebClass']];
            case 'Ping':
                return ['UTC' => '2026-11-04T10:00:00', 'WelcomeText' => 'Welcome '.($body['StringTest'] ?? '')];
        }

        return self::error('NotAllowed', "Unexpected {$operation}");
    }

    /**
     * PrepareBookingModification for $booking, shaped like the real answer:
     * pre-instantiated SSRs per passenger and flight (customer services and
     * internal codes), the real INU-TRW seat map and its paid extra baggage.
     */
    private function modificationOffer(array $booking): array
    {
        $real = self::fixture('PrepareFlightsExtras');
        $segment = (string) $booking['Segments'][0]['Ref'];
        $optional = [['Code' => 'OTHS']];
        $adult = null;
        foreach ($booking['Passengers'] as $p) {
            $ref = (string) $p['Ref'];
            foreach (['VGML', 'WCHR', 'BIKE', 'TKNE', 'BBML'] as $code) {
                $optional[] = ['Code' => $code, 'RefPassenger' => $ref, 'RefSegment' => $segment];
            }
            if ($p['PassengerTypeCode'] !== 'INF') {
                $adult ??= $ref;
                $optional[] = ['Code' => 'SEAT', 'RefPassenger' => $ref, 'RefSegment' => $segment, 'Data' => ['Seat' => ['SeatRow' => 0, 'SeatLetter' => null]]];
            }
        }
        $map = $real['SeatMaps'][0];
        $map['RefSegment'] = $segment;
        $booking['SeatMaps'] = [$map];
        $option = $real['FareInfo']['EMDTicketFareOptions'][0];
        $option['AppliableRefPassengers'] = [$adult];
        $option['AppliableRefSegments'] = [$segment];

        return [
            'Booking' => $booking,
            'Passengers' => $booking['Passengers'],
            'SpecialServices' => [],
            'OptionalSpecialServices' => $optional,
            'FareInfo' => [
                'SaleCurrencyCode' => 'AUD',
                'EMDTicketFareOptions' => [$option],
                'EMDTicketFares' => [],
                'FareRules' => $booking['FareInfo']['FareRules'] ?? [],
                'Itineraries' => $booking['FareInfo']['Itineraries'] ?? [],
            ],
        ];
    }

    /** ModifyBooking: adds and removes SSRs and ancillaries the way Zenith does. */
    private function applyModification(array $booking, array $body): array
    {
        $same = fn ($a, $b) => ($a['Code'] ?? null) === ($b['Code'] ?? null)
            && (string) ($a['RefPassenger'] ?? '') === (string) ($b['RefPassenger'] ?? '')
            && (string) ($a['RefSegment'] ?? '') === (string) ($b['RefSegment'] ?? '');
        $ssrs = $booking['SpecialServices'] ?? [];
        foreach ($body['RemovedSpecialServices'] ?? [] as $r) {
            $ssrs = array_values(array_filter($ssrs, fn ($s) => ! $same($s, $r)));
        }
        foreach ($body['SpecialServices'] ?? [] as $s) {
            $ssrs = array_values(array_filter($ssrs, fn ($x) => ! $same($x, $s)));
            $ssrs[] = [...$s, 'Status' => 'NN'];
        }
        $booking['SpecialServices'] = $ssrs;
        $emds = $booking['FareInfo']['EMDTicketFares'] ?? [];
        $emds = array_values(array_filter($emds, fn ($e) => ! in_array($e['Ref'], $body['RemovedEMDTicketFares'] ?? [], true)));
        foreach ($body['EMDTicketFares'] ?? [] as $e) {
            $emds[] = $e;
            $booking['MiscInfo']['ContainsIssuableTickets'] = true;
        }
        $booking['FareInfo']['EMDTicketFares'] = $emds;

        return $booking;
    }

    /** A service on sale on its own: a travel credit bundle. */
    public static function serviceOption(array $extra = []): array
    {
        return [
            'Ref' => '10000084', 'ServiceTypeCode' => 'ECoupon', 'ServiceCode' => '10000084', 'Label' => 'Island Pass',
            'HtmlDescription' => '<p>10 one-way <b>travel credits</b>.</p><script>x</script>', 'MaxQuantity' => 5, 'AllowMultiSelect' => true,
            'SaleCurrencyAmount' => ['BaseAmount' => 199.0, 'TaxAmount' => 0, 'TotalAmount' => 199.0],
            // As in Zenith's service bookings: the currency only here.
            'AmountDetails' => ['FareCurrencyCode' => 'AUD', 'BaseCurrencyCode' => 'AUD'],
            'AppliableRefPassengers' => [], 'AppliableRefSegments' => [], ...$extra,
        ];
    }

    /**
     * PrepareCancel and Cancel on the current booking, the way Zenith does:
     * segment cancel lists (then cancels) the held flights; refund quotes
     * each ticket, keeps the penalty, cancels everything and creates the
     * voucher on the booking's customer.
     */
    private function cancel(string $operation, array $body): array
    {
        $pnr = strtoupper($body['UniqueID']['ID'] ?? '');
        $booking = $this->pnrBookings[$pnr] ?? $this->booking ?? self::fixture('CreateBooking')['Booking'];
        $held = array_values(array_map(fn ($s) => (string) $s['Ref'], array_filter($booking['Segments'], fn ($s) => ! in_array($s['BookingClass']['StatusCode'], ['HX', 'XX'], true))));
        $settings = $body['CancelSettings'] ?? [];

        if (array_key_exists('RefundSettings', $settings)) {
            if (empty($booking['MiscInfo']['CancelInfo']['CanRefund'])) {
                return self::error('NotRefundable', 'Ticket is not refundable');
            }
            $docs = [];
            foreach ($booking['TicketInfo']['ETTickets'] as $t) {
                $amount = $this->refunds[$t['DocumentNumber']] ?? 300.0;
                $docs[] = ['DocumentNumber' => $t['DocumentNumber'], 'CouponOrder' => 1, 'SaleCurrencyAmountToRefund' => ['BaseAmount' => $amount - 20, 'TaxAmount' => 20, 'TotalAmount' => $amount], 'TaxesToRefund' => []];
            }
            $total = array_sum(array_map(fn ($d) => $d['SaleCurrencyAmountToRefund']['TotalAmount'], $docs));
            $fare = [
                'EMDTicketFares' => $this->refundPenalty > 0 ? [['Label' => 'Cancellation penalty', 'RefPassenger' => $booking['Passengers'][0]['Ref'], 'SaleCurrencyAmount' => ['TotalAmount' => $this->refundPenalty]]] : [],
                'ElectronicDocumentsToCancel' => $docs,
                'SaleCurrencyAmountToRefund' => ['TotalAmount' => $total],
                'SaleCurrencyAmountToRefundInAccount' => 0,
            ];
            if ($operation === 'PrepareCancel') {
                return ['Booking' => $booking, 'FareInfo' => $fare, 'SegmentReferencesToCancel' => $held];
            }
            $fop = $settings['RefundSettings']['RefundFops'][0] ?? null;
            if ($total > 0 && (($fop['Code'] ?? null) !== 'MCO' || abs(($fop['Amount'] ?? 0) - $total) > 0.001)) {
                return self::error('InvalidRefundFop', 'Refund fops do not match the amount to refund');
            }
            foreach ($booking['TicketInfo']['ETTickets'] as &$ticket) {
                foreach ($ticket['Coupons'] as &$coupon) {
                    $coupon['StatusCode'] = 'RF';
                }
                unset($coupon);
            }
            unset($ticket);
            $refs = $held;
            $customer = (string) ($booking['PnrInformation']['RefCustomer'] ?? '');
            if ($total > 0) {
                $this->mcos[$customer][] = ['DocumentNumber' => '9990000000'.count($this->mcos[$customer] ?? []), 'Amount' => $total, 'Currency' => $booking['FareInfo']['SaleCurrencyCode'] ?? 'AUD', 'StatusCode' => 'Issued', 'ExpiryDateGMT' => '2027-11-04T00:00:00'];
            }
        } else {
            if (empty($booking['MiscInfo']['CancelInfo']['CanCancel'])) {
                return self::error('NotCancellable', 'Booking cannot be cancelled');
            }
            if ($operation === 'PrepareCancel') {
                return ['Booking' => $booking, 'SegmentReferencesToCancel' => $held];
            }
            $asked = array_map('strval', $settings['CancelSegmentSettings']['SegmentReferencesToCancel'] ?? []);
            if (array_diff($asked, $held)) {
                return self::error('InvalidSegment', 'Segment cannot be cancelled');
            }
            $refs = $asked === [] ? $held : $asked;
            $fare = null;
        }

        foreach ($booking['Segments'] as &$segment) {
            if (in_array((string) $segment['Ref'], $refs, true)) {
                $segment['BookingClass']['StatusCode'] = 'HX';
            }
        }
        unset($segment);
        if (isset($this->pnrBookings[$pnr])) {
            $this->pnrBookings[$pnr] = $booking;
        } else {
            $this->booking = $booking;
        }

        return ['Booking' => $booking, 'FareInfo' => $fare];
    }

    private function byRef(string $ref): ?array
    {
        foreach ($this->accounts as $account) {
            if ($account['customer']['Ref'] === $ref) {
                return $account;
            }
        }

        return null;
    }
}
