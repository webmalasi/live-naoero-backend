<?php

namespace Tests\Feature\Api\V1;

/**
 * Additional services: meals, assistance, seats and paid extras, offered
 * from what Zenith returns (a real preproduction PrepareFlights for INU-TRW
 * with its seat map and AUD 300 extra baggage), chosen with the booking and
 * changed afterwards.
 */
class BookingExtrasApiTest extends AppApiTestCase
{
    private const ADULT = 'Traveler_Type_1_Index_0';

    private const SEGMENT = '-13605';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
        $this->zenith->prepare = FakeZenith::fixture('PrepareFlightsExtras');
    }

    private function prepare(): array
    {
        return $this->api('POST', 'bookings/prepare', ['offerRef' => 'OFFER-1', 'itineraryRef' => 'Itin_0'], $this->token)->assertOk()->json();
    }

    private function passenger(): array
    {
        return [['ref' => self::ADULT, 'values' => [
            'title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14',
            'mobilePhone' => '+61 400 000 001', 'email' => 'john@example.com',
            'docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01',
        ]]];
    }

    private static function seats(array $map, bool $available): array
    {
        $out = [];
        foreach ($map['cabins'] as $c) {
            foreach ($c['rows'] as $r) {
                foreach ($r['groups'] as $g) {
                    foreach ($g as $s) {
                        if ($s['available'] === $available && ! $s['chargeable']) {
                            $out[] = $s['seat'];
                        }
                    }
                }
            }
        }

        return $out;
    }

    public function test_prepare_offers_only_customer_services_seats_extras_and_fare_rules(): void
    {
        $prepared = $this->prepare();
        $sent = $this->zenith->calls('PrepareFlights')[0];
        $this->assertTrue($sent['IncludeAllOptionalSpecialServices']);

        $extras = $prepared['extras'];
        $this->assertSame('AUD', $extras['currency']);
        $this->assertSame([['ref' => self::ADULT, 'type' => 'AD', 'label' => 'Adult 1', 'name' => null]], $extras['passengers']);
        $this->assertSame(self::SEGMENT, $extras['segments'][0]['ref']);

        $codes = array_column($extras['services'], 'code');
        foreach (['VGML', 'AVML', 'WCHR', 'BLND', 'PETC', 'BIKE', 'OTHS', 'MAAS'] as $code) {
            $this->assertContains($code, $codes);
        }
        // Staff, ticketing and paid-baggage codes are never offered as free requests.
        foreach (['TKNE', 'RLOC', 'DEPA', 'GRPF', 'ED25', 'XBAG', 'CHML', 'BBML', 'SEAT'] as $code) {
            $this->assertNotContains($code, $codes);
        }
        $meal = collect($extras['services'])->firstWhere('code', 'VGML');
        $this->assertSame(['code' => 'VGML', 'category' => 'meal', 'categoryLabel' => 'Meals', 'label' => 'Vegetarian meal (no dairy)', 'description' => 'No meat, fish or dairy products.', 'needsText' => false, 'textLabel' => null, 'maxLength' => 60,
            'available' => [['passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT]]], $meal);
        $this->assertSame([['passengerRef' => null, 'segmentRef' => null]], collect($extras['services'])->firstWhere('code', 'OTHS')['available']);
        $this->assertTrue(collect($extras['services'])->firstWhere('code', 'PETC')['needsText']);

        $map = $extras['seatMaps'][0];
        $this->assertSame(self::SEGMENT, $map['segmentRef']);
        $this->assertNotEmpty(self::seats($map, true));
        $this->assertNotEmpty(self::seats($map, false));
        $this->assertSame([['passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT]], $extras['seatPassengers']);

        $this->assertCount(1, $extras['ancillaries']);
        $bag = $extras['ancillaries'][0];
        $this->assertSame(['10000189', '25KG Extra Baggage INU-TRW | TRW-INU', 'XBAG', 300, false, 1, [self::ADULT], [self::SEGMENT]],
            [$bag['code'], $bag['label'], $bag['serviceCode'], $bag['price'], $bag['multiSelect'], $bag['maxQuantity'], $bag['passengerRefs'], $bag['segmentRefs']]);

        $rule = $extras['fareRules'][0];
        $this->assertSame([self::SEGMENT], $rule['segmentRefs']);
        $this->assertNotEmpty($rule['change']['label']);
        $this->assertNotEmpty($rule['conditions']['children']);
    }

    public function test_extras_chosen_with_the_booking_reach_zenith(): void
    {
        $prepared = $this->prepare();
        $seat = self::seats($prepared['extras']['seatMaps'][0], true)[0];
        $bag = $prepared['extras']['ancillaries'][0]['ref'];

        $this->api('POST', 'bookings', [
            'draftId' => $prepared['draftId'],
            'passengers' => $this->passenger(),
            'extras' => [
                'services' => [
                    ['code' => 'VGML', 'passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT],
                    ['code' => 'PETC', 'passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'text' => 'Small cat, 4 kg 🐱'],
                    ['code' => 'OTHS', 'text' => 'Celebrating an anniversary'],
                ],
                'seats' => [['passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'seat' => strtolower($seat)]],
                'ancillaries' => [['ref' => $bag, 'passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT]],
            ],
        ], $this->token)->assertCreated();

        $sent = $this->zenith->calls('CreateBooking')[0];
        $ssr = fn ($code) => collect($sent['SpecialServices'])->firstWhere('Code', $code);
        $this->assertSame([self::ADULT, self::SEGMENT], [$ssr('VGML')['RefPassenger'], $ssr('VGML')['RefSegment']]);
        $this->assertSame('SMALL CAT, 4 KG', $ssr('PETC')['Text']);
        $this->assertSame('CELEBRATING AN ANNIVERSARY', $ssr('OTHS')['Text']);
        $this->assertNull($ssr('OTHS')['RefPassenger'] ?? null);
        preg_match('/^(\d+)([A-Z])$/', $seat, $m);
        $this->assertSame(['SeatRow' => (int) $m[1], 'SeatLetter' => $m[2]], array_intersect_key($ssr('SEAT')['Data']['Seat'], ['SeatRow' => 1, 'SeatLetter' => 1]));
        $this->assertSame($seat, $ssr('SEAT')['Text']);
        // The passenger form's own SSRs are still there.
        $this->assertNotNull($ssr('DOCS'));

        $emd = $sent['FareInfo']['EMDTicketFares'];
        $this->assertCount(1, $emd);
        $this->assertSame([self::ADULT, self::SEGMENT, '10000189', 'XBAG'], [$emd[0]['RefPassenger'], $emd[0]['RefSegment'], $emd[0]['AncillaryCode'], $emd[0]['AssociatedSpecialServiceCode']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $emd[0]['Ref']);
    }

    public function test_extras_are_checked_against_the_offer(): void
    {
        $prepared = $this->prepare();
        $map = $prepared['extras']['seatMaps'][0];
        $bag = $prepared['extras']['ancillaries'][0]['ref'];
        $post = fn (array $extras) => $this->api('POST', 'bookings', ['draftId' => $prepared['draftId'], 'passengers' => $this->passenger(), 'extras' => $extras], $this->token);
        $svc = fn ($code, $text = null) => ['code' => $code, 'passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'text' => $text];

        foreach ([
            [['services' => [$svc('TKNE')]], 'is not available'],
            [['services' => [$svc('VGML'), $svc('AVML')]], 'Choose only one meal'],
            [['services' => [$svc('WCHR'), $svc('WCHC')]], 'Choose only one wheelchair type'],
            [['services' => [$svc('PETC')]], 'Animal type and weight'],
            [['services' => [['code' => 'VGML', 'passengerRef' => 'Traveler_Type_9_Index_9', 'segmentRef' => self::SEGMENT]]], 'is not available'],
            [['seats' => [['passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'seat' => self::seats($map, false)[0]]]], 'is no longer available'],
            [['seats' => [['passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'seat' => '99Z']]], 'does not exist'],
            [['ancillaries' => [['ref' => $bag, 'passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'quantity' => 2]]], 'choose between 1 and 1'],
            [['ancillaries' => [['ref' => 'nope', 'passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT]]], 'no longer offered'],
        ] as [$extras, $message]) {
            $res = $post($extras)->assertStatus(422)->assertJsonPath('error.code', 'invalid_extras');
            $this->assertStringContainsString($message, $res->json('error.problems.0.message'), json_encode($extras));
        }
        $post(['seats' => [['passengerRef' => self::ADULT, 'segmentRef' => self::SEGMENT, 'seat' => 'window']]])->assertStatus(400);
        $this->assertCount(0, $this->zenith->calls('CreateBooking'));
    }

    public function test_extras_can_be_added_and_removed_after_booking(): void
    {
        // The booking from the CreateBooking fixture: an adult with an infant on the lap.
        $draft = $this->api('POST', 'bookings/prepare', ['offerRef' => 'O', 'itineraryRef' => 'I'], $this->token)->json('draftId');
        $this->zenith->prepare = null;
        $draft = $this->api('POST', 'bookings/prepare', ['offerRef' => 'O', 'itineraryRef' => 'I'], $this->token)->json('draftId');
        $doc = ['docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => [
            ['ref' => 'Traveler_Type_1_Index_0', 'values' => ['title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14', 'mobilePhone' => '+61 400 000 001', ...$doc]],
            ['ref' => 'Traveler_Type_2_Index_1', 'values' => ['firstName' => 'Amy', 'lastName' => 'Citizen', 'gender' => 'F', 'dateOfBirth' => '2018-05-05', ...$doc, 'docNumber' => 'PB1234567']],
            ['ref' => 'Traveler_Type_3_Index_2', 'values' => ['firstName' => 'Ben', 'lastName' => 'Citizen', 'gender' => 'M', 'dateOfBirth' => '2026-01-10', ...$doc, 'docNumber' => 'PC1234567']],
        ]], $this->token)->assertCreated();
        [$adult, $infant, $segment] = ['237560', '237561', '490318'];

        $page = $this->api('GET', 'bookings/003PUX/extras', token: $this->token)->assertOk();
        $offer = $page->json('offer');
        $this->assertSame([], $page->json('current.services'));
        $this->assertSame('003PUX', $page->json('booking.pnr'));
        // Infants: baby food yes, a seat no.
        $bbml = collect($offer['services'])->firstWhere('code', 'BBML');
        $this->assertSame([['passengerRef' => $infant, 'segmentRef' => $segment]], $bbml['available']);
        $this->assertSame([['passengerRef' => $adult, 'segmentRef' => $segment]], $offer['seatPassengers']);
        $this->assertNotNull(collect($offer['passengers'])->firstWhere('ref', $adult)['name']);
        $seat = self::seats($offer['seatMaps'][0], true)[0];

        $added = $this->api('POST', 'bookings/003PUX/extras', ['add' => [
            'services' => [['code' => 'WCHR', 'passengerRef' => $adult, 'segmentRef' => $segment], ['code' => 'BBML', 'passengerRef' => $infant, 'segmentRef' => $segment]],
            'seats' => [['passengerRef' => $adult, 'segmentRef' => $segment, 'seat' => $seat]],
            'ancillaries' => [['ref' => $offer['ancillaries'][0]['ref'], 'passengerRef' => $adult, 'segmentRef' => $segment]],
        ]], $this->token)->assertOk();
        $sent = $this->zenith->calls('ModifyBooking')[0];
        $this->assertSame(['TypeCode' => 'PnrCode', 'ID' => '003PUX'], $sent['UniqueID']);
        $this->assertTrue($sent['DeferredIssuance']);
        $this->assertNotEmpty($sent['Verification']['RefCustomer']);
        $this->assertSame(['WCHR', 'BBML', 'SEAT'], array_column($sent['SpecialServices'], 'Code'));
        $this->assertSame('10000189', $sent['EMDTicketFares'][0]['AncillaryCode']);

        $current = $added->json('current');
        $this->assertSame(['WCHR', 'BBML'], array_column($current['services'], 'code'));
        $this->assertSame('Requested', $current['services'][0]['statusLabel']);
        $this->assertSame($seat, $current['seats'][0]['seat']);
        $this->assertSame([300, false, true], [$current['ancillaries'][0]['price'], $current['ancillaries'][0]['paid'], $current['ancillaries'][0]['removable']]);
        $this->assertSame(300, $added->json('booking.extras.total'));

        // Remove the wheelchair and the (unpaid) bag; change the seat.
        $bagRef = $current['ancillaries'][0]['ref'];
        $other = self::seats($offer['seatMaps'][0], true)[1];
        $this->api('POST', 'bookings/003PUX/extras', [
            'add' => ['seats' => [['passengerRef' => $adult, 'segmentRef' => $segment, 'seat' => $other]]],
            'remove' => ['services' => [['code' => 'WCHR', 'passengerRef' => $adult, 'segmentRef' => $segment]], 'seats' => [['passengerRef' => $adult, 'segmentRef' => $segment]], 'ancillaries' => [$bagRef]],
        ], $this->token)->assertOk()
            ->assertJsonPath('current.services.0.code', 'BBML')->assertJsonPath('current.seats.0.seat', $other)->assertJsonPath('current.ancillaries', []);
        $sent = $this->zenith->calls('ModifyBooking')[1];
        $this->assertSame([['Code' => 'WCHR', 'RefPassenger' => $adult, 'RefSegment' => $segment]], $sent['RemovedSpecialServices']);
        $this->assertSame([$bagRef], $sent['RemovedEMDTicketFares']);

        // A paid extra cannot be removed online.
        $this->api('POST', 'bookings/003PUX/extras', ['add' => ['ancillaries' => [['ref' => $offer['ancillaries'][0]['ref'], 'passengerRef' => $adult, 'segmentRef' => $segment]]]], $this->token)->assertOk();
        $booking = $this->zenith->booking;
        $paidRef = end($booking['FareInfo']['EMDTicketFares'])['Ref'];
        $booking['TicketInfo']['EMDTickets'] = [['DocumentNumber' => '9512100000099', 'RefEMDTicketFare' => $paidRef]];
        $this->zenith->booking = $booking;
        $this->api('POST', 'bookings/003PUX/extras', ['remove' => ['ancillaries' => [$paidRef]]], $this->token)
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_extras')
            ->assertJsonPath('error.problems.0.message', '25KG Extra Baggage INU-TRW | TRW-INU is already paid and cannot be removed online. Please contact Naoero Airlines.');

        // Nothing to do, someone else's booking, a bad seat.
        $this->api('POST', 'bookings/003PUX/extras', ['add' => []], $this->token)->assertStatus(400);
        $this->api('GET', 'bookings/003PUX/extras', token: $this->signUp('bo@example.com')['accessToken'])->assertNotFound();
        $this->api('POST', 'bookings/003PUX/extras', ['add' => ['seats' => [['passengerRef' => $infant, 'segmentRef' => $segment, 'seat' => $seat]]]], $this->token)
            ->assertStatus(422)->assertJsonPath('error.problems.0.passengerRef', $infant);
    }

    public function test_a_ticketed_booking_with_new_paid_extras_can_be_paid(): void
    {
        $this->zenith->prepare = null;
        $draft = $this->api('POST', 'bookings/prepare', ['offerRef' => 'O', 'itineraryRef' => 'I'], $this->token)->json('draftId');
        $doc = ['docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => [
            ['ref' => 'Traveler_Type_1_Index_0', 'values' => ['title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14', 'mobilePhone' => '+61 400 000 001', ...$doc]],
            ['ref' => 'Traveler_Type_2_Index_1', 'values' => ['firstName' => 'Amy', 'lastName' => 'Citizen', 'gender' => 'F', 'dateOfBirth' => '2018-05-05', ...$doc, 'docNumber' => 'PB1234567']],
            ['ref' => 'Traveler_Type_3_Index_2', 'values' => ['firstName' => 'Ben', 'lastName' => 'Citizen', 'gender' => 'M', 'dateOfBirth' => '2026-01-10', ...$doc, 'docNumber' => 'PC1234567']],
        ]], $this->token)->assertCreated();
        $paid = FakeZenith::fixture('CreateBooking')['Booking'];
        $paid['TicketInfo']['ETTickets'] = [['DocumentNumber' => '9512100000001', 'RefPassenger' => '237560']];
        $paid['MiscInfo']['ContainsIssuableTickets'] = false;
        $this->zenith->booking = $paid;
        $this->api('GET', 'bookings/003PUX', token: $this->token)->assertJsonPath('booking.status', 'ticketed');

        $offer = $this->api('GET', 'bookings/003PUX/extras', token: $this->token)->json('offer');
        $this->api('POST', 'bookings/003PUX/extras', ['add' => ['ancillaries' => [['ref' => $offer['ancillaries'][0]['ref'], 'passengerRef' => '237560', 'segmentRef' => '490318']]]], $this->token)
            ->assertOk()->assertJsonPath('booking.status', 'pending_payment');
        $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated();
    }
}
