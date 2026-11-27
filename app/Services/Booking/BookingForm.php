<?php

namespace App\Services\Booking;

use App\Services\Support\Js;
use App\Services\Support\Phones;

/**
 * The passenger form for a booking, built from what PrepareFlights returns.
 *
 * Zenith decides which passenger data is mandatory (route, passenger type,
 * government APIS rules) and lists it as special services (SSRs) with the
 * exact properties to fill in Data.Fields. Nothing here hardcodes a required
 * field: we only know how to label and write each kind of SSR. An SSR never
 * seen before still gets a plain text field, so a new airline rule never
 * blocks bookings.
 */
final class BookingForm
{
    // Data object name inside SpecialService.Data for each structured SSR.
    private const DATA_OBJECT = [
        'EXT-ADOB' => 'Adof', 'CHLD' => 'Chld', 'INFT' => 'Inft', 'UMNR' => 'Umnr',
        'DOCS' => 'Docs', 'FOID' => 'Foid', 'DOCO' => 'Doco', 'DOCA' => 'Doca', 'FQTV' => 'Fqtv',
        'EXT-ECTC' => 'Ectc', 'EXT-PCTC' => 'Pctc', 'EXT-BILL' => 'Bill',
    ];

    private const BIRTH_SSRS = ['EXT-ADOB', 'CHLD', 'INFT', 'UMNR'];

    // Text SSRs we know how to collect.
    private const TEXT_SSRS = [
        'CTCE' => ['key' => 'email', 'label' => 'Email', 'kind' => 'email'],
        'CTCM' => ['key' => 'mobilePhone', 'label' => 'Mobile phone', 'kind' => 'phone'],
        'CTCH' => ['key' => 'homePhone', 'label' => 'Home phone', 'kind' => 'phone'],
        'CTCF' => ['key' => 'fax', 'label' => 'Fax', 'kind' => 'phone'],
        'PWGT' => ['key' => 'weight', 'label' => 'Weight (kg)', 'kind' => 'number'],
        'RFTV' => ['key' => 'reasonForTravel', 'label' => 'Reason for travel', 'kind' => 'text'],
    ];

    // Optional SSRs worth offering in the form; others (seats, meals,
    // remarks, device ids...) are not passenger data.
    public const OFFERED_OPTIONAL = ['CTCE', 'CTCM', 'CTCH', 'EXT-ADOB'];

    // Travel document properties ("Documents[0].X") -> form key.
    public const DOC_KEYS = [
        'DocumentTypeCode' => ['key' => 'docType', 'label' => 'Document type', 'kind' => 'select'],
        'DocumentNumber' => ['key' => 'docNumber', 'label' => 'Document number', 'kind' => 'text'],
        'NationalityCountryCode' => ['key' => 'nationality', 'label' => 'Nationality', 'kind' => 'country'],
        'IssueCountryCode' => ['key' => 'docIssueCountry', 'label' => 'Issuing country', 'kind' => 'country'],
        'DocumentIssuanceDate' => ['key' => 'docIssueDate', 'label' => 'Date of issue', 'kind' => 'date'],
        'DocumentExpiryDate' => ['key' => 'docExpiry', 'label' => 'Expiry date', 'kind' => 'date'],
        'DateOfBirth' => ['key' => 'dateOfBirth', 'label' => 'Date of birth', 'kind' => 'date'],
        'Gender' => ['key' => 'gender', 'label' => 'Gender', 'kind' => 'select'],
    ];

    private const FIELD_ORDER = [
        'title', 'firstName', 'lastName', 'gender', 'dateOfBirth', 'email', 'mobilePhone', 'homePhone', 'fax',
        'nationality', 'docType', 'docNumber', 'docIssueCountry', 'docIssueDate', 'docExpiry', 'placeOfBirth',
    ];

    private const NAME = '/^[A-Za-z][A-Za-z\' -]*$/';

    private const EMAIL = '/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/';

    private const DOC_NUMBER = '/^[A-Za-z0-9]{3,20}$/';

    /** Gender implied by an adult title; null when the title doesn't say. */
    public static function genderForTitle(?string $title): ?string
    {
        return match (strtoupper((string) $title)) {
            'MR', 'MSTR' => 'M',
            'MRS', 'MS', 'MISS' => 'F',
            default => null,
        };
    }

    private static function humanize(string $s): string
    {
        $s = preg_replace('/([a-z])([A-Z])/', '$1 $2', $s);
        $s = trim(preg_replace('/Code$/', '', $s));

        return ucfirst($s);
    }

    private static function kindFor(string $prop): string
    {
        return match (true) {
            (bool) preg_match('/Date$/', $prop) => 'date',
            (bool) preg_match('/CountryCode$/', $prop) => 'country',
            (bool) preg_match('/Email$/', $prop) => 'email',
            (bool) preg_match('/Phone|PhoneNumber|FaxNumber/', $prop) => 'phone',
            default => 'text',
        };
    }

    public static function isAdultType(string $type): bool
    {
        return ! in_array($type, ['CHD', 'INF', 'INS', 'UM'], true);
    }

    /**
     * Age limits for a passenger type. Zenith gives adults no minimum age, so
     * an adult must at least be past the oldest child/infant age limit (12).
     *
     * @return array{min: int|float|null, max: int|float|null}
     */
    public static function ageLimits(string $type, array $ref): array
    {
        $t = null;
        foreach ($ref['passengerTypes'] as $x) {
            if ($x['code'] === $type) {
                $t = $x;
                break;
            }
        }
        $min = $t['minAge'] ?? null;
        if ($min === null && self::isAdultType($type)) {
            $childMax = [];
            foreach ($ref['passengerTypes'] as $x) {
                if (! self::isAdultType($x['code']) && $x['maxAge'] !== null) {
                    $childMax[] = $x['maxAge'];
                }
            }
            if ($childMax) {
                $min = max($childMax);
            }
        }

        return ['min' => $min, 'max' => $t['maxAge'] ?? null];
    }

    /** Whole years between $birth and $on, both YYYY-MM-DD. */
    public static function ageOn(string $birth, string $on): int
    {
        [$by, $bm, $bd] = array_map('intval', explode('-', $birth));
        [$y, $m, $d] = array_map('intval', explode('-', $on));

        return $y - $by - ($m < $bm || ($m === $bm && $d < $bd) ? 1 : 0);
    }

    private static function validDate(string $s): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $s));

        return checkdate($m, $d, $y);
    }

    // ------------------------------------------------------------ building

    /** @return array{passengers: list<array>, bookingFields: list<array>, travel: array{firstDate: string, lastDate: string}} */
    public static function build(array $prep, array $ref, array $offered = self::OFFERED_OPTIONAL): array
    {
        $passengers = Js::list($prep['Passengers'] ?? null);
        $mandatory = Js::list($prep['SpecialServices'] ?? null);
        $optional = Js::list($prep['OptionalSpecialServices'] ?? null);
        $typeLabel = function (string $code) use ($ref): string {
            $label = $code;
            foreach ($ref['passengerTypes'] as $t) {
                if ($t['code'] === $code) {
                    $label = $t['label'];
                    break;
                }
            }

            return trim(preg_replace('/\(s\)|\(ren\)/', '', $label));
        };
        $counters = [];

        $forms = [];
        foreach ($passengers as $p) {
            $type = (string) $p['PassengerTypeCode'];
            $fields = [];
            $put = function (array $f) use (&$fields): void {
                if (! isset($fields[$f['key']])) {
                    $fields[$f['key']] = $f;
                } elseif ($f['required'] && ! $fields[$f['key']]['required']) {
                    $fields[$f['key']]['required'] = true;
                }
            };
            $adult = self::isAdultType($type);
            if ($adult) {
                $put(['key' => 'title', 'label' => 'Title', 'kind' => 'select', 'required' => true, 'options' => $ref['titles']]);
            }
            $put(['key' => 'firstName', 'label' => 'First name', 'kind' => 'text', 'required' => true, 'hint' => 'As shown on the travel document']);
            $put(['key' => 'lastName', 'label' => 'Surname', 'kind' => 'text', 'required' => true, 'hint' => 'As shown on the travel document']);
            // Children and infants have no gender-bearing title, and Zenith needs one.
            if (! $adult) {
                $put(['key' => 'gender', 'label' => 'Gender', 'kind' => 'select', 'required' => true, 'options' => $ref['genders']]);
            }

            $ssrs = [];
            foreach ($mandatory as $s) {
                if (($s['RefPassenger'] ?? null) === $p['Ref']) {
                    $ssrs[] = [$s, true];
                }
            }
            foreach ($optional as $s) {
                if (($s['RefPassenger'] ?? null) === $p['Ref'] && in_array($s['Code'], $offered, true)) {
                    $ssrs[] = [$s, false];
                }
            }

            foreach ($ssrs as [$s, $required]) {
                $code = $s['Code'];
                $fieldsList = Js::list($s['Data']['Fields'] ?? null);
                if (isset(self::TEXT_SSRS[$code])) {
                    $t = self::TEXT_SSRS[$code];
                    $put(['key' => $t['key'], 'label' => $t['label'], 'kind' => $t['kind'], 'required' => $required]);
                } elseif (in_array($code, self::BIRTH_SSRS, true)) {
                    $put(['key' => 'dateOfBirth', 'label' => 'Date of birth', 'kind' => 'date', 'required' => $required]);
                } elseif ($code === 'DOCS' || $code === 'FOID') {
                    $allowed = Js::list($s['Data']['Docs']['AllowedDocumentTypeCodes'] ?? null);
                    $docOptions = array_values(array_filter($ref['documentTypes'], fn ($d) => ! $allowed || in_array($d['code'], $allowed, true)));
                    foreach ($fieldsList as $field) {
                        if (! preg_match('/^Documents\[(\d+)\]\.(\w+)$/', (string) $field, $m) || $m[1] !== '0') {
                            continue; // a second document is never required in practice
                        }
                        $d = self::DOC_KEYS[$m[2]] ?? null;
                        if (! $d) {
                            $put(['key' => "doc.{$m[2]}", 'label' => self::humanize($m[2]), 'kind' => self::kindFor($m[2]), 'required' => $required]);

                            continue;
                        }
                        if ($d['key'] === 'gender') {
                            if (! $adult) {
                                $put(['key' => 'gender', 'label' => 'Gender', 'kind' => 'select', 'required' => $required, 'options' => $ref['genders']]);
                            }

                            continue; // adults: from the title
                        }
                        $put([...$d, 'required' => $required, ...($d['key'] === 'docType' ? ['options' => $docOptions] : [])]);
                    }
                } elseif ($code === 'DOCO') {
                    foreach ($fieldsList as $field) {
                        $put($field === 'PlaceOfBirth'
                            ? ['key' => 'placeOfBirth', 'label' => 'Place of birth', 'kind' => 'text', 'required' => $required]
                            : ['key' => "doco.{$field}", 'label' => self::humanize($field), 'kind' => self::kindFor($field), 'required' => $required]);
                    }
                } elseif ($code === 'DOCA') {
                    $addressType = (string) ($s['Data']['Doca']['AddressTypeCode'] ?? 'Address');
                    foreach ($fieldsList as $field) {
                        $put([
                            'key' => "doca.{$addressType}.{$field}",
                            'label' => self::humanize($addressType).' address: '.mb_strtolower(self::humanize($field)),
                            'kind' => self::kindFor($field),
                            'required' => $required,
                        ]);
                    }
                } elseif ($required) {
                    // Unknown mandatory SSR: still collectable as text.
                    $put(['key' => "ssr.{$code}", 'label' => self::humanize($code), 'kind' => 'text', 'required' => true]);
                }
            }

            // When both phones are required, the home phone may repeat the mobile.
            if (isset($fields['homePhone'], $fields['mobilePhone'])) {
                $fields['homePhone']['required'] = false;
                $fields['homePhone']['hint'] = 'Leave empty to use the mobile number';
            }
            $counters[$type] = ($counters[$type] ?? 0) + 1;
            $ordered = array_values($fields);
            $rank = fn (array $f) => ($i = array_search($f['key'], self::FIELD_ORDER, true)) === false ? 99 : $i + 1;
            usort($ordered, fn ($a, $b) => $rank($a) <=> $rank($b));
            $forms[] = ['ref' => $p['Ref'], 'type' => $type, 'label' => $typeLabel($type).' '.$counters[$type], 'fields' => $ordered];
        }

        $bookingFields = [];
        foreach ($mandatory as $s) {
            if (! empty($s['RefPassenger'])) {
                continue;
            }
            $code = $s['Code'];
            $obj = self::DATA_OBJECT[$code] ?? null;
            $props = Js::list($s['Data']['Fields'] ?? null);
            if (! $props && $obj) {
                $props = array_values(array_filter(array_keys(Js::list($s['Data'][$obj] ?? null)), fn ($k) => $k !== 'Extensions'));
            }
            $short = self::humanize(str_replace('EXT-', '', $code));
            if ($obj && $props) {
                foreach ($props as $prop) {
                    $bookingFields[] = ['key' => "booking.{$code}.{$prop}", 'label' => "{$short}: ".mb_strtolower(self::humanize($prop)), 'kind' => self::kindFor($prop), 'required' => true];
                }
            } else {
                $bookingFields[] = ['key' => "booking.{$code}", 'label' => $short, 'kind' => 'text', 'required' => true];
            }
        }

        $dates = [];
        foreach (Js::list($prep['Segments'] ?? null) as $s) {
            $date = substr((string) ($s['FlightInfo']['DepartureDate'] ?? ''), 0, 10);
            if ($date !== '') {
                $dates[] = $date;
            }
        }
        sort($dates, SORT_STRING);
        $today = Js::today();

        return [
            'passengers' => $forms,
            'bookingFields' => $bookingFields,
            'travel' => ['firstDate' => $dates[0] ?? $today, 'lastDate' => $dates[count($dates) - 1] ?? $today],
        ];
    }

    // ------------------------------------------------------------ validation

    /**
     * @param  array{passengers: list<array{ref: string, values: array}>, booking?: array|null}  $input
     * @return list<array{passengerRef: ?string, field: ?string, message: string}>
     */
    public static function validate(array $form, array $input, array $ref, array $countries, ?string $today = null): array
    {
        $today ??= Js::today();
        $problems = [];
        $add = function (?string $passengerRef, ?string $field, string $message) use (&$problems): void {
            $problems[] = ['passengerRef' => $passengerRef, 'field' => $field, 'message' => $message];
        };

        $byRef = [];
        foreach ($input['passengers'] as $p) {
            $byRef[$p['ref']] = $p['values'] ?? [];
        }
        $formRefs = array_column($form['passengers'], 'ref');
        foreach ($input['passengers'] as $p) {
            if (! in_array($p['ref'], $formRefs, true)) {
                $add(null, null, 'Unknown passenger in the request.');
            }
        }

        $names = [];
        foreach ($form['passengers'] as $pf) {
            if (! array_key_exists($pf['ref'], $byRef)) {
                $add($pf['ref'], null, "Enter the details for {$pf['label']}.");

                continue;
            }
            $v = $byRef[$pf['ref']];
            $limits = self::ageLimits($pf['type'], $ref);
            foreach ($pf['fields'] as $f) {
                $raw = $v[$f['key']] ?? null;
                $value = is_string($raw) ? trim($raw) : '';
                if ($value === '') {
                    if ($f['required']) {
                        $add($pf['ref'], $f['key'], "{$f['label']} is required.");
                    }

                    continue;
                }
                if (mb_strlen($value) > 60) {
                    $add($pf['ref'], $f['key'], "{$f['label']} is too long.");

                    continue;
                }
                $options = array_column($f['options'] ?? [], 'code');
                switch ($f['kind']) {
                    case 'email':
                        if (! preg_match(self::EMAIL, $value)) {
                            $add($pf['ref'], $f['key'], 'Enter a valid email address.');
                        }
                        break;
                    case 'phone':
                        // Valid for its country; the mobile must take text messages.
                        $country = is_string($v[$f['key'].'Country'] ?? null) ? $v[$f['key'].'Country'] : null;
                        $phone = Phones::parse($value, $country);
                        if (! $phone) {
                            $add($pf['ref'], $f['key'], 'Enter a valid '.mb_strtolower($f['label']).' number with its country code.');
                        } elseif ($f['key'] === 'mobilePhone' && $phone['landline']) {
                            $add($pf['ref'], $f['key'], 'This is a landline number. Enter a mobile number so the airline can text you about your flight.');
                        }
                        break;
                    case 'number':
                        if (! preg_match('/^\d{1,3}(\.\d)?$/', $value)) {
                            $add($pf['ref'], $f['key'], "{$f['label']} must be a number.");
                        }
                        break;
                    case 'select':
                        if (! in_array($value, $options, true)) {
                            $add($pf['ref'], $f['key'], 'Choose a valid '.mb_strtolower($f['label']).'.');
                        }
                        break;
                    case 'country':
                        if (! isset($countries[$value])) {
                            $add($pf['ref'], $f['key'], 'Choose a valid '.mb_strtolower($f['label']).'.');
                        }
                        break;
                    case 'date':
                        if (! self::validDate($value)) {
                            $add($pf['ref'], $f['key'], "{$f['label']} is not a valid date.");
                        }
                        break;
                }
                if (($f['key'] === 'firstName' || $f['key'] === 'lastName') && (! preg_match(self::NAME, $value) || mb_strlen($value) > 30)) {
                    $add($pf['ref'], $f['key'], 'Use English letters only, exactly as on the travel document.');
                }
                if ($f['key'] === 'docNumber' && ! preg_match(self::DOC_NUMBER, $value)) {
                    $add($pf['ref'], $f['key'], 'Use letters and numbers only, as on the document.');
                }
            }

            $typeName = preg_replace('/ \d+$/', '', $pf['label']);
            $dob = trim((string) ($v['dateOfBirth'] ?? ''));
            if ($dob !== '' && self::validDate($dob)) {
                if ($dob > $today) {
                    $add($pf['ref'], 'dateOfBirth', 'Date of birth cannot be in the future.');
                } elseif ($dob > $form['travel']['firstDate']) {
                    $add($pf['ref'], 'dateOfBirth', 'The passenger must be born before the flight.');
                } else {
                    if ($limits['min'] !== null && self::ageOn($dob, $form['travel']['firstDate']) < $limits['min']) {
                        $add($pf['ref'], 'dateOfBirth', "{$typeName} passengers must be at least {$limits['min']} on the day of travel.");
                    }
                    if ($limits['max'] !== null && self::ageOn($dob, $form['travel']['lastDate']) >= $limits['max']) {
                        $add($pf['ref'], 'dateOfBirth', "{$typeName} passengers must be under {$limits['max']} for the whole trip.");
                    }
                }
            }
            $expiry = trim((string) ($v['docExpiry'] ?? ''));
            if ($expiry !== '' && self::validDate($expiry) && $expiry <= $form['travel']['lastDate']) {
                $add($pf['ref'], 'docExpiry', 'The document must still be valid after your last flight.');
            }
            $issued = trim((string) ($v['docIssueDate'] ?? ''));
            if ($issued !== '' && self::validDate($issued) && $issued > $today) {
                $add($pf['ref'], 'docIssueDate', 'Date of issue cannot be in the future.');
            }

            $fullName = strtoupper(trim((string) ($v['firstName'] ?? ''))).' '.strtoupper(trim((string) ($v['lastName'] ?? '')));
            if (trim($fullName) !== '') {
                if (isset($names[$fullName])) {
                    $add($pf['ref'], 'firstName', 'Each passenger must have a different name.');
                }
                $names[$fullName] = $pf['ref'];
            }
        }

        $adults = count(array_filter($form['passengers'], fn ($p) => self::isAdultType($p['type'])));
        $infants = count(array_filter($form['passengers'], fn ($p) => $p['type'] === 'INF'));
        if ($infants > $adults) {
            $add(null, null, 'Each infant must travel on the lap of a different adult.');
        }

        foreach ($form['bookingFields'] as $f) {
            $value = trim((string) ($input['booking'][$f['key']] ?? ''));
            if ($value === '') {
                if ($f['required']) {
                    $add(null, $f['key'], "{$f['label']} is required.");
                }

                continue;
            }
            if ($f['kind'] === 'email' && ! preg_match(self::EMAIL, $value)) {
                $add(null, $f['key'], 'Enter a valid email address.');
            }
            if ($f['kind'] === 'phone' && ! Phones::parse($value)) {
                $add(null, $f['key'], 'Enter a valid phone number with its country code.');
            }
            if ($f['kind'] === 'country' && ! isset($countries[$value])) {
                $add(null, $f['key'], 'Choose a valid country.');
            }
            if ($f['kind'] === 'date' && ! self::validDate($value)) {
                $add(null, $f['key'], "{$f['label']} is not a valid date.");
            }
        }

        return $problems;
    }

    // ------------------------------------------------------------ writing back to Zenith

    private static function zDate(string $d): string
    {
        return "{$d}T00:00:00";
    }

    /**
     * Fills the PrepareFlights passengers and SSRs with the validated values,
     * ready for CreateBooking. Mandatory SSRs are always sent; optional ones
     * only when the customer filled them.
     *
     * @return array{Passengers: list<array>, SpecialServices: list<array>}
     */
    public static function apply(array $prep, array $form, array $input, array $offered = self::OFFERED_OPTIONAL): array
    {
        $passengers = Js::list($prep['Passengers'] ?? null);
        $byRef = [];
        foreach ($input['passengers'] as $p) {
            $byRef[$p['ref']] = $p['values'] ?? [];
        }
        $booking = is_array($input['booking'] ?? null) ? $input['booking'] : [];
        $val = function (?string $ref, string $key) use ($byRef, $booking): ?string {
            $v = $ref !== null ? ($byRef[$ref][$key] ?? null) : ($booking[$key] ?? null);

            return is_string($v) && trim($v) !== '' ? trim($v) : null;
        };

        // Infants sit on an adult's lap: pair them in order.
        $adults = array_values(array_map(fn ($p) => $p['Ref'], array_filter($passengers, fn ($p) => self::isAdultType((string) $p['PassengerTypeCode']))));
        $lapOf = [];
        $i = 0;
        foreach ($passengers as $p) {
            if ($p['PassengerTypeCode'] === 'INF') {
                if (isset($adults[$i])) {
                    $lapOf[$p['Ref']] = $adults[$i];
                }
                $i++;
            }
        }

        foreach ($passengers as &$p) {
            $p['NameElement'] = [
                ...(is_array($p['NameElement'] ?? null) ? $p['NameElement'] : []),
                'CivilityCode' => self::isAdultType((string) $p['PassengerTypeCode']) ? $val($p['Ref'], 'title') : null,
                'Firstname' => $val($p['Ref'], 'firstName'),
                'Surname' => $val($p['Ref'], 'lastName'),
            ];
        }
        unset($p);
        $passengerByRef = [];
        foreach ($passengers as $p) {
            $passengerByRef[$p['Ref']] = $p;
        }

        $fill = function (array &$s) use ($val, $lapOf, $passengerByRef, $booking): bool {
            $r = $s['RefPassenger'] ?? null;
            $r = $r === null ? null : (string) $r;
            $p = $r !== null ? ($passengerByRef[$r] ?? null) : null;
            $code = $s['Code'];
            $obj = self::DATA_OBJECT[$code] ?? null;
            $data = is_array($s['Data'] ?? null) ? $s['Data'] : [];

            if (isset(self::TEXT_SSRS[$code])) {
                $key = self::TEXT_SSRS[$code]['key'];
                $text = $val($r, $key);
                if (! $text && $code === 'CTCH') {
                    // No home phone: the mobile, with the mobile's country.
                    $key = 'mobilePhone';
                    $text = $val($r, $key);
                }
                if (! $text) {
                    return false;
                }
                // Phones reach Zenith in E.164 ("+919899765000").
                if (self::TEXT_SSRS[$code]['kind'] === 'phone') {
                    $text = Phones::normalize($text, $val($r, "{$key}Country"));
                }
                $s['Text'] = $text;

                return true;
            }
            if (in_array($code, self::BIRTH_SSRS, true)) {
                $dob = $val($r, 'dateOfBirth');
                if (! $dob) {
                    return false;
                }
                $inner = is_array($data[$obj] ?? null) ? $data[$obj] : [];
                $inner['DateOfBirth'] = self::zDate($dob);
                if ($code === 'INFT' && $r !== null && isset($lapOf[$r])) {
                    $inner['RefPassengerWithSeat'] = $lapOf[$r];
                }
                $data[$obj] = $inner;
                $s['Data'] = $data;

                return true;
            }
            if ($code === 'DOCS') {
                $doc = [];
                $set = function (string $prop, string $key, bool $date = false) use (&$doc, $val, $r): void {
                    $v = $val($r, $key);
                    if ($v) {
                        $doc[$prop] = $date ? self::zDate($v) : $v;
                    }
                };
                $set('DocumentTypeCode', 'docType');
                $set('DocumentNumber', 'docNumber');
                $set('NationalityCountryCode', 'nationality');
                $set('IssueCountryCode', 'docIssueCountry');
                $set('DocumentIssuanceDate', 'docIssueDate', true);
                $set('DocumentExpiryDate', 'docExpiry', true);
                $set('DateOfBirth', 'dateOfBirth', true);
                foreach (Js::list($data['Fields'] ?? null) as $field) {
                    if (preg_match('/^Documents\[0\]\.(\w+)$/', (string) $field, $m) && ! isset(self::DOC_KEYS[$m[1]])) {
                        $v = $val($r, "doc.{$m[1]}");
                        if ($v) {
                            $doc[$m[1]] = self::kindFor($m[1]) === 'date' ? self::zDate($v) : $v;
                        }
                    }
                }
                if (empty($doc['DocumentNumber']) && empty($doc['DocumentTypeCode'])) {
                    return false;
                }
                $gender = $p ? ($val($p['Ref'], 'gender') ?? self::genderForTitle($val($p['Ref'], 'title'))) : null;
                if ($gender) {
                    $doc['Gender'] = $gender;
                }
                if (! empty($p['NameElement']['Surname'])) {
                    $doc['Surname'] = $p['NameElement']['Surname'];
                }
                if (! empty($p['NameElement']['Firstname'])) {
                    $doc['Firstname'] = $p['NameElement']['Firstname'];
                }
                $data['Docs'] = [...(is_array($data['Docs'] ?? null) ? $data['Docs'] : []), 'Documents' => [$doc]];
                $s['Data'] = $data;

                return true;
            }
            if ($code === 'FOID') {
                $type = $val($r, 'docType');
                $number = $val($r, 'docNumber');
                if (! $type && ! $number) {
                    return false;
                }
                $data['Foid'] = ['DocumentTypeCode' => $type, 'DocumentNumber' => $number];
                $s['Data'] = $data;

                return true;
            }
            if ($code === 'DOCO' || $code === 'DOCA') {
                $inner = is_array($data[$obj] ?? null) ? $data[$obj] : [];
                $any = false;
                foreach (Js::list($data['Fields'] ?? null) as $field) {
                    $key = $code === 'DOCO'
                        ? ($field === 'PlaceOfBirth' ? 'placeOfBirth' : "doco.{$field}")
                        : 'doca.'.($inner['AddressTypeCode'] ?? 'Address').".{$field}";
                    $v = $val($r, $key);
                    if ($v) {
                        $inner[$field] = self::kindFor($field) === 'date' ? self::zDate($v) : $v;
                        $any = true;
                    }
                }
                $data[$obj] = $inner === [] ? ($data[$obj] ?? new \ArrayObject) : $inner;
                $s['Data'] = $data;

                return $any;
            }
            if ($r === null && $obj) {
                $inner = is_array($data[$obj] ?? null) ? $data[$obj] : [];
                $any = false;
                $pattern = '/^booking\.'.preg_quote($code, '/').'\.(\w+)$/';
                foreach (array_keys($booking) as $key) {
                    $v = $val(null, (string) $key);
                    if (preg_match($pattern, (string) $key, $m) && $v) {
                        $inner[$m[1]] = self::kindFor($m[1]) === 'date' ? self::zDate($v) : $v;
                        $any = true;
                    }
                }
                $data[$obj] = $inner === [] ? ($data[$obj] ?? new \ArrayObject) : $inner;
                $s['Data'] = $data;

                return $any;
            }
            $text = $r !== null ? $val($r, "ssr.{$code}") : $val(null, "booking.{$code}");
            if (! $text) {
                return false;
            }
            $s['Text'] = $text;

            return true;
        };

        $out = [];
        foreach (Js::list($prep['SpecialServices'] ?? null) as $s) {
            $fill($s);
            $out[] = $s; // mandatory: always sent; Zenith reports anything missing
        }
        foreach (Js::list($prep['OptionalSpecialServices'] ?? null) as $s) {
            if (empty($s['RefPassenger']) || ! in_array($s['Code'], $offered, true)) {
                continue;
            }
            foreach ($out as $m) {
                if ($m['Code'] === $s['Code'] && ($m['RefPassenger'] ?? null) === $s['RefPassenger']) {
                    continue 2;
                }
            }
            if ($fill($s)) {
                $out[] = $s;
            }
        }

        return ['Passengers' => $passengers, 'SpecialServices' => $out];
    }

    /** Which form field an SSR code feeds, to attach Zenith's messages to it. */
    public static function fieldForSsr(string $code, ?array $form, string $message): ?string
    {
        if (isset(self::TEXT_SSRS[$code])) {
            return self::TEXT_SSRS[$code]['key'];
        }
        if (in_array($code, self::BIRTH_SSRS, true)) {
            return 'dateOfBirth';
        }
        $keys = array_column($form['fields'] ?? [], 'key');
        if ($code === 'DOCS' || $code === 'FOID') {
            return match (true) {
                (bool) preg_match('/expir/i', $message) => 'docExpiry',
                (bool) preg_match('/document ?type/i', $message) => 'docType',
                (bool) preg_match('/nationalit/i', $message) => 'nationality',
                (bool) preg_match('/issu.*country|country.*issu/i', $message) => 'docIssueCountry',
                (bool) preg_match('/birth/i', $message) => 'dateOfBirth',
                (bool) preg_match('/gender/i', $message) => in_array('gender', $keys, true) ? 'gender' : 'title',
                default => 'docNumber',
            };
        }
        foreach ($keys as $key) {
            if ($code === 'DOCO' && ($key === 'placeOfBirth' || str_starts_with($key, 'doco.'))) {
                return $key;
            }
            if ($code === 'DOCA' && str_starts_with($key, 'doca.')) {
                return $key;
            }
            if ($code !== 'DOCO' && $code !== 'DOCA' && $key === "ssr.{$code}") {
                return $key;
            }
        }

        return null;
    }
}
