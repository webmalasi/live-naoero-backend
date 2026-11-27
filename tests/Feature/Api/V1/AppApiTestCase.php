<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class AppApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeZenith $zenith;

    protected function setUp(): void
    {
        parent::setUp();
        // The fixtures were recorded on 15-16 October 2026.
        Date::setTestNow('2026-10-15 08:00:00');
        config([
            'naoero.zenith.key' => 'test-zenith-key',
            'naoero.jwt_secret' => str_repeat('s', 40),
            'naoero.app_access_token' => null,
            'naoero.cache_store' => 'array',
        ]);
        $this->zenith = (new FakeZenith)->install();
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    protected function api(string $method, string $uri, array $data = [], ?string $token = null, array $headers = []): TestResponse
    {
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->json($method, "/api/v1/{$uri}", $data, $headers);
    }

    /** Registers in Zenith and signs up through the API; returns the login response. */
    protected function signUp(string $email = 'ana@example.com', string $password = 'Passw0rd!'): array
    {
        return $this->api('POST', 'auth/signup', [
            'title' => 'mr', 'firstName' => 'Ana', 'lastName' => 'Citizen',
            'email' => $email, 'phone' => '+61 400 000 001', 'password' => $password, 'deviceLabel' => 'Test phone',
        ])->assertCreated()->json();
    }
}
