<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;

class AppLogApiTest extends AppApiTestCase
{
    public function test_app_logs_are_stored_with_secrets_redacted(): void
    {
        $token = $this->signUp()['accessToken'];
        $this->api('POST', 'logs/app', [
            'deviceId' => 'device-123456',
            'appVersion' => '1.0.0',
            'platform' => 'android',
            'entries' => [
                ['ts' => '2026-10-15T07:59:00.000Z', 'level' => 'info', 'event' => 'screen', 'message' => 'Home'],
                ['level' => 'error', 'event' => 'api', 'detail' => ['password' => 'x', 'path' => '/me']],
            ],
        ], $token)->assertOk()->assertJson(['ok' => true, 'stored' => 2]);

        $rows = DB::table('app_logs')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNotNull($rows[0]->customer_id);
        $this->assertSame(['password' => '[redacted]', 'path' => '/me'], json_decode($rows[1]->detail, true));
    }

    public function test_bad_batches_are_refused(): void
    {
        $this->api('POST', 'logs/app', ['deviceId' => 'short', 'entries' => [['level' => 'info', 'event' => 'x']]])->assertStatus(400);
        $this->api('POST', 'logs/app', ['deviceId' => 'device-123456', 'entries' => [['level' => 'loud', 'event' => 'x']]])->assertStatus(400);
    }
}
