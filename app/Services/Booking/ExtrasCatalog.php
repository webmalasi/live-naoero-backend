<?php

namespace App\Services\Booking;

/**
 * The special services (SSRs) customers may request themselves. Zenith
 * offers about eighty optional codes on every flight, most of them for
 * airline staff (ticketing, groups, deportees, staff travel…); only the
 * codes listed here are ever shown to customers or accepted from them.
 * Labels are our own because the airline's preproduction labels are partly
 * truncated ("y Baggage", "en"). Requests are free: the airline confirms or
 * refuses them later. Paid services come from the airline as ancillaries.
 */
final class ExtrasCatalog
{
    /** Display order of the categories. */
    public const CATEGORIES = [
        'meal' => 'Meals',
        'assistance' => 'Special assistance',
        'baggage' => 'Baggage & equipment',
        'animal' => 'Travelling with animals',
        'seating' => 'Seating preferences',
        'lounge' => 'Lounge',
        'note' => 'Note to the airline',
    ];

    /**
     * code => [category, label, description, text prompt or null, passenger types allowed or null (all but infants)].
     * Infants (INF) only get what lists them explicitly.
     */
    public const SERVICES = [
        // Meals: one per passenger per flight.
        'AVML' => ['meal', 'Asian vegetarian meal', 'Vegetarian meal prepared the Asian way.', null, null],
        'VLML' => ['meal', 'Vegetarian meal (lacto-ovo)', 'No meat or fish; contains dairy and eggs.', null, null],
        'VGML' => ['meal', 'Vegetarian meal (no dairy)', 'No meat, fish or dairy products.', null, null],
        'VVML' => ['meal', 'Vegan meal', 'No animal products of any kind.', null, null],
        'GFML' => ['meal', 'Gluten-free meal', 'Prepared without gluten.', null, null],
        'DBML' => ['meal', 'Diabetic meal', 'Low in sugar.', null, null],
        'HNML' => ['meal', 'Hindu meal', 'No beef or pork.', null, null],
        'MOML' => ['meal', 'Muslim meal', 'Halal; no pork or alcohol.', null, null],
        'KSML' => ['meal', 'Kosher meal', 'Prepared under rabbinical supervision.', null, null],
        'SFML' => ['meal', 'Seafood meal', 'Fish and seafood.', null, null],
        'FPML' => ['meal', 'Fruit platter', 'Fresh fruit.', null, null],
        'CHML' => ['meal', 'Child meal', 'A meal for children.', null, ['CHD']],
        'BBML' => ['meal', 'Baby food', 'Jars of baby food for infants.', null, ['INF']],
        'SPML' => ['meal', 'Other special meal', 'Tell us what you need.', 'Describe the meal', null],

        // Assistance: one wheelchair level per passenger per flight.
        'WCHR' => ['assistance', 'Wheelchair to and from the aircraft', 'You can walk short distances and climb steps, but need a wheelchair for long distances.', null, null],
        'WCHS' => ['assistance', 'Wheelchair, cannot use steps', 'You can walk short distances but cannot climb aircraft steps.', null, null],
        'WCHC' => ['assistance', 'Wheelchair, fully immobile', 'You need a wheelchair all the way to your seat.', null, null],
        'WCOB' => ['assistance', 'On-board wheelchair', 'A wheelchair on the aircraft, provided by the airline.', null, null],
        'WCMP' => ['assistance', 'Own manual wheelchair', 'You bring your own manual wheelchair.', null, null],
        'WCBD' => ['assistance', 'Own powered wheelchair (dry battery)', 'Non-spillable battery.', null, null],
        'WCBW' => ['assistance', 'Own powered wheelchair (wet battery)', 'Wet-cell battery.', null, null],
        'BLND' => ['assistance', 'Blind or low-vision passenger', 'Assistance through the airport and on board.', null, null],
        'DEAF' => ['assistance', 'Deaf or hard-of-hearing passenger', 'Including travel with a hearing dog.', null, null],
        'DPNA' => ['assistance', 'Intellectual or developmental disability', 'Assistance for a passenger who needs support.', null, null],
        'MAAS' => ['assistance', 'Meet and assist', 'A staff member meets and helps you through the airport.', null, null],
        'MEDA' => ['assistance', 'Medical condition', 'The airline may contact you for a medical clearance.', 'Describe the medical condition', null],
        'OXYG' => ['assistance', 'Oxygen on board', 'Medical oxygen during the flight.', 'Flow rate or details', null],
        'LANG' => ['assistance', 'Languages spoken', 'Let the crew know which language you speak.', 'Languages spoken', null],

        // Baggage & equipment (paid extra baggage comes as an ancillary).
        'BIKE' => ['baggage', 'Bicycle', 'Carriage of a bicycle in the hold.', null, null],
        'SPEQ' => ['baggage', 'Sports equipment', 'Surfboards, golf clubs, diving gear…', 'What equipment?', null],
        'BULK' => ['baggage', 'Bulky baggage', 'An item larger than standard dimensions.', 'Describe the item', null],
        'FRAG' => ['baggage', 'Fragile baggage', 'An item that needs careful handling.', null, null],

        // Animals.
        'PETC' => ['animal', 'Pet in the cabin', 'A small animal in a carrier at your feet.', 'Animal type and weight', null],
        'ESAN' => ['animal', 'Emotional support or psychiatric assistance animal', 'The airline may ask for documents.', null, null],

        // Seating preferences (when no seat is chosen on the seat map).
        'NSSW' => ['seating', 'Window seat preference', 'Assigned at check-in when possible.', null, null],
        'NSSA' => ['seating', 'Aisle seat preference', 'Assigned at check-in when possible.', null, null],
        'NSSB' => ['seating', 'Bulkhead seat preference', 'Assigned at check-in when possible.', null, null],

        'LNGE' => ['lounge', 'Lounge access', 'Access to the airport lounge where available.', null, null],

        // Booking level.
        'OTHS' => ['note', 'Note to the airline', 'Anything else the airline should know.', 'Your note', null],
    ];

    /** Codes of which a passenger may hold only one per flight. */
    public const EXCLUSIVE = [
        'meal' => ['AVML', 'VLML', 'VGML', 'VVML', 'GFML', 'DBML', 'HNML', 'MOML', 'KSML', 'SFML', 'FPML', 'CHML', 'BBML', 'SPML'],
        'wheelchair' => ['WCHR', 'WCHS', 'WCHC'],
        'seatPreference' => ['NSSW', 'NSSA', 'NSSB'],
    ];

    /** Longest free text accepted in a request (IATA free text). */
    public const TEXT_MAX = 60;

    /** Zenith special service statuses as customers should read them. */
    public const STATUS = [
        'HK' => ['confirmed', 'Confirmed'], 'KK' => ['confirmed', 'Confirmed'], 'KL' => ['confirmed', 'Confirmed'],
        'HS' => ['confirmed', 'Confirmed'], 'TK' => ['confirmed', 'Confirmed'], 'RR' => ['confirmed', 'Confirmed'],
        'NN' => ['requested', 'Requested'], 'HN' => ['requested', 'Requested'], 'PN' => ['requested', 'Requested'], 'LL' => ['requested', 'Waitlisted'],
        'UN' => ['refused', 'Not available'], 'UC' => ['refused', 'Not available'], 'NO' => ['refused', 'Not available'],
        'XX' => ['cancelled', 'Cancelled'], 'HX' => ['cancelled', 'Cancelled'], 'XL' => ['cancelled', 'Cancelled'],
    ];

    /** Fare rule change / refund codes. */
    public const VOLUNTARY = [
        'Forbidden' => 'Not allowed',
        'WithPenalties' => 'Allowed with a fee',
        'Free' => 'Free of charge',
        'Allowed' => 'Allowed',
    ];

    public static function offered(string $code): bool
    {
        return isset(self::SERVICES[$code]);
    }

    public static function forPassengerType(string $code, ?string $type): bool
    {
        $allowed = self::SERVICES[$code][4] ?? null;
        if ($allowed !== null) {
            return $type !== null && in_array($type, $allowed, true);
        }

        return $type !== 'INF';
    }

    public static function needsText(string $code): bool
    {
        return (self::SERVICES[$code][3] ?? null) !== null;
    }

    /** @return array{code: string, category: string, categoryLabel: string, label: string, description: string, needsText: bool, textLabel: ?string} */
    public static function describe(string $code): array
    {
        [$category, $label, $description, $prompt] = self::SERVICES[$code];

        return [
            'code' => $code,
            'category' => $category,
            'categoryLabel' => self::CATEGORIES[$category],
            'label' => $label,
            'description' => $description,
            'needsText' => $prompt !== null,
            'textLabel' => $prompt,
        ];
    }

    /** The exclusive group a code belongs to, if any. */
    public static function exclusiveGroup(string $code): ?string
    {
        foreach (self::EXCLUSIVE as $group => $codes) {
            if (in_array($code, $codes, true)) {
                return $group;
            }
        }

        return null;
    }

    /** IATA free text: upper case letters, digits, spaces and . , / - ' only. */
    public static function cleanText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $t = preg_replace("/[^A-Z0-9 .,\\/'-]/", '', strtoupper($text));
        $t = trim(preg_replace('/\s+/', ' ', $t));

        return $t === '' ? null : mb_substr($t, 0, self::TEXT_MAX);
    }

    /** @return array{0: string, 1: string} [state, label] */
    public static function status(?string $code): array
    {
        if ($code === null || $code === '') {
            return ['requested', 'Requested'];
        }

        return self::STATUS[strtoupper($code)] ?? ['requested', $code];
    }
}
