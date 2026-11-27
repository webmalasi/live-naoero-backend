<?php

namespace App\Services\Flights;

use App\Exceptions\ApiException;
use App\Services\Support\Js;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Flight search: checks the app's request against the live configuration
 * (so only searches the airline can sell ever reach Zenith and its quota),
 * builds the SearchFlights request, and turns Zenith's answer into the
 * journeys and fares the app shows.
 */
final class FlightSearch
{
    private const POLICY_TEXT = [
        'WithPenalties' => 'Allowed with fees',
        'WithoutPenalties' => 'Free',
        'Forbidden' => 'Not allowed',
    ];

    // ------------------------------------------------------------ request

    public static function isRealDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value) : '';
    }

    /** JavaScript Number(): the numeric value, or null for NaN. */
    private static function number(mixed $value): int|float|null
    {
        return match (true) {
            is_int($value), is_float($value) => $value,
            is_bool($value) => (int) $value,
            $value === null => 0,
            is_string($value) => trim($value) === '' ? 0 : (is_numeric(trim($value)) ? trim($value) + 0 : null),
            default => null,
        };
    }

    /**
     * @return array{legs: list<array{origin: string, destination: string, date: string}>, passengers: array<string, int>, currency: string, promoCode: ?string, webClassCodes: list<string>, flexibleDays: int}
     *
     * @throws ApiException
     */
    public static function validate(mixed $body, array $config, ?string $today = null): array
    {
        if (! is_array($body) || array_is_list($body) && $body !== []) {
            throw ApiException::invalid('Request body must be a JSON object.');
        }
        $today ??= Js::today();
        $routes = (array) $config['routes'];

        $rawLegs = $body['legs'] ?? null;
        if (! is_array($rawLegs) || ! array_is_list($rawLegs) || $rawLegs === []) {
            throw ApiException::invalid('At least one flight leg is required.');
        }
        if (count($rawLegs) > $config['maxLegs']) {
            throw ApiException::invalid("At most {$config['maxLegs']} flight legs can be searched together.");
        }

        $legs = [];
        foreach ($rawLegs as $i => $leg) {
            $l = is_array($leg) ? $leg : [];
            $origin = strtoupper(trim(self::str($l['origin'] ?? '')));
            $destination = strtoupper(trim(self::str($l['destination'] ?? '')));
            $date = trim(self::str($l['date'] ?? ''));
            $n = $i + 1;
            if (! preg_match('/^[A-Z]{3}$/', $origin) || ! preg_match('/^[A-Z]{3}$/', $destination)) {
                throw ApiException::invalid("Leg {$n}: origin and destination must be 3-letter airport codes.");
            }
            if (! in_array($destination, $routes[$origin] ?? [], true)) {
                throw ApiException::invalid("Leg {$n}: {$origin} to {$destination} is not a route we fly.");
            }
            if (! self::isRealDate($date)) {
                throw ApiException::invalid("Leg {$n}: date must be YYYY-MM-DD.");
            }
            if ($date < $today) {
                throw ApiException::invalid("Leg {$n}: the date is in the past.");
            }
            if ($i > 0 && $date < $legs[$i - 1]['date']) {
                throw ApiException::invalid("Leg {$n} cannot depart before leg {$i}.");
            }
            $legs[] = ['origin' => $origin, 'destination' => $destination, 'date' => $date];
        }

        $rawPassengers = $body['passengers'] ?? null;
        if (! is_array($rawPassengers) || (array_is_list($rawPassengers) && $rawPassengers !== [])) {
            throw ApiException::invalid('Passengers must be an object of type code to count.');
        }
        $allowedTypes = array_column($config['passengerTypes'], 'code');
        $passengers = [];
        $total = 0;
        foreach ($rawPassengers as $code => $value) {
            $count = self::number($value);
            if ($count === null || $count < 0 || floor($count) != $count) {
                throw ApiException::invalid("Passenger count for {$code} must be a whole number.");
            }
            $count = (int) $count;
            if ($count === 0) {
                continue;
            }
            if (! in_array((string) $code, $allowedTypes, true)) {
                throw ApiException::invalid("Unknown passenger type {$code}.");
            }
            $passengers[(string) $code] = $count;
            $total += $count;
        }
        if ($total === 0) {
            throw ApiException::invalid('Add at least one passenger.');
        }
        if ($total > $config['maxPassengers']) {
            throw ApiException::invalid("At most {$config['maxPassengers']} passengers can travel on one booking.");
        }

        $currency = strtoupper(trim(self::str($body['currency'] ?? $config['defaultCurrency'])));
        if (! in_array($currency, array_column($config['currencies'], 'code'), true)) {
            throw ApiException::invalid("Currency {$currency} is not available.");
        }

        $promoCode = null;
        if (($body['promoCode'] ?? null) !== null && trim(self::str($body['promoCode'])) !== '') {
            if (! $config['allowPromoCode']) {
                throw ApiException::invalid('Promo codes are not available.');
            }
            $promoCode = mb_substr(trim(self::str($body['promoCode'])), 0, 32);
        }

        // Fare-type filters: codes must exist, and each mandatory group needs one.
        $known = [];
        foreach ($config['webClassFilters'] as $filter) {
            foreach ($filter['classes'] as $class) {
                $known[$class['code']] = true;
            }
        }
        $webClassCodes = [];
        if (($body['webClassCodes'] ?? null) !== null) {
            if (! is_array($body['webClassCodes']) || ! array_is_list($body['webClassCodes'])) {
                throw ApiException::invalid('webClassCodes must be a list.');
            }
            foreach ($body['webClassCodes'] as $code) {
                $c = self::str($code);
                if (! isset($known[$c])) {
                    throw ApiException::invalid("Unknown fare type {$c}.");
                }
                if (! in_array($c, $webClassCodes, true)) {
                    $webClassCodes[] = $c;
                }
            }
        }
        foreach ($config['webClassFilters'] as $filter) {
            if ($filter['mandatory'] && ! array_intersect(array_column($filter['classes'], 'code'), $webClassCodes)) {
                throw ApiException::invalid('Choose a '.mb_strtolower($filter['name']).' to search.');
            }
        }

        $flexibleDays = 0;
        if (($body['flexibleDays'] ?? null) !== null) {
            $f = $body['flexibleDays'];
            if (! (is_int($f) || is_float($f)) || ($f != 0 && $f != 1)) {
                throw ApiException::invalid('flexibleDays must be 0 or 1.');
            }
            $flexibleDays = (int) $f;
        }

        return compact('legs', 'passengers', 'currency', 'promoCode', 'webClassCodes', 'flexibleDays');
    }

    /** @return array<string, mixed> */
    public static function buildRequest(array $input): array
    {
        $display = ['SaleCurrencyCode' => $input['currency'], 'ShowWebClasses' => true];
        if (! empty($input['promoCode'])) {
            $display['PromoCode'] = $input['promoCode'];
        }
        if (! empty($input['webClassCodes'])) {
            $display['WebClassesCodes'] = $input['webClassCodes'];
        }

        return [
            'OriginDestinations' => array_map(fn ($leg) => [
                'OriginCode' => $leg['origin'],
                'DestinationCode' => $leg['destination'],
                // Zenith ignores time zones; it wants the local date at midnight.
                'TargetDate' => "{$leg['date']}T00:00:00",
            ], $input['legs']),
            // Each passenger entry needs a Ref that is unique within the message.
            'Passengers' => array_map(fn ($code, $quantity) => [
                'PassengerTypeCode' => (string) $code,
                'PassengerQuantity' => $quantity,
                'Ref' => "P_{$code}",
            ], array_keys($input['passengers']), $input['passengers']),
            'FareDisplaySettings' => $display,
        ];
    }

    // ------------------------------------------------------------ fare families

    private static function policy(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return self::POLICY_TEXT[$code] ?? preg_replace('/([a-z])([A-Z])/', '$1 $2', $code);
    }

    public static function titleCase(string $text): string
    {
        $text = mb_strtolower($text);
        $text = preg_replace_callback('/(^|[\s(\/&-])([a-z])/', fn ($m) => $m[1].strtoupper($m[2]), $text);
        $text = preg_replace('/\b(\d+)\s*kgs?\b/i', '$1 kg', $text);
        $text = preg_replace_callback('/\b(Max|From|With|Per|And|Or|Of|To)\b/', fn ($m) => strtolower($m[1]), $text);

        return preg_replace_callback('/^./u', fn ($m) => mb_strtoupper($m[0]), $text);
    }

    /** Turns the airline's <ul><li>..</li></ul> fare description into lines. */
    public static function parseInclusions(?string $html): array
    {
        if (! $html) {
            return [];
        }
        preg_match_all('/<li[^>]*>([\s\S]*?)<\/li>/i', $html, $matches);
        $lines = [];
        foreach ($matches[1] as $item) {
            $line = preg_replace('/<i[^>]*>[\s\S]*?<\/i>/i', '', $item); // material icon names
            $line = preg_replace('/<[^>]+>/', '', $line);
            $line = str_replace(['&amp;', '&nbsp;'], ['&', ' '], $line);
            $line = preg_replace('/[\s\x{00A0}\x{FEFF}]+/u', ' ', $line);
            $line = trim($line);
            if ($line !== '') {
                $lines[] = self::titleCase($line);
            }
        }

        return $lines;
    }

    /** @return array<string, array{code: string, name: string, color: ?string, inclusions: list<string>, order: int|float}> */
    public static function fareFamilies(array $codes): array
    {
        $families = [];
        foreach (Js::list($codes['Codes'] ?? null) as $c) {
            $color = FlightConfigService::property($c, 'Color')['StringValue'] ?? null;
            $families[$c['Code']] = [
                'code' => $c['Code'],
                'name' => Js::text($c['Label'] ?? null) ?? "Fare {$c['Code']}",
                'color' => is_string($color) && preg_match('/^[0-9a-f]{6}$/i', $color) ? '#'.strtoupper($color) : null,
                'inclusions' => self::parseInclusions(FlightConfigService::property($c, 'DescriptionHtml')['StringValue'] ?? null),
                'order' => FlightConfigService::property($c, 'Order')['NumberValue'] ?? 999,
            ];
        }

        return $families;
    }

    // ------------------------------------------------------------ response

    /** @return array<string, mixed> */
    public static function emptyResult(string $currency, string $reason): array
    {
        return ['currency' => $currency, 'offer' => null, 'journeys' => [], 'noResultsReason' => $reason];
    }

    /** @return array<string, mixed> */
    public static function normalize(array $raw, array $fareFamilies): array
    {
        $fareInfo = is_array($raw['FareInfo'] ?? null) ? $raw['FareInfo'] : [];
        $currency = $fareInfo['SaleCurrencyCode'] ?? '';
        $segments = [];
        foreach (Js::list($raw['Segments'] ?? null) as $s) {
            $segments[$s['Ref']] = $s;
        }
        $fareRules = [];
        foreach (Js::list($fareInfo['FareRules'] ?? null) as $r) {
            $fareRules[$r['Ref']] = $r;
        }
        $passengersByRef = [];
        foreach (Js::list($raw['Passengers'] ?? null) as $p) {
            $passengersByRef[$p['Ref']] = $p;
        }
        $ticketFares = Js::list($fareInfo['ETTicketFares'] ?? null);

        $journeys = [];
        foreach (Js::list($fareInfo['Itineraries'] ?? null) as $itin) {
            $ods = Js::list($itin['AirOriginDestinations'] ?? null);
            usort($ods, fn ($a, $b) => $a['OriginDestinationOrder'] <=> $b['OriginDestinationOrder']);
            $coupons = array_map(function ($od) {
                $cs = Js::list($od['AirCoupons'] ?? null);
                usort($cs, fn ($a, $b) => $a['CouponOrder'] <=> $b['CouponOrder']);

                return $cs;
            }, $ods);
            // Skip anything referencing a segment we were not sent.
            foreach ($coupons as $cs) {
                if ($cs === [] || array_filter($cs, fn ($c) => ! isset($segments[$c['RefSegment'] ?? ''])) !== []) {
                    continue 2;
                }
            }

            $journeyId = implode('|', array_map(fn ($cs) => implode('+', array_column($cs, 'RefSegment')), $coupons));
            if (! isset($journeys[$journeyId])) {
                $legs = [];
                foreach ($ods as $i => $od) {
                    $legs[] = self::buildLeg($od['OriginDestinationOrder'], array_map(fn ($c) => $segments[$c['RefSegment']], $coupons[$i]));
                }
                $journeys[$journeyId] = ['id' => $journeyId, 'legs' => $legs, 'fares' => [], 'lowestPrice' => INF];
            }

            $fares = array_values(array_filter($ticketFares, fn ($f) => ($f['RefItinerary'] ?? null) === $itin['Ref']));
            $firstFare = $fares[0] ?? null;

            $seatsLeft = null;
            $legCabins = [];
            foreach ($coupons as $cs) {
                $cabin = null;
                foreach ($cs as $c) {
                    $bookingClass = null;
                    foreach (Js::list($segments[$c['RefSegment']]['BookingClasses'] ?? null) as $bc) {
                        if (($bc['Code'] ?? null) === ($c['BookingClassCode'] ?? null)) {
                            $bookingClass = $bc;
                            break;
                        }
                    }
                    if (isset($bookingClass['Quantity'])) {
                        $seatsLeft = $seatsLeft === null ? $bookingClass['Quantity'] : min($seatsLeft, $bookingClass['Quantity']);
                    }
                    $cabin ??= $bookingClass['CabinClassCode'] ?? null;
                }
                $legCabins[] = match ($cabin) {
                    'C' => 'Business',
                    'Y' => 'Economy',
                    default => $cabin,
                };
            }

            $total = $itin['SaleCurrencyAmount']['TotalAmount'] ?? 0;
            $adultFare = $firstFare;
            foreach ($fares as $f) {
                if (($passengersByRef[$f['RefPassenger'] ?? '']['PassengerTypeCode'] ?? null) === 'AD') {
                    $adultFare = $f;
                    break;
                }
            }

            $fareLegs = [];
            foreach ($ods as $i => $od) {
                $order = $od['OriginDestinationOrder'];
                // All passengers share the same web class on a given leg, so the
                // first ticket fare is enough (per the Zenith web-class guide).
                $odFare = self::odFare($firstFare, $order);
                $familyCode = $odFare['RefWebClass'] ?? null;
                $family = $familyCode !== null ? ($fareFamilies[$familyCode] ?? null) : null;
                $bag = null;
                foreach (Js::list(self::odFare($adultFare, $order)['CouponFares'][0]['BagAllowances'] ?? null) as $b) {
                    if (empty($b['CarryOn'])) {
                        $bag = $b;
                        break;
                    }
                }
                $rule = ! empty($od['RefFareRule']) ? ($fareRules[$od['RefFareRule']] ?? null) : null;
                $fareLegs[] = [
                    'order' => $order,
                    'cabin' => $legCabins[$i],
                    'fareFamily' => $family
                        ? ['code' => $family['code'], 'name' => $family['name'], 'color' => $family['color'], 'inclusions' => $family['inclusions']]
                        : ($familyCode !== null ? ['code' => $familyCode, 'name' => "Fare {$familyCode}", 'color' => null, 'inclusions' => []] : null),
                    'bookingClass' => $coupons[$i][0]['BookingClassCode'] ?? null,
                    'checkedBaggageKg' => $bag['Weight'] ?? null,
                    'changePolicy' => self::policy($rule['VoluntaryChangeCode'] ?? null),
                    'refundPolicy' => self::policy($rule['VoluntaryRefundCode'] ?? null),
                ];
            }

            $journeys[$journeyId]['fares'][] = [
                'itineraryRef' => $itin['Ref'],
                'currency' => $currency,
                'totalAmount' => Js::round2($total),
                'baseAmount' => Js::round2($itin['SaleCurrencyAmount']['BaseAmount'] ?? 0),
                'taxAmount' => Js::round2($itin['SaleCurrencyAmount']['TaxAmount'] ?? 0),
                'discountAmount' => Js::round2($itin['SaleCurrencyAmount']['DiscountAmount'] ?? 0),
                'seatsLeft' => $seatsLeft,
                'legs' => $fareLegs,
                'passengers' => array_map(function ($f) use ($passengersByRef) {
                    $pax = $passengersByRef[$f['RefPassenger'] ?? ''] ?? null;

                    return [
                        'type' => $pax['PassengerTypeCode'] ?? ($f['RefPassenger'] ?? null),
                        'quantity' => $pax['PassengerQuantity'] ?? 1,
                        'unitAmount' => Js::round2($f['SaleCurrencyAmount']['TotalAmount'] ?? 0),
                    ];
                }, $fares),
            ];
            $journeys[$journeyId]['lowestPrice'] = min($journeys[$journeyId]['lowestPrice'], Js::round2($total));
        }

        $sorted = array_values($journeys);
        foreach ($sorted as &$journey) {
            usort($journey['fares'], fn ($a, $b) => $a['totalAmount'] <=> $b['totalAmount']);
        }
        unset($journey);
        usort($sorted, fn ($a, $b) => strcmp($a['legs'][0]['departure'], $b['legs'][0]['departure']) ?: $a['lowestPrice'] <=> $b['lowestPrice']);

        $offerRef = $raw['Offer']['Ref'] ?? null;

        return [
            'currency' => $currency,
            'offer' => $offerRef ? ['ref' => $offerRef, 'expiresAt' => $raw['Offer']['ExpirationDateGMT'] ?? null] : null,
            'journeys' => $sorted,
            'noResultsReason' => $sorted ? null : 'NoAvailability',
        ];
    }

    private static function odFare(?array $fare, mixed $order): ?array
    {
        foreach (Js::list($fare['OriginDestinationFares'] ?? null) as $f) {
            if (($f['OriginDestinationOrder'] ?? null) === $order) {
                return $f;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private static function buildLeg(mixed $order, array $segs): array
    {
        $segments = array_map(fn ($s) => [
            'flightNumber' => trim(($s['AirlineDesignator'] ?? '').($s['FlightInfo']['FlightNumber'] ?? '')),
            'origin' => $s['OriginCode'],
            'destination' => $s['DestinationCode'],
            'departure' => $s['FlightInfo']['DepartureDate'] ?? '',
            'arrival' => $s['FlightInfo']['ArrivalDate'] ?? '',
            'durationMinutes' => $s['FlightInfo']['DurationMinutes'] ?? null,
            'aircraft' => $s['FlightInfo']['EquipmentText'] ?? null,
        ], $segs);
        $first = $segments[0];
        $last = $segments[count($segments) - 1];

        return [
            'order' => $order,
            'origin' => $first['origin'],
            'destination' => $last['destination'],
            'departure' => $first['departure'],
            'arrival' => $last['arrival'],
            'durationMinutes' => count($segments) === 1 ? $first['durationMinutes'] : null,
            'stops' => count($segments) - 1,
            'segments' => $segments,
        ];
    }

    // ------------------------------------------------------------ flexible dates

    public static function shiftDate(string $date, int $days): string
    {
        return (new DateTimeImmutable($date, new DateTimeZone('UTC')))->modify("{$days} day")->format('Y-m-d');
    }

    /**
     * The date combinations for a "flexible dates" search: each leg moved a
     * day either way while the other stays put (5 searches for a return
     * trip, 3 for one-way). Combinations in the past, out of order, or on
     * days the airline doesn't fly are dropped, so they cost nothing.
     *
     * @return list<list<string>>
     */
    public static function flexibleDateSets(array $input, array $config, string $today): array
    {
        $base = array_column($input['legs'], 'date');
        $sets = [$base];
        foreach ($base as $i => $date) {
            foreach ([-1, 1] as $delta) {
                $dates = $base;
                $dates[$i] = self::shiftDate($date, $delta);
                $sets[] = $dates;
            }
        }
        $seen = [];
        $out = [];
        foreach ($sets as $dates) {
            $key = implode('|', $dates);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            foreach ($dates as $i => $d) {
                if ($d < $today || ($i > 0 && $d < $dates[$i - 1])) {
                    continue 2;
                }
                $leg = $input['legs'][$i];
                if (! FlightConfigService::isServedOn($config, $leg['origin'], $leg['destination'], $d)) {
                    continue 2;
                }
            }
            $out[] = $dates;
        }

        return $out;
    }
}
