<?php

namespace Tests\Unit\AppApi;

use App\Services\Booking\BookingForm;
use App\Services\Booking\BookingSummary;
use App\Services\Booking\SavedTravellers;
use App\Services\Flights\FlightConfigService;
use App\Services\Flights\FlightSearch;
use App\Services\Support\Js;
use App\Services\Support\Phones;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The app API moved from Next.js to Laravel. tests/Fixtures/golden.json holds
 * what the original TypeScript produced for real Zenith responses; the PHP
 * port must produce exactly the same data.
 */
class ParityTest extends TestCase
{
    private const ADULT = 'Traveler_Type_1_Index_0';

    private const CHILD = 'Traveler_Type_2_Index_1';

    private const INFANT = 'Traveler_Type_3_Index_2';

    private static ?array $golden = null;

    private static function golden(string $key): mixed
    {
        self::$golden ??= json_decode(file_get_contents(__DIR__.'/../../Fixtures/golden.json'), true);

        return self::$golden[$key];
    }

    public static function fixture(string $name): array
    {
        return Js::decode(file_get_contents(__DIR__."/../../Fixtures/Zenith/{$name}.json"));
    }

    /** Compares as JSON would see it (objects vs lists, numbers). */
    private function assertSameJson(mixed $expected, mixed $actual): void
    {
        $this->assertEquals($expected, json_decode(Js::encode($actual), true));
    }

    private static function config(): array
    {
        return FlightConfigService::build(self::fixture('SaleConfiguration'), self::fixture('eod'), self::fixture('vc_Airport'), '2026-10-15T08:00:00.000Z');
    }

    private static function ref(): array
    {
        return [
            'titles' => [['code' => 'MISS', 'label' => 'Miss'], ['code' => 'MR', 'label' => 'Mr'], ['code' => 'MRS', 'label' => 'Mrs'], ['code' => 'MS', 'label' => 'Ms']],
            'documentTypes' => [['code' => 'DOI', 'label' => 'Document of Identity'], ['code' => 'NI', 'label' => 'ID Card'], ['code' => 'PP', 'label' => 'Passport'], ['code' => 'RT', 'label' => 'Refugee travel document']],
            'genders' => [['code' => 'M', 'label' => 'Male'], ['code' => 'F', 'label' => 'Female']],
            'passengerTypes' => [
                ['code' => 'AD', 'label' => 'Adult(s)', 'minAge' => null, 'maxAge' => null],
                ['code' => 'CHD', 'label' => 'Child(ren)', 'minAge' => 2, 'maxAge' => 12],
                ['code' => 'INF', 'label' => 'Infant(s)', 'minAge' => 0, 'maxAge' => 2],
            ],
        ];
    }

    private static function goodValues(): array
    {
        $doc = ['docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01'];

        return [
            ['ref' => self::ADULT, 'values' => ['title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14', 'mobilePhone' => '+61 400 000 001', 'email' => 'john@example.com', ...$doc]],
            ['ref' => self::CHILD, 'values' => ['firstName' => 'Amy', 'lastName' => 'Citizen', 'gender' => 'F', 'dateOfBirth' => '2018-05-05', ...$doc, 'docNumber' => 'PB1234567']],
            ['ref' => self::INFANT, 'values' => ['firstName' => 'Ben', 'lastName' => 'Citizen', 'gender' => 'M', 'dateOfBirth' => '2026-01-10', ...$doc, 'docNumber' => 'PC1234567']],
        ];
    }

    public function test_flight_config_matches(): void
    {
        $this->assertSameJson(self::golden('config'), self::config());
    }

    public function test_fare_families_match(): void
    {
        $this->assertSameJson(self::golden('families'), FlightSearch::fareFamilies(self::fixture('vc_WebClass')));
        $this->assertSame(self::golden('inclusions'), FlightSearch::parseInclusions(
            '<ul><li><i>luggage</i>23KGS checked BAGGAGE &amp; more</li><li>Seat SELECTION from AUD 10 per flight</li><li>&nbsp;</li></ul>'
        ));
    }

    public static function searches(): array
    {
        return [['search_owweb'], ['search_rt'], ['search_noflt']];
    }

    #[DataProvider('searches')]
    public function test_search_results_match(string $name): void
    {
        $families = FlightSearch::fareFamilies(self::fixture('vc_WebClass'));
        $this->assertSameJson(self::golden('search')[$name], FlightSearch::normalize(self::fixture($name), $families));
    }

    public function test_search_validation_and_flexible_dates_match(): void
    {
        $config = self::config();
        $flightDates = (array) $config['flightDates'];
        $input = FlightSearch::validate([
            'legs' => [
                ['origin' => 'bne', 'destination' => 'INU', 'date' => $flightDates['BNE-INU'][3]],
                ['origin' => 'INU', 'destination' => 'BNE', 'date' => $flightDates['INU-BNE'][6]],
            ],
            'passengers' => ['AD' => 2, 'CHD' => 1, 'INF' => 0],
            'currency' => 'aud',
            'flexibleDays' => 1,
        ], $config, '2026-10-15');
        $this->assertSameJson(self::golden('searchInput')['value'], $input);
        $this->assertSame(self::golden('flexible'), FlightSearch::flexibleDateSets($input, $config, '2026-10-15'));
    }

    public function test_passenger_form_matches(): void
    {
        $this->assertSameJson(self::golden('form'), BookingForm::build(self::fixture('PrepareFlights'), self::ref()));
    }

    public function test_passenger_validation_matches(): void
    {
        $form = BookingForm::build(self::fixture('PrepareFlights'), self::ref());
        $countries = ['AU' => true, 'NR' => true, 'FJ' => true];
        $this->assertSame(self::golden('validGood'), BookingForm::validate($form, ['passengers' => self::goodValues()], self::ref(), $countries, '2026-10-16'));

        $bad = self::goodValues();
        $bad[0]['values'] = [...$bad[0]['values'], 'email' => 'not-an-email', 'firstName' => 'J0hn', 'mobilePhone' => '', 'docType' => 'XX', 'nationality' => 'ZZ'];
        $bad[2]['values'] = [...$bad[2]['values'], 'dateOfBirth' => '2023-01-01', 'docExpiry' => '2026-10-20'];
        $bad[1]['values'] = [...$bad[1]['values'], 'firstName' => 'John', 'lastName' => 'Citizen'];
        $this->assertSame(self::golden('validBad'), BookingForm::validate($form, ['passengers' => $bad], self::ref(), $countries, '2026-10-16'));
    }

    public function test_create_booking_payload_matches(): void
    {
        $prepare = self::fixture('PrepareFlights');
        $form = BookingForm::build($prepare, self::ref());
        // Unchanged since the proxy, except that phones now reach Zenith in E.164.
        $expected = self::golden('applied');
        foreach ($expected['SpecialServices'] as &$ssr) {
            if (in_array($ssr['Code'], ['CTCM', 'CTCH'], true)) {
                $ssr['Text'] = Phones::normalize($ssr['Text']);
            }
        }
        unset($ssr);
        $this->assertSameJson($expected, BookingForm::apply($prepare, $form, ['passengers' => self::goodValues()]));
    }

    public function test_booking_summary_matches(): void
    {
        $booking = self::fixture('CreateBooking')['Booking'];
        $summary = BookingSummary::summarize($booking, CarbonImmutable::parse('2026-10-16T08:00:00Z'));
        // Extras are new since the proxy; everything the proxy returned is unchanged.
        $this->assertSame(['services' => [], 'seats' => [], 'ancillaries' => [], 'total' => 0], array_diff_key($summary['extras'], ['segments' => 1]));
        $this->assertSame([['ref' => '490318', 'origin' => 'BNE', 'destination' => 'INU']], $summary['extras']['segments']);
        $this->assertSame('Economy', $summary['cabin']);
        $this->assertSame(['flown' => 0, 'open' => 0, 'refunded' => 0, 'exchanged' => 0, 'void' => 0, 'total' => 0], $summary['coupons']);
        $this->assertNull($summary['pendingChange']);
        // Services bought on their own are new too: a flight booking has none.
        $this->assertSame(['flights', [], null], [$summary['kind'], $summary['services'], $summary['keptTickets']]);
        unset($summary['extras'], $summary['cabin'], $summary['coupons'], $summary['pendingChange'], $summary['kind'], $summary['services'], $summary['keptTickets']);
        $this->assertSameJson(self::golden('summary'), $summary);
    }

    public function test_saved_traveller_eligibility_matches(): void
    {
        $form = BookingForm::build(self::fixture('PrepareFlights'), self::ref());
        $travellers = [
            ['id' => 't1', 'firstName' => 'Mia', 'lastName' => 'X', 'dateOfBirth' => '1990-07-07', 'lastPassengerType' => 'AD', 'values' => []],
            ['id' => 't2', 'firstName' => 'Leo', 'lastName' => 'X', 'dateOfBirth' => '2026-02-02', 'lastPassengerType' => 'INF', 'values' => []],
            ['id' => 't3', 'firstName' => 'Kid', 'lastName' => 'X', 'dateOfBirth' => '2018-01-01', 'lastPassengerType' => 'CHD', 'values' => []],
            ['id' => 't4', 'firstName' => 'Nod', 'lastName' => 'X', 'dateOfBirth' => null, 'lastPassengerType' => 'CHD', 'values' => []],
        ];
        $this->assertSameJson(self::golden('eligibility'), SavedTravellers::eligibility($travellers, $form, self::ref()));
    }

    public function test_empty_objects_survive_a_round_trip(): void
    {
        $json = '{"a":{},"b":[],"c":{"d":{}},"e":[{}]}';
        $this->assertSame($json, Js::encode(Js::decode($json)));
    }
}
