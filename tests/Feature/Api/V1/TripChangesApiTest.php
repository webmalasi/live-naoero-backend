<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * After booking: flight status, passenger details, changing flights, adding
 * a trip, splitting and online check-in. The Zenith answers are real
 * preproduction ones for 003PVE (BNE-INU-NAN on 9 November, one adult),
 * including a flight change to 23 November and its cancellation.
 */
class TripChangesApiTest extends AppApiTestCase
{
    private const PNR = '003PVE';

    private const PAX = '237588';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
        $customer = DB::table('customers')->where('zenith_login', 'ana@example.com')->first(['id', 'zenith_environment']);
        DB::table('bookings')->insert([
            'id' => (string) Str::uuid(), 'customer_id' => $customer->id, 'zenith_environment' => $customer->zenith_environment,
            'pnr' => self::PNR, 'status' => 'ticketed', 'currency' => 'AUD', 'total_amount' => 1613.16, 'amount_due' => 0,
            'summary' => '{}', 'ticket_numbers' => '[]', 'zenith_booking' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->zenith->booking = FakeZenith::fixture('LoadBookingTicketed')['Booking'];
    }

    private function show(string $path)
    {
        return $this->api('GET', 'bookings/'.self::PNR.$path, token: $this->token);
    }

    private function send(string $path, array $body = [])
    {
        return $this->api('POST', 'bookings/'.self::PNR.$path, $body, $this->token);
    }

    // ------------------------------------------------------------ flight status

    public function test_flight_status_comes_from_the_airline_on_the_day(): void
    {
        $this->show('')->assertOk();
        Date::setTestNow('2026-11-09 02:00:00');
        $this->token = $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!', 'deviceLabel' => 'Test phone'])->assertOk()->json('accessToken');
        $this->zenith->flightStatus = ['FlightSummaries' => [[
            'CityPair' => ['OriginCode' => 'BNE', 'DestinationCode' => 'INU'], 'FlightNumber' => 'ON  002',
            'ScheduledDepartureTime' => '2026-11-09T12:30:00', 'RevisedDepartureTime' => '2026-11-09T13:10:00', 'RealDepartureTime' => null,
            'ScheduledArrivalTime' => '2026-11-09T19:15:00', 'EstimatedArrivalTime' => '2026-11-09T19:55:00', 'RealArrivalTime' => null,
            'AirportFlightStatusCode' => 'Delayed', 'DelayReason' => 'Late inbound aircraft',
        ]]];

        $status = $this->show('/flight-status')->assertOk();
        $status->assertJsonPath('flights.0.flightNumber', 'ON002')
            ->assertJsonPath('flights.0.status', 'Delayed')->assertJsonPath('flights.0.statusLabel', 'Delayed')
            ->assertJsonPath('flights.0.expectedDeparture', '2026-11-09T13:10:00')
            ->assertJsonPath('flights.0.delayReason', 'Late inbound aircraft')
            ->assertJsonPath('flights.1.status', null)->assertJsonPath('flights.1.note', 'No live status for this flight yet.');
        $sent = $this->zenith->calls('FlightStatus');
        $this->assertSame(['OriginCode' => 'BNE', 'DestinationCode' => 'INU'], $sent[0]['CityPair']);
        $this->assertSame('2026-11-09T00:00:00', $sent[0]['Date']);
        $this->assertSame('Departing', $sent[0]['SearchDateTypeCode']);

        // Asked again within two minutes: no new airline calls.
        $this->show('/flight-status')->assertOk();
        $this->assertCount(2, $this->zenith->calls('FlightStatus'));
    }

    public function test_flight_status_waits_for_the_day_before_the_flight(): void
    {
        $this->show('')->assertOk();
        $this->show('/flight-status')->assertOk()
            ->assertJsonPath('flights.0.status', null)
            ->assertJsonPath('flights.0.note', 'Live status is shown from the day before the flight.');
        $this->assertSame([], $this->zenith->calls('FlightStatus'));
    }

    // ------------------------------------------------------------ passengers

    public function test_passenger_details_are_prefilled_and_names_stay_as_booked(): void
    {
        $this->zenith->modification = FakeZenith::fixture('PrepareBookingModificationTicketed');
        $form = $this->show('/passengers')->assertOk();
        $keys = array_column($form->json('passengers.0.fields'), 'key');
        foreach (['dateOfBirth', 'email', 'mobilePhone', 'nationality', 'docType', 'docNumber', 'docIssueCountry', 'docExpiry'] as $key) {
            $this->assertContains($key, $keys);
        }
        $this->assertNotContains('firstName', $keys);
        $this->assertNotContains('title', $keys);
        $form->assertJsonPath('passengers.0.name', 'Anna Tester')
            ->assertJsonPath('values.'.self::PAX.'.docNumber', 'PN1234567')
            ->assertJsonPath('values.'.self::PAX.'.docExpiry', '2032-01-09')
            ->assertJsonPath('values.'.self::PAX.'.dateOfBirth', '1992-03-14')
            ->assertJsonPath('values.'.self::PAX.'.email', 'naoero.e2e.1792066724@example.com');
        $this->assertStringContainsString('Names cannot be changed online', $form->json('nameChangeNote'));
    }

    public function test_only_changed_passenger_details_reach_zenith(): void
    {
        $this->zenith->modification = FakeZenith::fixture('PrepareBookingModificationTicketed');
        $this->send('/passengers', ['passengers' => [['ref' => self::PAX, 'values' => [
            'docNumber' => 'PN7654321', 'docExpiry' => '2034-05-01', 'mobilePhone' => '+61 400 999 888', 'firstName' => 'Hacker',
        ]]]])->assertOk()->assertJsonPath('message', 'Passenger details updated.');

        $sent = $this->zenith->calls('ModifyBooking')[0];
        $this->assertEqualsCanonicalizing(['DOCS', 'CTCM'], array_column($sent['SpecialServices'], 'Code'));
        $docs = collect($sent['SpecialServices'])->firstWhere('Code', 'DOCS');
        $this->assertArrayNotHasKey('Text', $docs);
        $doc = $docs['Data']['Docs']['Documents'][0];
        $this->assertSame(['PN7654321', '2034-05-01T00:00:00', 'Anna', 'Tester', 'F', 'NR', 'PP'],
            [$doc['DocumentNumber'], $doc['DocumentExpiryDate'], $doc['Firstname'], $doc['Surname'], $doc['Gender'], $doc['NationalityCountryCode'], $doc['DocumentTypeCode']]);
        $this->assertSame('+61400999888', collect($sent['SpecialServices'])->firstWhere('Code', 'CTCM')['Text'], 'phones reach Zenith in E.164');
        $this->assertTrue($sent['DeferredIssuance']);
        $this->assertArrayNotHasKey('Passengers', $sent);
    }

    public function test_passenger_details_are_checked(): void
    {
        $this->zenith->modification = FakeZenith::fixture('PrepareBookingModificationTicketed');
        $this->send('/passengers', ['passengers' => [['ref' => self::PAX, 'values' => ['docExpiry' => '2026-11-01']]]])
            ->assertStatus(422)->assertJsonPath('error.problems.0.field', 'docExpiry');
        $this->send('/passengers', ['passengers' => [['ref' => self::PAX, 'values' => ['docNumber' => 'PN1234567']]]])
            ->assertStatus(400)->assertJsonPath('error.message', 'Nothing has changed.');
        $this->send('/passengers', ['passengers' => [['ref' => 'nobody', 'values' => ['docNumber' => 'X1234567']]]])->assertStatus(400);
        $this->assertSame([], $this->zenith->calls('ModifyBooking'));
    }

    // ------------------------------------------------------------ change flights

    public function test_a_flight_change_is_quoted_held_paid_or_cancelled(): void
    {
        $options = $this->show('/change')->assertOk();
        $options->assertJsonPath('canChange', true)->assertJsonPath('legs.0.order', 1)
            ->assertJsonPath('legs.0.origin', 'BNE')->assertJsonPath('legs.0.destination', 'NAN')
            ->assertJsonCount(2, 'legs.0.flights')->assertJsonPath('pendingChange', null);

        $this->send('/change/search', ['legOrder' => 1, 'date' => '2026-10-01'])->assertStatus(400);
        $search = $this->send('/change/search', ['legOrder' => 1, 'date' => '2026-11-23'])->assertOk();
        $this->assertNotEmpty($search->json('results.journeys'));
        $search->assertJsonPath('currentFare', 1613.16);
        $sent = $this->zenith->calls('SearchFlightsForExchange')[0];
        $this->assertSame([['RefETTicketFare' => '15404488', 'CouponOrders' => [1, 2]]], $sent['TicketFaresToExchange']);
        $this->assertSame(['TargetDate' => '2026-11-23T00:00:00', 'OriginCode' => 'BNE', 'DestinationCode' => 'NAN'], $sent['OriginDestinations'][0]);

        $choice = ['legOrder' => 1, 'offerRef' => $search->json('results.offer.ref'), 'itineraryRef' => $search->json('results.journeys.0.fares.0.itineraryRef')];
        $this->send('/change/quote', $choice)->assertOk()
            ->assertJsonPath('lines', [['label' => 'Penalty', 'passenger' => 'Anna Tester', 'amount' => 160]])
            ->assertJsonPath('changeFees', 160)->assertJsonPath('currentFare', 1613.16)
            ->assertJsonPath('fareDifference', 0)->assertJsonPath('estimatedTotal', 160)->assertJsonPath('cheaper', false)
            ->assertJsonPath('flights.0.departure', '2026-11-23T12:30:00');

        $held = $this->send('/change', $choice)->assertOk();
        $held->assertJsonPath('booking.status', 'pending_payment')
            ->assertJsonPath('booking.pendingChange.amountDue', 160)
            ->assertJsonPath('booking.pendingChange.flights.0.departure', '2026-11-23T12:30:00')
            ->assertJsonCount(2, 'booking.flights')->assertJsonPath('booking.flights.0.departure', '2026-11-09T12:30:00');
        $this->assertStringContainsString('Pay 160.00 AUD', $held->json('message'));
        $this->show('/change')->assertOk()->assertJsonPath('canChange', false)->assertJsonPath('pendingChange.amountDue', 160);
        $this->send('/change', $choice)->assertStatus(409)->assertJsonPath('error.code', 'change_pending');

        // Paying the change is paying the booking: the amount due is the change's.
        $this->send('/payments')->assertCreated()->assertJsonPath('amount', 160);

        $cancelled = $this->api('DELETE', 'bookings/'.self::PNR.'/change', token: $this->token)->assertOk();
        $cancelled->assertJsonPath('booking.pendingChange', null)->assertJsonPath('booking.status', 'ticketed')
            ->assertJsonCount(2, 'booking.flights')->assertJsonPath('booking.flights.0.departure', '2026-11-09T12:30:00');
        $this->api('DELETE', 'bookings/'.self::PNR.'/change', token: $this->token)->assertStatus(409)->assertJsonPath('error.code', 'no_pending_change');
    }

    public function test_a_change_with_nothing_to_pay_is_still_confirmed_with_create_ticket(): void
    {
        $this->send('/change', ['legOrder' => 1, 'offerRef' => 'OFFER', 'itineraryRef' => 'Itin_0'])->assertOk();
        // A flexible fare: no penalty, same price.
        $this->zenith->booking['FareInfo']['SaleCurrencyTravelerAmountToPay']['TotalAmount'] = 0;
        $this->zenith->booking['FareInfo']['EMDTicketFares'] = [];
        $this->show('')->assertOk()->assertJsonPath('booking.pendingChange.amountDue', 0)->assertJsonPath('booking.status', 'pending_payment');

        $paid = $this->send('/payments')->assertCreated();
        $paid->assertJsonPath('amount', 0)->assertJsonPath('booking.status', 'ticketed')->assertJsonPath('booking.pendingChange', null);
        $this->assertSame([], $this->zenith->calls('CreateTicket')[0]['Fops']);
    }

    public function test_a_refused_change_says_why(): void
    {
        $this->zenith->failures['Exchange'] = FakeZenith::error('DupePassengerFlight', 'There is already a passenger with this name on this flight.');
        $this->send('/change', ['legOrder' => 1, 'offerRef' => 'OFFER', 'itineraryRef' => 'Itin_0'])
            ->assertStatus(409)->assertJsonPath('error.code', 'passenger_already_booked');
        $this->send('/change', ['legOrder' => 7, 'offerRef' => 'OFFER', 'itineraryRef' => 'Itin_0'])
            ->assertStatus(409)->assertJsonPath('error.code', 'change_not_allowed');
    }

    // ------------------------------------------------------------ add a trip

    public function test_a_trip_is_added_and_paid_like_the_booking(): void
    {
        $search = $this->send('/add-trip/search', ['origin' => 'inu', 'destination' => 'BNE', 'date' => '2026-11-20'])->assertOk();
        $this->assertNotEmpty($search->json('results.journeys'));
        $sent = $this->zenith->calls('SearchFlightsForAdditionalItinerary')[0];
        $this->assertSame(['TargetDate' => '2026-11-20T00:00:00', 'OriginCode' => 'INU', 'DestinationCode' => 'BNE'], $sent['OriginDestinations']);

        $choice = ['offerRef' => $search->json('results.offer.ref'), 'itineraryRef' => $search->json('results.journeys.0.fares.0.itineraryRef')];
        $quote = $this->send('/add-trip/quote', $choice)->assertOk();
        $this->assertGreaterThan(0, $quote->json('total'));
        $this->assertNotEmpty($quote->json('flights'));

        $added = $this->send('/add-trip', $choice)->assertOk();
        $added->assertJsonPath('booking.status', 'pending_payment')->assertJsonPath('booking.amountDue', 452.3);
        $this->send('/payments')->assertCreated()->assertJsonPath('amount', 452.3);

        $this->send('/add-trip/search', ['origin' => 'BNE', 'destination' => 'BNE', 'date' => '2026-11-20'])->assertStatus(400);
        $this->zenith->failures['SearchFlightsForAdditionalItinerary'] = FakeZenith::error('NoAvailability', 'No availability found for the given criteria.');
        $this->send('/add-trip/search', ['origin' => 'INU', 'destination' => 'BNE', 'date' => '2026-11-20'])->assertOk()
            ->assertJsonPath('results.journeys', [])->assertJsonPath('results.noResultsReason', 'NoAvailability');
    }

    // ------------------------------------------------------------ split

    public function test_a_passenger_gets_a_booking_of_their_own(): void
    {
        $two = FakeZenith::fixture('LoadBookingTicketed')['Booking'];
        $two['Passengers'][] = ['Ref' => '237589', 'PassengerQuantity' => 1, 'PassengerTypeCode' => 'AD', 'NameElement' => ['CivilityCode' => 'MR', 'Firstname' => 'Ben', 'Surname' => 'Tester']];
        $ben = $two;
        $ben['PnrInformation']['PnrCode'] = 'SPL001';
        $ben['Passengers'] = [end($two['Passengers'])];
        $this->zenith->pnrBookings = [self::PNR => $two, 'SPL001' => $ben];

        $this->send('/split', ['passengerRefs' => [self::PAX, '237589']])->assertStatus(400);
        $split = $this->send('/split', ['passengerRefs' => ['237589']])->assertOk();
        $split->assertJsonPath('newBooking.pnr', 'SPL001')->assertJsonPath('newBooking.passengers.0.name', 'Ben Tester');
        $this->assertStringContainsString('Ben Tester now has booking SPL001', $split->json('message'));
        $this->assertSame([['Ref' => '237589', 'Quantity' => 1]], $this->zenith->calls('SplitBooking')[0]['PassengersToSplit']);
        $this->assertEqualsCanonicalizing([self::PNR, 'SPL001'], array_column($this->api('GET', 'bookings', token: $this->token)->json('bookings'), 'pnr'));
    }

    public function test_a_single_passenger_booking_cannot_be_split(): void
    {
        $this->send('/split', ['passengerRefs' => [self::PAX]])->assertStatus(409)->assertJsonPath('error.code', 'split_not_possible');
        $this->assertSame([], $this->zenith->calls('SplitBooking'));
    }

    // ------------------------------------------------------------ check-in

    public function test_check_in_refused_by_the_airline_shows_why(): void
    {
        $info = $this->show('/check-in')->assertOk();
        $info->assertJsonPath('groups.0.canCheckIn', false)
            ->assertJsonPath('groups.0.passengers.0.name', 'Anna Tester')
            ->assertJsonPath('groups.0.passengers.0.status', 'NotAuthorized')
            ->assertJsonPath('groups.0.flights.0.origin', 'BNE')->assertJsonPath('groups.0.flights.1.destination', 'NAN');
        $this->assertContains('The fare basis KECO does not allow web check-in.', $info->json('groups.0.passengers.0.reasons'));
        $this->assertStringContainsString('check in at the airport', $info->json('unavailableReason'));
        $this->send('/check-in', ['group' => 0])->assertStatus(409)->assertJsonPath('error.code', 'checkin_unavailable');
        $this->assertSame([], $this->zenith->calls('Checkin'));
    }

    public function test_check_in_gives_boarding_passes(): void
    {
        $open = FakeZenith::fixture('PrepareCheckin');
        foreach ($open['CheckinTargets'][0]['ETTicketCheckinList'][0]['ETCouponCheckinList'] as &$coupon) {
            $coupon = [...$coupon, 'CheckinStatusCode' => 'Checkable', 'NotAuthorizedReason' => null, 'NotAuthorizedDetailedReasons' => [], 'SeatingMandatory' => false];
        }
        unset($coupon);
        $open['Booking']['MiscInfo']['ContainsIssuableTickets'] = false;
        $this->zenith->checkin = $open;

        $this->show('/check-in')->assertOk()->assertJsonPath('groups.0.canCheckIn', true)->assertJsonPath('unavailableReason', null);
        $done = $this->send('/check-in', ['group' => 0])->assertOk();
        $this->assertSame([['DocumentNumber' => '1232415191663', 'CouponOrders' => [1, 2]]], $this->zenith->calls('Checkin')[0]['ETTicketTargets']);
        $this->assertMatchesRegularExpression('~^https://[^/]+/Zenith/BoardingPass\?arg=abc$~', $done->json('boardingPasses.0.url'));
        $done->assertJsonPath('booking.coupons.open', 2);
    }
}
