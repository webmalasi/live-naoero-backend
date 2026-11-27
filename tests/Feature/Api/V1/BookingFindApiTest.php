<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;

/**
 * Bookings made elsewhere (website, travel agent, airport): found on the
 * customer's airline account (SearchBooking) or added with the booking
 * reference and a passenger's surname, then managed like any other booking.
 */
class BookingFindApiTest extends AppApiTestCase
{
    private string $token;

    private string $ref;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
        $this->ref = (string) DB::table('customers')->value('zenith_customer_ref');
    }

    /** A real booking (fixture) with its own reference, passenger name and owner. */
    private function booking(string $pnr, string $surname, string $first = 'JOHN', ?string $owner = null, bool $ticketed = false): array
    {
        $b = FakeZenith::fixture('CreateBooking')['Booking'];
        $b['PnrInformation']['PnrCode'] = $pnr;
        $b['PnrInformation']['RefCustomer'] = $owner;
        $b['Passengers'][0]['NameElement']['Surname'] = $surname;
        $b['Passengers'][0]['NameElement']['Firstname'] = $first;
        if ($ticketed) {
            $b['TicketInfo']['ETTickets'] = [['DocumentNumber' => '9512100000777', 'RefPassenger' => '237560']];
            $b['MiscInfo']['ContainsIssuableTickets'] = false;
        }

        return $b;
    }

    private function find(array $body, ?string $token = null)
    {
        return $this->api('POST', 'bookings/find', $body, $token ?? $this->token);
    }

    public function test_a_booking_made_elsewhere_is_added_with_its_reference_and_surname(): void
    {
        $this->zenith->pnrBookings = ['AGT123' => $this->booking('AGT123', "O'BRIEN-NÚÑEZ", 'MARY ANN', owner: 'agency-99')];

        // Accents, case, spaces and punctuation don't matter; the first name is optional and may be partial.
        $added = $this->find(['pnr' => 'agt123', 'lastName' => 'obrien nunez', 'firstName' => 'Mary'])->assertCreated();
        $added->assertJsonPath('booking.pnr', 'AGT123')
            ->assertJsonPath('booking.source', 'reference')
            ->assertJsonPath('booking.sourceLabel', 'Added with your booking reference');
        $sent = $this->zenith->calls('LoadBooking')[0];
        $this->assertSame(['PassengerName' => 'OBRIEN NUNEZ'], $sent['Verification']);
        $this->assertDatabaseHas('bookings', ['pnr' => 'AGT123', 'source' => 'reference', 'verify_surname' => "O'BRIEN-NÚÑEZ"]);
        $this->assertDatabaseHas('auth_events', ['event' => 'booking_find', 'success' => true]);

        // It is in the list, and every later call is verified by the passenger's name.
        $this->zenith->searchResults = [];
        $list = $this->api('GET', 'bookings', token: $this->token)->assertOk();
        $this->assertSame(['AGT123'], array_column($list->json('bookings'), 'pnr'));
        $this->assertSame('reference', $list->json('bookings.0.source'));
        $this->assertNotNull($list->json('bookings.0.linkedAt'));

        $this->api('GET', 'bookings/AGT123', token: $this->token)->assertOk()->assertJsonPath('booking.source', 'reference');
        $this->assertSame(['PassengerName' => "O'BRIEN-NÚÑEZ"], end($this->zenith->calls)[1]['Verification']);
        $this->api('POST', 'bookings/AGT123/payments', [], $this->token)->assertCreated();
        $this->assertSame(['PassengerName' => "O'BRIEN-NÚÑEZ"], $this->zenith->calls('CreateTicket')[0]['Verification']);
        $this->api('POST', 'bookings/AGT123/confirmation', [], $this->token)->assertOk();
        $this->assertSame(['PassengerName' => "O'BRIEN-NÚÑEZ"], $this->zenith->calls('SendTicketConfirmation')[0]['Verification']);

        // Adding it again is harmless.
        $this->find(['pnr' => 'AGT123', 'lastName' => "O'Brien-Núñez"])->assertCreated()->assertJsonPath('booking.source', 'reference');
        $this->assertSame(1, DB::table('bookings')->count());
    }

    public function test_a_booking_is_found_with_a_ticket_number_and_its_passenger_surname(): void
    {
        $booking = $this->booking('TKT123', 'CITIZEN', ticketed: true);
        $booking['Passengers'][1]['NameElement']['Surname'] = 'OTHER';
        $this->zenith->pnrBookings = ['TKT123' => $booking];

        // The name must be the ticket holder's, not just anyone on the booking.
        $this->find(['ticketNumber' => '951 2100000777', 'lastName' => 'Other'])->assertNotFound()->assertJsonPath('error.code', 'booking_not_found');
        $this->find(['ticketNumber' => '9512100000999', 'lastName' => 'Citizen'])->assertNotFound();
        $this->find(['ticketNumber' => '12345', 'lastName' => 'Citizen'])->assertStatus(400)->assertJsonPath('error.code', 'invalid_request');
        $this->find(['lastName' => 'Citizen'])->assertStatus(400)->assertJsonPath('error.message', 'Enter your booking reference or ticket number.');

        $this->find(['ticketNumber' => '951-2100000777', 'lastName' => 'citizen'])->assertCreated()
            ->assertJsonPath('booking.pnr', 'TKT123')->assertJsonPath('booking.source', 'reference');
        $sent = collect($this->zenith->calls('LoadBooking'))->last();
        $this->assertSame(['TypeCode' => 'ETTicketNumber', 'ID' => '9512100000777'], $sent['UniqueID']);
        $this->assertDatabaseHas('bookings', ['pnr' => 'TKT123', 'verify_surname' => 'CITIZEN']);
    }

    public function test_a_wrong_reference_or_name_reveals_nothing_and_is_limited(): void
    {
        $this->zenith->pnrBookings = ['AGT123' => $this->booking('AGT123', 'CITIZEN', 'JOHN')];
        $message = $this->find(['pnr' => 'ZZZ999', 'lastName' => 'Citizen'])->assertNotFound()->assertJsonPath('error.code', 'booking_not_found')->json('error.message');
        // Zenith does not check the name with every key: we do.
        $this->assertSame($message, $this->find(['pnr' => 'AGT123', 'lastName' => 'Smith'])->assertNotFound()->json('error.message'));
        $this->assertSame($message, $this->find(['pnr' => 'AGT123', 'lastName' => 'Citizen', 'firstName' => 'Jane'])->assertNotFound()->json('error.message'));
        $this->assertSame(0, DB::table('bookings')->count());

        foreach ([['pnr' => 'AB', 'lastName' => 'Citizen'], ['pnr' => 'AGT-123', 'lastName' => 'Citizen'], ['pnr' => 'AGT123', 'lastName' => ''], ['pnr' => 'AGT123', 'lastName' => '12345']] as $bad) {
            $this->find($bad)->assertStatus(400)->assertJsonPath('error.code', 'invalid_request');
        }
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Smith'])->assertNotFound();
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Smith'])->assertNotFound();
        // Five wrong answers in 15 minutes: even the right one waits.
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Citizen'])->assertStatus(429);
        $this->api('POST', 'bookings/find', ['pnr' => 'AGT123', 'lastName' => 'Citizen'])->assertUnauthorized();
    }

    public function test_a_booking_on_another_account_is_never_shared(): void
    {
        $this->zenith->pnrBookings = ['AGT123' => $this->booking('AGT123', 'CITIZEN')];
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Citizen'])->assertCreated();

        $other = $this->signUp('bo@example.com')['accessToken'];
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Citizen'], $other)
            ->assertStatus(409)->assertJsonPath('error.code', 'booking_linked_elsewhere');
        $this->api('GET', 'bookings/AGT123', token: $other)->assertNotFound();
        $this->assertSame(1, DB::table('bookings')->count());
    }

    public function test_bookings_on_the_airline_account_are_found_and_kept_up_to_date(): void
    {
        $this->zenith->pnrBookings = [
            'WEB111' => $this->booking('WEB111', 'CITIZEN', owner: $this->ref, ticketed: true),
            'AIR222' => $this->booking('AIR222', 'CITIZEN', owner: $this->ref),
        ];
        $this->zenith->searchResults = [
            ['PnrCode' => 'WEB111', 'PnrStatusCode' => 'Issued', 'NextActiveAirportCode' => 'BNE', 'NextActiveArrivalAirportCode' => 'INU', 'NextActiveDepartureDate' => '2026-12-01T06:20:00'],
            ['PnrCode' => 'AIR222', 'PnrStatusCode' => 'Option', 'NextActiveAirportCode' => 'BNE', 'NextActiveDepartureDate' => '2026-11-20T06:20:00', 'TimeLimit' => now()->addDay()->toIso8601String()],
        ];

        $list = $this->api('GET', 'bookings', token: $this->token)->assertOk();
        $this->assertSame(['status' => 'synced', 'found' => 2, 'imported' => 2, 'updated' => 0], array_diff_key($list->json('sync'), ['syncedAt' => 1]));
        $this->assertEqualsCanonicalizing(['WEB111', 'AIR222'], array_column($list->json('bookings'), 'pnr'));
        $this->assertSame(['account'], array_unique(array_column($list->json('bookings'), 'source')));
        $this->assertSame(['CustomerRef' => $this->ref], array_intersect_key($this->zenith->calls('SearchBooking')[0], ['CustomerRef' => 1]));
        $this->assertDatabaseHas('bookings', ['pnr' => 'WEB111', 'status' => 'ticketed', 'source' => 'account', 'verify_surname' => null]);

        // Searched at most every 5 minutes; a refresh at most every 30 seconds.
        $this->api('GET', 'bookings', token: $this->token)->assertOk()->assertJsonPath('sync.status', 'recent');
        $this->api('GET', 'bookings?refresh=1', token: $this->token)->assertOk()->assertJsonPath('sync.status', 'recent');
        $this->assertCount(1, $this->zenith->calls('SearchBooking'));

        // The airline issued the tickets for AIR222: the next search refreshes it.
        $this->zenith->pnrBookings['AIR222'] = $this->booking('AIR222', 'CITIZEN', owner: $this->ref, ticketed: true);
        $this->zenith->searchResults[1]['PnrStatusCode'] = 'Issued';
        $this->travel(31)->seconds();
        $this->api('GET', 'bookings?refresh=1', token: $this->token)->assertOk()
            ->assertJsonPath('sync.status', 'synced')->assertJsonPath('sync.imported', 0)->assertJsonPath('sync.updated', 1);
        $this->assertDatabaseHas('bookings', ['pnr' => 'AIR222', 'status' => 'ticketed']);

        // Adding an account booking by reference keeps it as an account booking.
        $this->find(['pnr' => 'WEB111', 'lastName' => 'Citizen'])->assertCreated()->assertJsonPath('booking.source', 'account');

        // The airline down: the list still works.
        $this->zenith->failures['SearchBooking'] = 'down';
        $this->travel(6)->minutes();
        $this->api('GET', 'bookings', token: $this->token)->assertOk()
            ->assertJsonPath('sync.status', 'unavailable')->assertJsonCount(2, 'bookings');
    }

    public function test_added_bookings_can_be_removed_but_not_ours(): void
    {
        $this->zenith->pnrBookings = [
            'AGT123' => $this->booking('AGT123', 'CITIZEN'),
            'WEB111' => $this->booking('WEB111', 'CITIZEN', owner: $this->ref),
        ];
        $this->zenith->searchResults = [['PnrCode' => 'WEB111', 'PnrStatusCode' => 'Option']];
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Citizen'])->assertCreated();
        $this->api('GET', 'bookings', token: $this->token)->assertJsonCount(2, 'bookings');

        $this->api('DELETE', 'bookings/AGT123', token: $this->token)->assertOk()->assertJsonPath('removed', true);
        $this->api('DELETE', 'bookings/WEB111', token: $this->token)->assertOk();
        $this->travel(6)->minutes();
        // Removed bookings stay removed, even when the account is searched again…
        $this->api('GET', 'bookings', token: $this->token)->assertOk()->assertJsonPath('bookings', []);
        $this->api('GET', 'bookings/AGT123', token: $this->token)->assertNotFound();
        // …until the customer adds one again.
        $this->find(['pnr' => 'AGT123', 'lastName' => 'Citizen'])->assertCreated();
        $this->api('GET', 'bookings?sync=0', token: $this->token)->assertJsonPath('bookings.0.pnr', 'AGT123')->assertJsonPath('sync.status', 'skipped');

        // A booking made here stays.
        DB::table('bookings')->where('pnr', 'AGT123')->update(['source' => 'app']);
        $this->api('DELETE', 'bookings/AGT123', token: $this->token)->assertStatus(409)->assertJsonPath('error.code', 'cannot_remove');
        $this->api('DELETE', 'bookings/NOPE99', token: $this->token)->assertNotFound();
    }
}
