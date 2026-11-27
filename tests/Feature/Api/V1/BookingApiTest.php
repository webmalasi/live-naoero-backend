<?php

namespace Tests\Feature\Api\V1;

use App\Services\Booking\BookingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingApiTest extends AppApiTestCase
{
    private const ADULT = 'Traveler_Type_1_Index_0';

    private const CHILD = 'Traveler_Type_2_Index_1';

    private const INFANT = 'Traveler_Type_3_Index_2';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
    }

    private function prepare(): array
    {
        return $this->api('POST', 'bookings/prepare', ['offerRef' => 'OFFER-1', 'itineraryRef' => 'IT_0'], $this->token)->assertOk()->json();
    }

    private function passengers(): array
    {
        $doc = ['docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01'];

        return [
            ['ref' => self::ADULT, 'values' => ['title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14', 'mobilePhone' => '+61 400 000 001', ...$doc]],
            ['ref' => self::CHILD, 'values' => ['firstName' => 'Amy', 'lastName' => 'Citizen', 'gender' => 'F', 'dateOfBirth' => '2018-05-05', ...$doc, 'docNumber' => 'PB1234567']],
            ['ref' => self::INFANT, 'values' => ['firstName' => 'Ben', 'lastName' => 'Citizen', 'gender' => 'M', 'dateOfBirth' => '2026-01-10', ...$doc, 'docNumber' => 'PC1234567']],
        ];
    }

    public function test_prepare_returns_the_form_price_and_prefill(): void
    {
        $prepared = $this->prepare();
        $this->assertSame(['Adult 1', 'Child 1', 'Infant 1'], array_column($prepared['form']['passengers'], 'label'));
        $this->assertSame('AUD', $prepared['price']['currency']);
        $this->assertSame(
            ['title' => 'MR', 'firstName' => 'Ana', 'lastName' => 'Citizen', 'email' => 'ana@example.com', 'mobilePhone' => '+61400000001'],
            $prepared['prefill'][self::ADULT],
        );
        $this->assertSame([], $prepared['savedTravellers']);
        $this->assertDatabaseHas('booking_drafts', ['id' => $prepared['draftId'], 'offer_ref' => 'OFFER-1']);
    }

    public function test_a_refused_offer_means_search_again(): void
    {
        $this->zenith->failures['PrepareFlights'] = FakeZenith::error('OfferExpired', 'Offer expired');
        $this->api('POST', 'bookings/prepare', ['offerRef' => 'OFFER-1', 'itineraryRef' => 'IT_0'], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'offer_changed');
    }

    public function test_passenger_problems_come_back_per_field(): void
    {
        $draft = $this->prepare()['draftId'];
        $passengers = $this->passengers();
        $passengers[2]['values']['dateOfBirth'] = '2023-01-01';
        $response = $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $passengers], $this->token)->assertStatus(422);
        $response->assertJsonPath('error.code', 'invalid_passengers')
            ->assertJsonPath('error.problems.0', ['passengerRef' => self::INFANT, 'field' => 'dateOfBirth', 'message' => 'Infant passengers must be under 2 for the whole trip.']);
        $this->assertCount(0, $this->zenith->calls('CreateBooking'));
    }

    public function test_zenith_invalid_data_is_mapped_to_fields(): void
    {
        $draft = $this->prepare()['draftId'];
        $this->zenith->failures['CreateBooking'] = FakeZenith::error('InvalidData', 'Invalid data', ['InvalidData' => [
            'SpecialServices' => [['Data' => ['Code' => 'DOCS', 'RefPassenger' => self::CHILD], 'Messages' => ['Document expiry date is invalid']]],
        ]]);
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)
            ->assertStatus(422)->assertJsonPath('error.problems.0.field', 'docExpiry')->assertJsonPath('error.problems.0.passengerRef', self::CHILD);
    }

    public function test_booking_payment_and_confirmation(): void
    {
        $draft = $this->prepare()['draftId'];
        $created = $this->api('POST', 'bookings', [
            'draftId' => $draft, 'passengers' => $this->passengers(), 'saveTravellers' => [self::ADULT, self::INFANT],
        ], $this->token)->assertCreated();
        $created->assertJsonPath('booking.pnr', '003PUX')->assertJsonPath('booking.status', 'pending_payment')->assertJsonPath('booking.amountDue', 1176.46);

        // What reached Zenith: names filled in, the infant on the adult's lap, the customer linked.
        $sent = $this->zenith->calls('CreateBooking')[0];
        $this->assertSame('test-zenith-key', $sent['RequestInfo']['AuthenticationKey']);
        $this->assertNotEmpty($sent['RefCustomer']);
        $infant = collect($sent['SpecialServices'])->first(fn ($s) => $s['Code'] === 'INFT');
        $this->assertSame(self::ADULT, $infant['Data']['Inft']['RefPassengerWithSeat']);

        // A draft is used once.
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)
            ->assertStatus(410)->assertJsonPath('error.code', 'draft_expired');

        // Saved travellers are offered for the right seats next time.
        $travellers = $this->api('GET', 'me/travellers', token: $this->token)->assertOk()->json('travellers');
        $this->assertEqualsCanonicalizing(['John', 'Ben'], array_column($travellers, 'firstName'));
        $offered = collect($this->prepare()['savedTravellers'])->pluck('eligibleFor', 'firstName');
        $this->assertSame([self::ADULT], $offered['John']);
        $this->assertSame([self::INFANT], $offered['Ben']);

        $this->api('GET', 'bookings', token: $this->token)->assertOk()->assertJsonPath('bookings.0.pnr', '003PUX')->assertJsonStructure(['bookings' => [['createdAt']]]);
        $this->api('GET', 'bookings/003pux', token: $this->token)->assertOk()->assertJsonPath('booking.pnr', '003PUX');

        $payment = $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated();
        $payment->assertJsonPath('redirectUrl', 'https://payments.example/checkout/abc')->assertJsonPath('amount', 1176.46);
        $this->assertStringStartsWith('https://payment-return.naoero.invalid/', $payment->json('returnUrlPrefix'));
        $fop = $this->zenith->calls('CreateTicket')[0]['Fops'][0];
        $this->assertSame('NAB', $fop['Code']);
        $this->assertSame($payment->json('returnUrlPrefix').'accept', $fop['RedirectInfo']['AcceptUrl']);

        // The app says "declined": Zenith still decides.
        $this->api('POST', "bookings/003PUX/payments/{$payment->json('paymentId')}", ['outcome' => 'decline'], $this->token)
            ->assertOk()->assertJsonPath('payment.status', 'declined')->assertJsonPath('booking.status', 'pending_payment');

        // Tickets issued: the booking is confirmed.
        $paid = FakeZenith::fixture('CreateBooking')['Booking'];
        $paid['TicketInfo']['ETTickets'] = [['DocumentNumber' => '9512100000001', 'RefPassenger' => '237560']];
        $paid['MiscInfo']['ContainsIssuableTickets'] = false;
        $this->zenith->booking = $paid;
        $this->api('POST', "bookings/003PUX/payments/{$payment->json('paymentId')}", ['outcome' => 'accept'], $this->token)
            ->assertOk()->assertJsonPath('payment.status', 'confirmed')->assertJsonPath('booking.status', 'ticketed')
            ->assertJsonPath('booking.tickets.0.number', '9512100000001')->assertJsonPath('booking.amountDue', 0);
        $this->assertDatabaseHas('booking_payments', ['id' => $payment->json('paymentId'), 'status' => 'confirmed']);

        $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'already_paid');
        $this->api('POST', 'bookings/003PUX/confirmation', [], $this->token)->assertOk()->assertJsonPath('ok', true);
    }

    public function test_the_ways_to_pay_come_from_the_airline(): void
    {
        $this->zenith->extraForms = [
            'TTI_TEST' => [['AUD', 'NZD', 'EUR'], null],
            'NEW_WALLET' => [['AUD'], 'https://pay.example/logo.png'],
            'EURO_ONLY' => [['EUR'], null],
        ];
        Cache::store(config('naoero.cache_store'))->forget('naoero:zenith:SaleConfiguration');
        $this->api('POST', 'bookings', ['draftId' => $this->prepare()['draftId'], 'passengers' => $this->passengers()], $this->token)->assertCreated();

        // Offered in the booking's currency, in the airline's order; a new option needs no change here.
        $res = $this->api('GET', 'bookings/003PUX/payment-options', token: $this->token)->assertOk();
        $res->assertJsonPath('currency', 'AUD')->assertJsonPath('amount', 1176.46);
        $this->assertSame(['NAB', 'TTI_TEST', 'NEW_WALLET'], array_column($res->json('options'), 'code'));
        $res->assertJsonPath('options.0.kind', 'card')->assertJsonPath('options.1.kind', 'test')
            ->assertJsonPath('options.2.name', 'New Wallet')->assertJsonPath('options.2.logoUrl', 'https://pay.example/logo.png');

        // The chosen one goes to the airline.
        $this->api('POST', 'bookings/003PUX/payments', ['fop' => 'TTI_TEST'], $this->token)->assertCreated();
        $this->assertSame('TTI_TEST', $this->zenith->calls('CreateTicket')[0]['Fops'][0]['Code']);

        // No choice (the website, older apps): the airline's first.
        $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated();
        $this->assertSame('NAB', $this->zenith->calls('CreateTicket')[1]['Fops'][0]['Code']);

        // Only what is offered for this currency; a bad code never reaches the airline.
        $this->api('POST', 'bookings/003PUX/payments', ['fop' => 'EURO_ONLY'], $this->token)->assertStatus(409)->assertJsonPath('error.code', 'payment_option_unavailable');
        $this->api('POST', 'bookings/003PUX/payments', ['fop' => 'no spaces!'], $this->token)->assertStatus(400);
        $this->assertCount(2, $this->zenith->calls('CreateTicket'));

        // Somebody else's booking is refused.
        $this->api('GET', 'bookings/AAAAAA/payment-options', token: $this->token)->assertNotFound();
    }

    public function test_the_airline_payment_form_is_made_mobile_friendly(): void
    {
        $form = '<html><head><title>Secure payment form</title></head><body><form action="https://tstws2.ttinteractive.com/NABResponse.ashx" method="post"><input name="EPS_CARDNUMBER"></form></body></html>';
        $out = BookingService::mobileFriendly($form);

        $this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', $out);
        $this->assertStringContainsString('<style id="naoero-mobile-payment">', $out);
        $this->assertLessThan(strpos($out, '</head>'), strpos($out, 'naoero-mobile-payment'));
        // The form itself is untouched: same fields, same destination.
        $this->assertStringContainsString('<form action="https://tstws2.ttinteractive.com/NABResponse.ashx" method="post"><input name="EPS_CARDNUMBER"></form>', $out);
        // Applied once only; nothing to do without HTML.
        $this->assertSame($out, BookingService::mobileFriendly($out));
        $this->assertNull(BookingService::mobileFriendly(null));
    }

    public function test_the_airline_card_form_is_served_from_our_own_address(): void
    {
        $draft = $this->prepare()['draftId'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)->assertCreated();
        $this->zenith->createTicket = ['RedirectResult' => ['UrlGet' => null, 'HtmlForm' => '<html><head><title>Secure payment form</title></head><body>'
            .'<img src="NauruAirlines/images_site/logo_billet.png"><form action="https://tstws2.ttinteractive.com/TTIDotNet/NABResponse.ashx?paramplus=1" method="post">'
            .'<input name="EPS_CARDNUMBER"></form></body></html>']];

        // The client gets our page to open, never the raw HTML.
        $payment = $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated();
        $payment->assertJsonPath('html', null);
        $paymentId = $payment->json('paymentId');
        $url = $payment->json('redirectUrl');
        $this->assertStringStartsWith(rtrim(config('app.url'), '/')."/api/v1/bookings/payments/{$paymentId}/form?t=", $url);
        $secret = substr($url, strpos($url, '?t=') + 3);
        $this->assertSame(40, strlen($secret));
        $stored = DB::table('booking_payments')->where('id', $paymentId)->first(['form_html', 'form_token_hash']);
        $this->assertSame(hash('sha256', $secret), $stored->form_token_hash, 'only a hash of the secret is kept');
        $this->assertStringNotContainsString($secret, $stored->form_html);

        // The form opens without sign-in (the WebView has no token), made for phones,
        // with relative addresses still pointing at the airline.
        $form = "/api/v1/bookings/payments/{$paymentId}/form?t={$secret}";
        $page = $this->get($form)->assertOk();
        $page->assertHeader('Content-Type', 'text/html; charset=utf-8')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertStringContainsString("frame-ancestors 'self'", $page->headers->get('Content-Security-Policy'));
        $html = $page->getContent();
        $this->assertStringContainsString('<base href="https://tstws2.ttinteractive.com/">', $html);
        $this->assertStringContainsString('naoero-mobile-payment', $html);
        $this->assertStringContainsString('<form action="https://tstws2.ttinteractive.com/TTIDotNet/NABResponse.ashx?paramplus=1" method="post"><input name="EPS_CARDNUMBER"></form>', $html);
        $this->get($form)->assertOk(); // a reload works while paying

        // A wrong, missing or foreign link gets nothing.
        $this->get("/api/v1/bookings/payments/{$paymentId}/form?t=".str_repeat('x', 40))->assertNotFound()->assertSee('This payment link is not valid');
        $this->get("/api/v1/bookings/payments/{$paymentId}/form")->assertNotFound();
        $this->get('/api/v1/bookings/payments/not-a-payment/form?t=x')->assertNotFound();

        // Once the payment page has ended, the form is gone.
        $this->api('POST', "bookings/003PUX/payments/{$paymentId}", ['outcome' => 'decline'], $this->token)->assertOk();
        $this->get($form)->assertStatus(410)->assertSee('This payment page has closed');
        $this->assertDatabaseHas('booking_payments', ['id' => $paymentId, 'form_html' => null]);
        // Only the real link is told so: a guessed one still learns nothing.
        $this->get("/api/v1/bookings/payments/{$paymentId}/form?t=".str_repeat('x', 40))->assertNotFound()->assertDontSee('closed');

        // And an open form lapses after an hour.
        $second = $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated()->json('redirectUrl');
        $path = substr($second, strlen(rtrim(config('app.url'), '/')));
        $this->get($path)->assertOk();
        $this->travel(BookingService::FORM_MINUTES + 1)->minutes();
        $this->get($path)->assertStatus(410)->assertSee('This payment page has expired');
    }

    public function test_a_passenger_already_on_the_flight_is_told_so(): void
    {
        $this->zenith->failures['CreateBooking'] = FakeZenith::error('DupePassengerFlight', 'These passengers are already booked on a flight.');
        $this->api('POST', 'bookings', ['draftId' => $this->prepare()['draftId'], 'passengers' => $this->passengers()], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'passenger_already_booked')
            ->assertJsonPath('error.message', 'A passenger is already booked on this flight. Check your existing bookings or contact Naoero Airlines.');

        // The older wording is still recognised.
        $this->zenith->failures['CreateBooking'] = FakeZenith::error('', 'Passenger already registered on this flight');
        $this->api('POST', 'bookings', ['draftId' => $this->prepare()['draftId'], 'passengers' => $this->passengers()], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'passenger_already_booked');
    }

    public function test_a_duplicate_names_the_unpaid_booking_only_on_the_same_flight(): void
    {
        // An unpaid booking (003PUX), put on the very flight being booked again.
        $this->api('POST', 'bookings', ['draftId' => $this->prepare()['draftId'], 'passengers' => $this->passengers()], $this->token)->assertCreated();
        $moveTo = function (array $segments): void {
            $stored = json_decode(DB::table('bookings')->where('pnr', '003PUX')->value('zenith_booking'), true);
            $stored['Segments'] = $segments;
            DB::table('bookings')->where('pnr', '003PUX')->update(['zenith_booking' => json_encode($stored)]);
        };
        $prepared = FakeZenith::fixture('PrepareFlights')['Segments'];
        $moveTo($prepared);
        $this->zenith->failures['CreateBooking'] = FakeZenith::error('DupePassengerFlight', 'These passengers are already booked on a flight.');
        $this->api('POST', 'bookings', ['draftId' => $this->prepare()['draftId'], 'passengers' => $this->passengers()], $this->token)
            ->assertStatus(409)->assertJsonPath('error.pendingPnr', '003PUX')
            ->assertJsonPath('error.message', 'A passenger is already booked on this flight in booking 003PUX. Pay for that booking from My Trips, or wait for it to expire.');

        // The same unpaid booking, but on another flight: not named.
        $prepared[0]['FlightInfo']['DepartureDate'] = '2030-01-01T09:00:00';
        $moveTo($prepared);
        $this->api('POST', 'bookings', ['draftId' => $this->prepare()['draftId'], 'passengers' => $this->passengers()], $this->token)
            ->assertStatus(409)->assertJsonMissingPath('error.pendingPnr')
            ->assertJsonPath('error.message', 'A passenger is already booked on this flight. Check your existing bookings or contact Naoero Airlines.');
    }

    public function test_a_website_payment_returns_to_the_website(): void
    {
        config(['naoero.shop.return_hosts' => ['napacificbridge.info'], 'app.url' => 'https://api.napacificbridge.info']);
        $draft = $this->prepare()['draftId'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)->assertCreated();

        // Only https pages on the website's own hosts are accepted.
        $this->api('POST', 'bookings/003PUX/payments', ['returnUrl' => 'https://evil.example/back'], $this->token)
            ->assertStatus(400)->assertJsonPath('error.code', 'return_url_not_allowed');
        $this->api('POST', 'bookings/003PUX/payments', ['returnUrl' => 'http://napacificbridge.info/back'], $this->token)
            ->assertStatus(400);

        // The payment page returns to our address, not the app-only one.
        $payment = $this->api('POST', 'bookings/003PUX/payments', ['returnUrl' => 'https://napacificbridge.info/booking-confirmation/'], $this->token)->assertCreated();
        $id = $payment->json('paymentId');
        $prefix = "https://api.napacificbridge.info/api/v1/bookings/payments/{$id}/return/";
        $payment->assertJsonPath('returnUrlPrefix', $prefix);
        $fop = $this->zenith->calls('CreateTicket')[0]['Fops'][0];
        $this->assertSame("{$prefix}accept", $fop['RedirectInfo']['AcceptUrl']);
        $this->assertSame("{$prefix}cancel", $fop['RedirectInfo']['CancelUrl']);
        $this->assertDatabaseHas('booking_payments', ['id' => $id, 'return_url' => 'https://napacificbridge.info/booking-confirmation/']);

        // The browser is sent on to the website with what it needs; nothing is decided here.
        $this->get("/api/v1/bookings/payments/{$id}/return/accept")->assertStatus(303)
            ->assertHeader('Location', "https://napacificbridge.info/booking-confirmation/?pnr=003PUX&paymentId={$id}&outcome=accept");
        $this->post("/api/v1/bookings/payments/{$id}/return/decline")->assertStatus(303);
        $this->assertDatabaseHas('booking_payments', ['id' => $id, 'status' => 'started']);

        // The website then asks for the real result, as the app does.
        $this->api('POST', "bookings/003PUX/payments/{$id}", ['outcome' => 'accept'], $this->token)
            ->assertOk()->assertJsonPath('booking.status', 'pending_payment');

        // Placeholders in the page are filled in instead.
        $second = $this->api('POST', 'bookings/003PUX/payments', ['returnUrl' => 'https://napacificbridge.info/trips/{pnr}?p={paymentId}&r={outcome}'], $this->token)->assertCreated();
        $sid = $second->json('paymentId');
        $this->get("/api/v1/bookings/payments/{$sid}/return/cancel")
            ->assertHeader('Location', "https://napacificbridge.info/trips/003PUX?p={$sid}&r=cancel");
    }

    public function test_payment_returns_without_a_website_page(): void
    {
        $draft = $this->prepare()['draftId'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)->assertCreated();

        // The app's payments keep their own return address and never redirect.
        $app = $this->api('POST', 'bookings/003PUX/payments', [], $this->token)->assertCreated();
        $this->assertStringStartsWith('https://payment-return.naoero.invalid/', $app->json('returnUrlPrefix'));
        $this->get("/api/v1/bookings/payments/{$app->json('paymentId')}/return/accept")->assertOk()->assertSee('You can close this window');

        // Unknown payments and outcomes.
        $this->get('/api/v1/bookings/payments/not-a-uuid/return/accept')->assertOk()->assertSee('You can close this window');
        $this->get('/api/v1/bookings/payments/'.Str::uuid().'/return/accept')->assertOk();
        $this->get("/api/v1/bookings/payments/{$app->json('paymentId')}/return/hacked")->assertNotFound();

        // A website page is refused while no website hosts are configured.
        config(['naoero.shop.return_hosts' => []]);
        $this->api('POST', 'bookings/003PUX/payments', ['returnUrl' => 'https://napacificbridge.info/x'], $this->token)
            ->assertStatus(503)->assertJsonPath('error.code', 'website_payments_not_configured');
    }

    public function test_bookings_belong_to_their_customer(): void
    {
        $draft = $this->prepare()['draftId'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $this->token)->assertCreated();

        $other = $this->signUp('bo@example.com')['accessToken'];
        $this->api('GET', 'bookings/003PUX', token: $other)->assertNotFound()->assertJsonPath('error.code', 'booking_not_found');
        $this->api('POST', 'bookings/003PUX/payments', [], $other)->assertNotFound();
        $this->api('GET', 'bookings', token: $other)->assertOk()->assertJsonPath('bookings', []);
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers()], $other)->assertStatus(410);
        $this->api('GET', 'bookings/bad!', token: $other)->assertStatus(400);
    }

    public function test_saved_travellers_can_be_removed(): void
    {
        $draft = $this->prepare()['draftId'];
        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => $this->passengers(), 'saveTravellers' => [self::CHILD]], $this->token)->assertCreated();
        $id = $this->api('GET', 'me/travellers', token: $this->token)->json('travellers.0.id');
        $this->api('DELETE', "me/travellers/{$id}", token: $this->token)->assertOk()->assertJsonPath('removed', true);
        $this->assertSame(0, DB::table('saved_travellers')->count());
    }
}
