<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;

class FlightApiTest extends AppApiTestCase
{
    private function search(array $overrides = []): array
    {
        return [
            'legs' => [['origin' => 'BNE', 'destination' => 'INU', 'date' => '2026-10-22']],
            'passengers' => ['AD' => 1],
            'currency' => 'AUD',
            ...$overrides,
        ];
    }

    public function test_flight_config_lists_routes_dates_and_currencies(): void
    {
        $response = $this->api('GET', 'flight-config')->assertOk()->assertHeader('X-Request-Id');
        $response->assertJsonPath('airline.code', 'ON')
            ->assertJsonPath('routes.BNE', ['INU', 'MAJ', 'NAN', 'PNI', 'TRW'])
            ->assertJsonPath('defaultCurrency', 'AUD')
            ->assertJsonPath('maxLegs', 2);
        $this->assertSame(['AUD', 'USD'], array_column($response->json('currencies'), 'code'));
        $this->assertNotEmpty($response->json('flightDates.BNE-INU'));
    }

    public function test_config_is_cached_between_requests(): void
    {
        $this->api('GET', 'flight-config')->assertOk();
        $this->api('GET', 'flight-config')->assertOk();
        $this->assertCount(1, $this->zenith->calls('SaleConfiguration'));
    }

    public function test_app_token_is_enforced_when_configured(): void
    {
        config(['naoero.app_access_token' => 'secret-app-token']);
        $this->api('GET', 'flight-config')->assertStatus(401)->assertJsonPath('error.code', 'unauthorized');
        $this->api('GET', 'flight-config', headers: ['X-App-Token' => 'secret-app-token'])->assertOk();
    }

    public function test_search_returns_journeys_and_never_sends_the_key_to_the_app(): void
    {
        $response = $this->api('POST', 'flight-search', $this->search())->assertOk();
        $response->assertJsonPath('currency', 'AUD')->assertJsonPath('journeys.0.legs.0.segments.0.flightNumber', 'ON041');
        $this->assertStringNotContainsString('test-zenith-key', $response->getContent());

        $sent = $this->zenith->calls('SearchFlights')[0];
        $this->assertSame('test-zenith-key', $sent['RequestInfo']['AuthenticationKey']);
        $this->assertSame('2026-10-22T00:00:00', $sent['OriginDestinations'][0]['TargetDate']);
    }

    public function test_invalid_searches_are_refused_before_zenith(): void
    {
        $this->api('POST', 'flight-search', $this->search(['legs' => [['origin' => 'BNE', 'destination' => 'SUV', 'date' => '2026-10-22']]]))
            ->assertStatus(400)->assertJsonPath('error.message', 'Leg 1: BNE to SUV is not a route we fly.');
        $this->api('POST', 'flight-search', $this->search(['passengers' => ['AD' => 1.5]]))
            ->assertStatus(400)->assertJsonPath('error.code', 'invalid_request');
        $this->call('POST', '/api/v1/flight-search', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{not json')
            ->assertStatus(400)->assertJsonPath('error.message', 'Request body must be valid JSON.');
        $this->assertCount(0, $this->zenith->calls('SearchFlights'));
    }

    public function test_flexible_search_runs_the_neighbouring_days_too(): void
    {
        $response = $this->api('POST', 'flight-search', $this->search(['flexibleDays' => 1]))->assertOk();
        $this->assertNotEmpty($response->json('flexible'));
        $this->assertContains(['2026-10-22'], array_column($response->json('flexible'), 'dates'));
    }

    public function test_searches_are_rate_limited_per_device(): void
    {
        config(['naoero.search_rate_limit' => 2]);
        $this->api('POST', 'flight-search', $this->search())->assertOk();
        $this->api('POST', 'flight-search', $this->search())->assertOk();
        $this->api('POST', 'flight-search', $this->search())->assertStatus(429)
            ->assertJsonPath('error.code', 'rate_limited')->assertHeader('Retry-After');
    }

    public function test_zenith_errors_map_to_clear_responses(): void
    {
        $this->zenith->failures['SearchFlights'] = FakeZenith::error('NoAvailability', 'No flights');
        $this->api('POST', 'flight-search', $this->search())->assertOk()->assertJsonPath('noResultsReason', 'NoAvailability');

        $this->zenith->failures['SearchFlights'] = FakeZenith::error('Unknown', 'The promo   code is invalid');
        $this->api('POST', 'flight-search', $this->search())->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_promo')->assertJsonPath('error.message', 'The promo code is invalid');

        $this->zenith->failures['SearchFlights'] = FakeZenith::error('CallQuotaReached', 'Quota');
        $this->api('POST', 'flight-search', $this->search())->assertStatus(429)->assertJsonPath('error.code', 'busy');

        $this->zenith->failures['SearchFlights'] = 'down';
        $this->api('POST', 'flight-search', $this->search())->assertStatus(503)->assertJsonPath('error.code', 'unavailable');
    }

    public function test_countries_come_from_zenith(): void
    {
        $this->api('GET', 'reference/countries')->assertOk()
            ->assertJsonPath('countries', [['code' => 'AU', 'name' => 'Australia'], ['code' => 'FJ', 'name' => 'Fiji'], ['code' => 'NR', 'name' => 'Nauru']]);
    }

    public function test_every_request_is_logged_without_secrets(): void
    {
        $this->api('POST', 'flight-search', $this->search())->assertOk();
        $logs = DB::table('server_logs')->get();
        $this->assertTrue($logs->contains(fn ($l) => $l->source === 'http' && $l->status === 200));
        $this->assertTrue($logs->contains(fn ($l) => $l->source === 'zenith' && $l->message === 'SearchFlights ok'));
        $this->assertStringNotContainsString('test-zenith-key', $logs->toJson());
    }

    public function test_unknown_routes_answer_in_the_api_error_shape(): void
    {
        $this->api('GET', 'nope')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->api('GET', 'health')->assertOk()->assertJson(['ok' => true, 'zenithKeyConfigured' => true]);
        $this->get('/api/v1/openapi.json')->assertOk()->assertJsonPath('openapi', '3.1.0');
        $this->get('/api/v1/docs')->assertOk()->assertSee('swagger-ui');
    }
}
