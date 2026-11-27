<?php

namespace App\Services\Booking;

use App\Services\Support\Js;
use Illuminate\Support\Str;

/**
 * Additional services on a booking, from what Zenith offers for it
 * (PrepareFlights at booking time, PrepareBookingModification afterwards):
 *
 *  - special services: free requests such as meals, wheelchairs, pets
 *    (only those in ExtrasCatalog);
 *  - seats: the seat map of each flight; a seat is chosen with a SEAT
 *    request, and a chargeable seat creates its own ancillary;
 *  - ancillaries: paid extras such as extra baggage (EMDTicketFareOptions),
 *    turned into EMDTicketFares on the booking;
 *  - mandatory fees and fare rules, for information.
 *
 * The customer's choice ("selection") is checked against that offer here,
 * never trusted: only what Zenith offered for that passenger and flight can
 * be booked.
 */
final class BookingExtras
{
    /** Most of one paid extra a passenger can take, when Zenith sets no limit. */
    private const MULTI_MAX = 9;

    // ------------------------------------------------------------ offer

    /**
     * What can be added. $source is a PrepareFlights or
     * PrepareBookingModification response. $names: passenger ref => name.
     *
     * @param  array<string, string>  $names
     * @param  array<string, array>  $ancillaryCodes  AncillaryCode value codes by code (labels, refundable…)
     * @return array<string, mixed>
     */
    public static function offer(array $source, array $names = [], array $ancillaryCodes = []): array
    {
        $booking = is_array($source['Booking'] ?? null) ? $source['Booking'] : null;
        $passengers = Js::list($source['Passengers'] ?? null) ?: Js::list($booking['Passengers'] ?? null);
        $segments = Js::list($source['Segments'] ?? null) ?: Js::list($booking['Segments'] ?? null);
        $fare = is_array($source['FareInfo'] ?? null) ? $source['FareInfo'] : [];
        $optional = Js::list($source['OptionalSpecialServices'] ?? null);
        $seatMaps = Js::list($source['SeatMaps'] ?? null) ?: Js::list($booking['SeatMaps'] ?? null);
        $currency = (string) ($fare['SaleCurrencyCode'] ?? $booking['FareInfo']['SaleCurrencyCode'] ?? '');
        $types = self::passengerTypes($passengers);

        // Passengers, numbered per type: "Adult 1", "Child 1"…
        $counters = [];
        $paxOut = [];
        foreach ($passengers as $p) {
            $type = (string) ($p['PassengerTypeCode'] ?? '');
            $counters[$type] = ($counters[$type] ?? 0) + 1;
            $name = $names[(string) $p['Ref']] ?? trim(($p['NameElement']['Firstname'] ?? '').' '.($p['NameElement']['Surname'] ?? ''));
            $paxOut[] = [
                'ref' => (string) $p['Ref'],
                'type' => $type,
                'label' => self::typeLabel($type).' '.$counters[$type],
                'name' => $name !== '' ? $name : null,
            ];
        }
        $segOut = array_map(fn ($s) => [
            'ref' => (string) $s['Ref'],
            'origin' => $s['OriginCode'] ?? null,
            'destination' => $s['DestinationCode'] ?? null,
            'flightNumber' => trim(($s['AirlineDesignator'] ?? $s['FlightInfo']['OperatingAirlineDesignator'] ?? '').($s['FlightInfo']['FlightNumber'] ?? '')),
            'departure' => $s['FlightInfo']['DepartureDate'] ?? null,
        ], $segments);

        // Special services, grouped by code with where each can be requested.
        $services = [];
        foreach ($optional as $s) {
            $code = (string) ($s['Code'] ?? '');
            if (! ExtrasCatalog::offered($code)) {
                continue;
            }
            $pax = isset($s['RefPassenger']) ? (string) $s['RefPassenger'] : null;
            if ($pax !== null && ! ExtrasCatalog::forPassengerType($code, $types[$pax] ?? null)) {
                continue;
            }
            $services[$code] ??= [...ExtrasCatalog::describe($code), 'maxLength' => ExtrasCatalog::TEXT_MAX, 'available' => []];
            $services[$code]['available'][] = ['passengerRef' => $pax, 'segmentRef' => isset($s['RefSegment']) ? (string) $s['RefSegment'] : null];
        }
        $order = array_flip(array_keys(ExtrasCatalog::SERVICES));
        uksort($services, fn ($a, $b) => $order[$a] <=> $order[$b]);

        // Paid extras on offer.
        $ancillaries = [];
        foreach (Js::list($fare['EMDTicketFareOptions'] ?? null) as $o) {
            if (! empty($o['IsSpecialServiceUserInput']) || ! empty($o['IsAmountUserInput']) || self::isWizard($o['CustomAncillaryData'] ?? null)) {
                continue; // seats come through the seat map; wizards and free amounts are not supported
            }
            $code = (string) ($o['AncillaryCode'] ?? '');
            $props = $ancillaryCodes[$code] ?? [];
            $ancillaries[] = [
                'ref' => (string) $o['Ref'],
                'code' => $code,
                'label' => Js::text($o['Label'] ?? null) ?? ($props['label'] ?? $code),
                'description' => self::plain($o['HtmlDescription'] ?? null),
                'category' => $o['CategoryCode'] ?? null,
                'serviceCode' => $o['AssociatedSpecialServiceCode'] ?? null,
                'price' => 0 + ($o['SaleCurrencyAmount']['TotalAmount'] ?? 0),
                'currency' => $currency,
                'passengerRefs' => array_map('strval', Js::list($o['AppliableRefPassengers'] ?? null)),
                'segmentRefs' => array_map('strval', Js::list($o['AppliableRefSegments'] ?? null)),
                'multiSelect' => (bool) ($o['AllowMultiSelect'] ?? false),
                'maxQuantity' => isset($o['MaxQuantity']) ? (int) $o['MaxQuantity'] : null,
                'refundable' => $props['refundable'] ?? null,
                'exchangeable' => $props['exchangeable'] ?? null,
            ];
        }

        // Seat maps and who needs a seat (infants sit on a lap: no SEAT entry).
        $seatPrices = self::seatPrices($fare);
        $maps = [];
        foreach ($seatMaps as $m) {
            $maps[] = self::seatMap($m, $seatPrices, $ancillaryCodes, $currency);
        }
        $seatSlots = [];
        foreach ($optional as $s) {
            if (($s['Code'] ?? null) === 'SEAT' && isset($s['RefPassenger'], $s['RefSegment'])) {
                $seatSlots[] = ['passengerRef' => (string) $s['RefPassenger'], 'segmentRef' => (string) $s['RefSegment']];
            }
        }

        $fees = array_map(fn ($f) => [
            'label' => Js::text($f['Label'] ?? null) ?? 'Fee',
            'amount' => 0 + ($f['SaleCurrencyAmount']['TotalAmount'] ?? 0),
            'passengerRef' => isset($f['RefPassenger']) ? (string) $f['RefPassenger'] : null,
            'segmentRef' => isset($f['RefSegment']) ? (string) $f['RefSegment'] : null,
        ], Js::list($fare['EMDTicketFares'] ?? null));

        return [
            'currency' => $currency,
            'passengers' => $paxOut,
            'segments' => $segOut,
            'categories' => array_map(fn ($code, $label) => ['code' => $code, 'label' => $label], array_keys(ExtrasCatalog::CATEGORIES), ExtrasCatalog::CATEGORIES),
            'services' => array_values($services),
            'seatMaps' => $maps,
            'seatPassengers' => $seatSlots,
            'ancillaries' => $ancillaries,
            'mandatoryFees' => $booking ? [] : $fees,
            'fareRules' => self::fareRules($fare, $segments),
        ];
    }

    private static function typeLabel(string $type): string
    {
        return match ($type) {
            'AD', 'ADT' => 'Adult',
            'CHD', 'CH' => 'Child',
            'INF', 'IN' => 'Infant',
            default => $type,
        };
    }

    /** @return array<string, string> passenger ref => type */
    private static function passengerTypes(array $passengers): array
    {
        $out = [];
        foreach ($passengers as $p) {
            $out[(string) $p['Ref']] = (string) ($p['PassengerTypeCode'] ?? '');
        }

        return $out;
    }

    private static function isWizard(mixed $data): bool
    {
        return is_string($data) && (str_starts_with($data, '/') || preg_match('#^https?://#i', $data));
    }

    private static function plain(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', $html)))));

        return $text === '' ? null : $text;
    }

    /** Paid seat prices by ancillary code, from what Zenith lists for the booking. */
    private static function seatPrices(array $fare): array
    {
        $prices = [];
        foreach ([...Js::list($fare['EMDTicketFareOptions'] ?? null), ...Js::list($fare['EMDTicketFares'] ?? null)] as $f) {
            $code = (string) ($f['AncillaryCode'] ?? '');
            if ($code !== '' && isset($f['SaleCurrencyAmount']['TotalAmount'])) {
                $prices[$code] ??= 0 + $f['SaleCurrencyAmount']['TotalAmount'];
            }
        }

        return $prices;
    }

    private static function seatMap(array $m, array $prices, array $ancillaryCodes, string $currency): array
    {
        $cabins = [];
        foreach (Js::list($m['Decks'] ?? null) as $deck) {
            foreach (Js::list($deck['Areas'] ?? null) as $area) {
                $rows = [];
                foreach (Js::list($area['Rows'] ?? null) as $row) {
                    $number = $row['RowNumber'] ?? null;
                    $groups = [];
                    foreach (Js::list($row['Columns'] ?? null) as $col) {
                        $groups[] = array_map(function ($s) use ($number, $prices, $ancillaryCodes, $currency) {
                            $anc = $s['AssociatedAncillaryCode'] ?? null;

                            return [
                                'seat' => $number !== null ? $number.$s['Letter'] : null,
                                'letter' => $s['Letter'],
                                'available' => (bool) ($s['IsAvailable'] ?? false),
                                'type' => $s['SeatTypeCode'] ?? null,
                                'takenBy' => isset($s['RefPassenger']) ? (string) $s['RefPassenger'] : null,
                                'chargeable' => $anc !== null && $anc !== '',
                                'ancillaryCode' => $anc ?: null,
                                'label' => $anc ? ($ancillaryCodes[$anc]['label'] ?? null) : null,
                                'color' => $anc ? ($ancillaryCodes[$anc]['color'] ?? null) : null,
                                'price' => $anc ? ($prices[$anc] ?? null) : 0,
                                'currency' => $currency,
                                'notForPassengerTypes' => Js::list($s['PassengerTypeCodeRestrictions'] ?? null),
                                'notWithServices' => Js::list($s['SpecialServiceCodeRestrictions'] ?? null),
                            ];
                        }, Js::list($col['Seats'] ?? null));
                    }
                    $rows[] = [
                        'number' => $number,
                        'exit' => ($row['LeftSideTypeCode'] ?? null) === 'Exit' || ($row['RightSideTypeCode'] ?? null) === 'Exit',
                        'left' => $row['LeftSideTypeCode'] ?? null,
                        'right' => $row['RightSideTypeCode'] ?? null,
                        'groups' => $groups,
                    ];
                }
                $cabins[] = ['name' => $area['Name'] ?? null, 'deck' => $deck['DeckNumber'] ?? 1, 'columns' => Js::list($area['ColumnSizes'] ?? null), 'rows' => $rows];
            }
        }

        return ['segmentRef' => (string) ($m['RefSegment'] ?? ''), 'aircraft' => $m['AircraftName'] ?? null, 'cabins' => $cabins];
    }

    /** Fare rules per origin-destination: change, refund, penalties and the airline's own conditions text. */
    private static function fareRules(array $fare, array $segments): array
    {
        $segmentsOf = [];
        foreach (Js::list($fare['Itineraries'] ?? null) as $it) {
            foreach (Js::list($it['AirOriginDestinations'] ?? null) as $od) {
                $refs = array_map(fn ($c) => (string) $c['RefSegment'], Js::list($od['AirCoupons'] ?? null));
                if (! empty($od['RefFareRule'])) {
                    $segmentsOf[$od['RefFareRule']] = array_values(array_unique([...($segmentsOf[$od['RefFareRule']] ?? []), ...$refs]));
                }
            }
        }
        $penalties = fn ($list) => array_map(fn ($p) => [
            'fromHours' => $p['FromHours'] ?? null,
            'toHours' => $p['ToHours'] ?? null,
            'noShow' => (bool) ($p['IsNoShowPenalty'] ?? false),
            'amount' => 0 + ($p['Amount']['TotalAmount'] ?? 0),
        ], Js::list($list));
        $tree = function ($node) use (&$tree) {
            if (! is_array($node)) {
                return null;
            }

            return [
                'text' => $node['Text'] ?? '',
                'value' => $node['Value'] ?? null,
                'children' => array_values(array_filter(array_map($tree, Js::list($node['Children'] ?? null)))),
            ];
        };

        return array_map(function ($r) use ($segmentsOf, $penalties, $tree) {
            $change = (string) ($r['VoluntaryChangeCode'] ?? '');
            $refund = (string) ($r['VoluntaryRefundCode'] ?? '');

            return [
                'ref' => (string) $r['Ref'],
                'segmentRefs' => $segmentsOf[$r['Ref']] ?? [],
                'change' => ['code' => $change, 'label' => ExtrasCatalog::VOLUNTARY[$change] ?? Str::headline($change)],
                'refund' => ['code' => $refund, 'label' => ExtrasCatalog::VOLUNTARY[$refund] ?? Str::headline($refund)],
                'changePenalties' => $penalties($r['Details']['ExchangePenalties'] ?? null),
                'refundPenalties' => $penalties($r['Details']['RefundPenalties'] ?? null),
                'minimumStayDays' => $r['Details']['MinimumStay'] ?? null,
                'maximumStayDays' => $r['Details']['MaximumStay'] ?? null,
                'conditions' => $tree($r['FareConditionText'] ?? null),
            ];
        }, Js::list($fare['FareRules'] ?? null));
    }

    // ------------------------------------------------------------ selection

    /**
     * Checks a selection against the offer.
     *
     * @param  array{services?: list<array>, seats?: list<array>, ancillaries?: list<array>}  $sel
     * @param  array<string, list<string>>  $heldServices  passenger ref => codes already on the booking (for seat restrictions)
     * @return list<array{passengerRef: ?string, segmentRef: ?string, field: string, message: string}>
     */
    public static function validate(array $offer, array $sel, array $heldServices = []): array
    {
        $problems = [];
        $bad = function (?string $pax, ?string $seg, string $message) use (&$problems): void {
            $problems[] = ['passengerRef' => $pax, 'segmentRef' => $seg, 'field' => 'extras', 'message' => $message];
        };
        $paxType = array_column($offer['passengers'], 'type', 'ref');
        $paxName = fn (?string $ref) => $ref === null ? 'the booking' : (collect($offer['passengers'])->firstWhere('ref', $ref)['name'] ?? collect($offer['passengers'])->firstWhere('ref', $ref)['label'] ?? 'a passenger');
        $services = array_column($offer['services'], null, 'code');

        // Special services.
        $taken = [];
        $groups = [];
        $requested = [];
        foreach ($sel['services'] ?? [] as $s) {
            $code = (string) ($s['code'] ?? '');
            $pax = isset($s['passengerRef']) ? (string) $s['passengerRef'] : null;
            $seg = isset($s['segmentRef']) ? (string) $s['segmentRef'] : null;
            $svc = $services[$code] ?? null;
            if (! $svc || ! in_array(['passengerRef' => $pax, 'segmentRef' => $seg], $svc['available'], true)) {
                $bad($pax, $seg, ($svc['label'] ?? $code).' is not available for '.$paxName($pax).' on this flight.');

                continue;
            }
            $key = "{$code}|{$pax}|{$seg}";
            if (isset($taken[$key])) {
                $bad($pax, $seg, "{$svc['label']} is selected twice.");

                continue;
            }
            $taken[$key] = true;
            if ($group = ExtrasCatalog::exclusiveGroup($code)) {
                $gk = "{$group}|{$pax}|{$seg}";
                if (isset($groups[$gk])) {
                    $what = ['meal' => 'one meal', 'wheelchair' => 'one wheelchair type', 'seatPreference' => 'one seat preference'][$group];
                    $bad($pax, $seg, "Choose only {$what} for ".$paxName($pax).' on each flight.');
                }
                $groups[$gk] = true;
            }
            $text = ExtrasCatalog::cleanText($s['text'] ?? null);
            if ($svc['needsText'] && $text === null) {
                $bad($pax, $seg, "{$svc['textLabel']} for {$svc['label']}.");
            }
            if ($pax !== null) {
                $requested[$pax][] = $code;
            }
        }

        // Seats.
        $seatByCode = [];
        foreach ($offer['seatMaps'] as $map) {
            foreach ($map['cabins'] as $cabin) {
                foreach ($cabin['rows'] as $row) {
                    foreach ($row['groups'] as $g) {
                        foreach ($g as $seat) {
                            if ($seat['seat'] !== null) {
                                $seatByCode[$map['segmentRef'].'|'.$seat['seat']] = $seat;
                            }
                        }
                    }
                }
            }
        }
        $usedSeats = [];
        $seatFor = [];
        foreach ($sel['seats'] ?? [] as $s) {
            $pax = (string) ($s['passengerRef'] ?? '');
            $seg = (string) ($s['segmentRef'] ?? '');
            $code = strtoupper(trim((string) ($s['seat'] ?? '')));
            if (! in_array(['passengerRef' => $pax, 'segmentRef' => $seg], $offer['seatPassengers'], true)) {
                $bad($pax ?: null, $seg ?: null, 'A seat cannot be chosen for '.$paxName($pax ?: null).' on this flight.');

                continue;
            }
            if (isset($seatFor["{$pax}|{$seg}"])) {
                $bad($pax, $seg, 'Choose one seat per passenger per flight.');

                continue;
            }
            $seatFor["{$pax}|{$seg}"] = true;
            $seat = $seatByCode["{$seg}|{$code}"] ?? null;
            if (! $seat) {
                $bad($pax, $seg, "Seat {$code} does not exist on this flight.");

                continue;
            }
            if (! $seat['available'] && $seat['takenBy'] !== $pax) {
                $bad($pax, $seg, "Seat {$code} is no longer available.");

                continue;
            }
            if (isset($usedSeats["{$seg}|{$code}"])) {
                $bad($pax, $seg, "Seat {$code} is chosen for two passengers.");

                continue;
            }
            $usedSeats["{$seg}|{$code}"] = true;
            if (in_array($paxType[$pax] ?? '', $seat['notForPassengerTypes'], true)) {
                $bad($pax, $seg, "Seat {$code} is not allowed for ".$paxName($pax).'.');
            }
            $held = [...($heldServices[$pax] ?? []), ...($requested[$pax] ?? [])];
            if ($hit = array_values(array_intersect($held, $seat['notWithServices']))) {
                $bad($pax, $seg, "Seat {$code} cannot be used with the ".implode(', ', $hit).' service (for example an exit row).');
            }
            if ($seat['chargeable'] && $seat['price'] === null) {
                $bad($pax, $seg, "Seat {$code} cannot be booked online. Please choose another seat.");
            }
        }

        // Paid extras.
        $anc = array_column($offer['ancillaries'], null, 'ref');
        $count = [];
        foreach ($sel['ancillaries'] ?? [] as $a) {
            $o = $anc[(string) ($a['ref'] ?? '')] ?? null;
            $pax = isset($a['passengerRef']) ? (string) $a['passengerRef'] : null;
            $seg = isset($a['segmentRef']) ? (string) $a['segmentRef'] : null;
            $qty = (int) ($a['quantity'] ?? 1);
            if (! $o) {
                $bad($pax, $seg, 'An extra you chose is no longer offered. Please review your extras.');

                continue;
            }
            if (($o['passengerRefs'] ? ! in_array($pax, $o['passengerRefs'], true) : $pax !== null)
                || ($o['segmentRefs'] ? ! in_array($seg, $o['segmentRefs'], true) : $seg !== null)) {
                $bad($pax, $seg, "{$o['label']} is not available for ".$paxName($pax).' on this flight.');

                continue;
            }
            $max = $o['multiSelect'] ? ($o['maxQuantity'] ?: self::MULTI_MAX) : 1;
            if ($qty < 1 || $qty > $max) {
                $bad($pax, $seg, "{$o['label']}: choose between 1 and {$max}.");

                continue;
            }
            $count[$o['ref']] = ($count[$o['ref']] ?? 0) + $qty;
            $scope = "{$o['ref']}|{$pax}|{$seg}";
            if (isset($count[$scope])) {
                $bad($pax, $seg, "{$o['label']} is selected twice.");
            }
            $count[$scope] = 1;
            if ($o['maxQuantity'] !== null && $count[$o['ref']] > $o['maxQuantity']) {
                $bad($pax, $seg, "Only {$o['maxQuantity']} × {$o['label']} can be added to this booking.");
            }
        }

        return $problems;
    }

    /** What the selection adds to the price. */
    public static function total(array $offer, array $sel): float
    {
        $total = 0.0;
        $anc = array_column($offer['ancillaries'], null, 'ref');
        foreach ($sel['ancillaries'] ?? [] as $a) {
            $total += ($anc[$a['ref'] ?? '']['price'] ?? 0) * max(1, (int) ($a['quantity'] ?? 1));
        }
        foreach ($sel['seats'] ?? [] as $s) {
            foreach ($offer['seatMaps'] as $map) {
                if ($map['segmentRef'] !== (string) ($s['segmentRef'] ?? '')) {
                    continue;
                }
                foreach ($map['cabins'] as $c) {
                    foreach ($c['rows'] as $r) {
                        foreach ($r['groups'] as $g) {
                            foreach ($g as $seat) {
                                if ($seat['seat'] === strtoupper((string) ($s['seat'] ?? ''))) {
                                    $total += (float) ($seat['price'] ?? 0);
                                }
                            }
                        }
                    }
                }
            }
        }

        return Js::round2($total);
    }

    /**
     * The Zenith entries for a (validated) selection: SSRs filled from the
     * templates Zenith pre-instantiated, ancillaries mutated from their
     * options. $source is the PrepareFlights / PrepareBookingModification
     * response the offer came from.
     *
     * @return array{SpecialServices: list<array>, EMDTicketFares: list<array>}
     */
    public static function toZenith(array $source, array $sel): array
    {
        $optional = Js::list($source['OptionalSpecialServices'] ?? null);
        $template = function (string $code, ?string $pax, ?string $seg) use ($optional): ?array {
            foreach ($optional as $s) {
                if (($s['Code'] ?? null) === $code
                    && (isset($s['RefPassenger']) ? (string) $s['RefPassenger'] : null) === $pax
                    && (isset($s['RefSegment']) ? (string) $s['RefSegment'] : null) === $seg) {
                    return $s;
                }
            }

            return null;
        };

        $ssrs = [];
        foreach ($sel['services'] ?? [] as $s) {
            $t = $template((string) $s['code'], $s['passengerRef'] ?? null, $s['segmentRef'] ?? null);
            if (! $t) {
                continue;
            }
            if (ExtrasCatalog::needsText($t['Code'])) {
                $t['Text'] = ExtrasCatalog::cleanText($s['text'] ?? null);
            }
            unset($t['Status'], $t['Available']);
            $ssrs[] = $t;
        }
        foreach ($sel['seats'] ?? [] as $s) {
            $t = $template('SEAT', (string) $s['passengerRef'], (string) $s['segmentRef']);
            if (! $t || ! preg_match('/^(\d+)([A-Z])$/', strtoupper((string) $s['seat']), $m)) {
                continue;
            }
            $data = is_array($t['Data'] ?? null) ? $t['Data'] : [];
            $data['Seat'] = [...(is_array($data['Seat'] ?? null) ? $data['Seat'] : []), 'SeatRow' => (int) $m[1], 'SeatLetter' => $m[2]];
            $t['Data'] = $data;
            // A held seat's template carries it as text ("5B"): Zenith wants
            // text and data to agree, so the text names the new seat too.
            $t['Text'] = $m[1].$m[2];
            unset($t['Status'], $t['Available']);
            $ssrs[] = $t;
        }

        $options = [];
        foreach (Js::list($source['FareInfo']['EMDTicketFareOptions'] ?? null) as $o) {
            $options[(string) $o['Ref']] = $o;
        }
        $emds = [];
        foreach ($sel['ancillaries'] ?? [] as $a) {
            $o = $options[(string) ($a['ref'] ?? '')] ?? null;
            if (! $o) {
                continue;
            }
            for ($i = 0; $i < max(1, (int) ($a['quantity'] ?? 1)); $i++) {
                $emds[] = array_filter([
                    'RefPassenger' => $a['passengerRef'] ?? null,
                    'RefSegment' => $a['segmentRef'] ?? null,
                    'AncillaryCode' => $o['AncillaryCode'] ?? null,
                    'Label' => $o['Label'] ?? null,
                    'AssociatedSpecialServiceCode' => $o['AssociatedSpecialServiceCode'] ?? null,
                    'RefItinerary' => $o['RefItinerary'] ?? null,
                    // Dynamic pricing data must be passed back untouched.
                    'CustomAncillaryData' => $o['CustomAncillaryData'] ?? null,
                    'SaleCurrencyAmount' => $o['SaleCurrencyAmount'] ?? null,
                    'Ref' => (string) Str::uuid(),
                ], fn ($v) => $v !== null);
            }
        }

        return ['SpecialServices' => $ssrs, 'EMDTicketFares' => $emds];
    }

    // ------------------------------------------------------------ on the booking

    /**
     * What the booking already holds: special services (with the airline's
     * answer), seats, and paid extras (issued = paid, cannot be removed).
     *
     * @return array{services: list<array>, seats: list<array>, ancillaries: list<array>, total: float, segments: list<array>}
     */
    public static function current(array $booking): array
    {
        $services = [];
        $seats = [];
        foreach (Js::list($booking['SpecialServices'] ?? null) as $s) {
            $code = (string) ($s['Code'] ?? '');
            $pax = isset($s['RefPassenger']) ? (string) $s['RefPassenger'] : null;
            $seg = isset($s['RefSegment']) ? (string) $s['RefSegment'] : null;
            [$state, $label] = ExtrasCatalog::status($s['Status'] ?? null);
            if ($code === 'SEAT') {
                $seat = null;
                if (is_array($s['Data']['Seat'] ?? null) && ! empty($s['Data']['Seat']['SeatRow'])) {
                    $seat = $s['Data']['Seat']['SeatRow'].strtoupper((string) ($s['Data']['Seat']['SeatLetter'] ?? ''));
                } elseif (preg_match('/(\d{1,3})\s*([A-Z])/i', (string) ($s['Text'] ?? ''), $m)) {
                    $seat = $m[1].strtoupper($m[2]);
                }
                if ($seat) {
                    $seats[] = ['passengerRef' => $pax, 'segmentRef' => $seg, 'seat' => $seat, 'status' => $state, 'statusLabel' => $label];
                }

                continue;
            }
            if (! ExtrasCatalog::offered($code) || $state === 'cancelled') {
                continue;
            }
            $services[] = [
                ...ExtrasCatalog::describe($code),
                'passengerRef' => $pax,
                'segmentRef' => $seg,
                'text' => Js::text($s['Text'] ?? null),
                'status' => $state,
                'statusLabel' => $label,
            ];
        }

        $issued = [];
        foreach (Js::list($booking['TicketInfo']['EMDTickets'] ?? null) as $t) {
            if (! empty($t['RefEMDTicketFare'])) {
                $issued[(string) $t['RefEMDTicketFare']] = $t['DocumentNumber'] ?? true;
            }
        }
        $ancillaries = [];
        $total = 0.0;
        foreach (Js::list($booking['FareInfo']['EMDTicketFares'] ?? null) as $f) {
            $ref = (string) ($f['Ref'] ?? '');
            $paid = isset($issued[$ref]);
            $amount = 0 + ($f['SaleCurrencyAmount']['TotalAmount'] ?? 0);
            $total += $amount;
            $ancillaries[] = [
                'ref' => $ref,
                'code' => $f['AncillaryCode'] ?? null,
                'label' => Js::text($f['Label'] ?? null) ?? 'Extra',
                'serviceCode' => $f['AssociatedSpecialServiceCode'] ?? null,
                'price' => $amount,
                'passengerRef' => isset($f['RefPassenger']) ? (string) $f['RefPassenger'] : null,
                'segmentRef' => isset($f['RefSegment']) ? (string) $f['RefSegment'] : null,
                'paid' => $paid,
                'emdNumber' => is_string($issued[$ref] ?? null) ? $issued[$ref] : null,
                'removable' => ! $paid,
            ];
        }

        return [
            'services' => $services,
            'seats' => $seats,
            'ancillaries' => $ancillaries,
            'total' => Js::round2($total),
            // To name the flight each item is on.
            'segments' => array_map(fn ($s) => [
                'ref' => (string) $s['Ref'],
                'origin' => $s['OriginCode'] ?? null,
                'destination' => $s['DestinationCode'] ?? null,
            ], Js::list($booking['Segments'] ?? null)),
        ];
    }
}
