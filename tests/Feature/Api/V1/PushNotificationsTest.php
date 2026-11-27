<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendPushNotification;
use App\Models\User;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\CustomerNotices;
use App\Services\Push\ApnsClient;
use App\Services\Push\PushCredentials;
use App\Services\Push\PushNotifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

/**
 * Phone pushes: the app registers its install (UID, token, choices) in our
 * database, and every new notice is pushed to the customer's phones through
 * APNs (iOS) and FCM (Android), once per phone, with what Apple or Google
 * answered recorded.
 */
class PushNotificationsTest extends AppApiTestCase
{
    private const IOS_UID = 'ios-install-0000000001';

    private const ANDROID_UID = 'android-install-000000001';

    private const IOS_TOKEN = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

    private string $token;

    /** @var list<Request> */
    private array $pushes = [];

    /** HTTP status Apple answers with, and its reason. */
    private array $apple = [200, null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), 'oauth2.googleapis.com')) {
                return Http::response(['access_token' => 'google-access', 'expires_in' => 3600]);
            }
            if (str_contains($request->url(), 'push.apple.com')) {
                $this->pushes[] = $request;

                return Http::response($this->apple[1] ? ['reason' => $this->apple[1]] : '', $this->apple[0], ['apns-id' => 'APNS-1']);
            }
            if (str_contains($request->url(), 'fcm.googleapis.com')) {
                $this->pushes[] = $request;

                return Http::response(['name' => 'projects/naoero-test/messages/1']);
            }

            return null;
        });
    }

    private function configure(): void
    {
        $ec = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($ec, $p8);
        $rsa = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($rsa, $pem);
        $credentials = app(PushCredentials::class);
        $credentials->save('apns', ['team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'bundle_id' => 'com.naoero.app', 'private_key' => $p8], true);
        $credentials->save('fcm', [
            'service_account' => ['type' => 'service_account', 'project_id' => 'naoero-test', 'client_email' => 'push@naoero-test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token'],
            'android' => ['app_id' => '1:123456789:android:abc123', 'api_key' => 'AIzaTest', 'sender_id' => '123456789'],
        ], true);
    }

    private function register(string $uid, array $body, ?string $token = null): TestResponse
    {
        return $this->api('PUT', "me/devices/{$uid}", $body, $token ?? $this->token);
    }

    private function registerBoth(): void
    {
        $this->register(self::IOS_UID, ['platform' => 'ios', 'pushToken' => self::IOS_TOKEN, 'apnsEnvironment' => 'sandbox', 'appVersion' => '1.0.0', 'deviceModel' => 'iPhone15,2'])->assertOk();
        $this->register(self::ANDROID_UID, ['platform' => 'android', 'pushToken' => 'fcm-token-1', 'appVersion' => '1.0.0'])->assertOk();
    }

    private function notify(string $type = 'booking_confirmed'): void
    {
        app(CustomerNotices::class)->send((string) DB::table('customers')->value('id'), $type, 'Booking 003PUX is confirmed', 'Your tickets are issued.', pnr: '003PUX');
    }

    public function test_the_app_registers_its_install_and_choices(): void
    {
        $device = $this->register(self::IOS_UID, ['platform' => 'ios', 'pushToken' => self::IOS_TOKEN, 'apnsEnvironment' => 'sandbox', 'osVersion' => 'iOS 18.1', 'locale' => 'en_AU'])
            ->assertOk()->json('device');
        $this->assertSame(['ios', 'apns', true, 'sandbox', true, true], [$device['platform'], $device['provider'], $device['hasPushToken'], $device['apnsEnvironment'], $device['pushEnabled'], $device['current']]);
        $this->assertArrayNotHasKey('pushToken', $device);

        $row = DB::table('customer_devices')->first();
        $this->assertNotSame(self::IOS_TOKEN, $row->push_token, 'the token is stored encrypted');
        $this->assertSame(self::IOS_TOKEN, Crypt::decryptString($row->push_token));
        $this->assertSame(DB::table('customers')->value('id'), $row->customer_id);

        // Choices: kept when a later launch leaves them out.
        $this->register(self::IOS_UID, ['platform' => 'ios', 'mutedCategories' => ['shop'], 'pushEnabled' => true])->assertOk()->assertJsonPath('device.mutedCategories', ['shop']);
        $this->register(self::IOS_UID, ['platform' => 'ios', 'pushToken' => self::IOS_TOKEN])->assertOk()
            ->assertJsonPath('device.mutedCategories', ['shop'])->assertJsonPath('device.hasPushToken', true);
        $this->assertSame(1, DB::table('customer_devices')->count());

        $this->api('GET', 'me/devices', token: $this->token, headers: ['X-Device-Uid' => self::IOS_UID])->assertOk()
            ->assertJsonPath('devices.0.deviceUid', self::IOS_UID)->assertJsonPath('devices.0.current', true);

        // Not an install UID / not an APNs token / not a category.
        $this->register('short', ['platform' => 'ios'])->assertStatus(400);
        $this->register(self::IOS_UID, ['platform' => 'ios', 'pushToken' => 'not-hex'])->assertStatus(400);
        $this->register(self::IOS_UID, ['platform' => 'ios', 'mutedCategories' => ['nope']])->assertStatus(400);
        $this->api('PUT', 'me/devices/'.self::IOS_UID, ['platform' => 'ios'])->assertStatus(401);

        // Signing out on the phone.
        $this->api('DELETE', 'me/devices/'.self::IOS_UID, token: $this->token)->assertOk()->assertJsonPath('unlinked', true);
        $this->assertNull(DB::table('customer_devices')->value('customer_id'));
    }

    public function test_a_reinstall_takes_the_token_and_a_phone_moves_with_its_account(): void
    {
        $this->register(self::ANDROID_UID, ['platform' => 'android', 'pushToken' => 'fcm-token-1', 'mutedCategories' => ['shop']])->assertOk();
        $this->register('android-reinstall-0000001', ['platform' => 'android', 'pushToken' => 'fcm-token-1'])->assertOk();
        $this->assertNull(DB::table('customer_devices')->where('device_uid', self::ANDROID_UID)->value('token_hash'));
        $this->assertSame(1, DB::table('customer_devices')->whereNotNull('token_hash')->count());

        $other = $this->signUp('ben@example.com')['accessToken'];
        $this->register(self::ANDROID_UID, ['platform' => 'android', 'pushToken' => 'fcm-token-2'], $other)->assertOk()
            ->assertJsonPath('device.mutedCategories', []);
        $this->assertSame(
            DB::table('customers')->where('zenith_login', 'ben@example.com')->value('id'),
            DB::table('customer_devices')->where('device_uid', self::ANDROID_UID)->value('customer_id'),
        );
    }

    public function test_the_push_config_gives_the_app_only_what_it_needs(): void
    {
        $this->api('GET', 'push/config')->assertOk()
            ->assertJsonPath('ios.enabled', false)->assertJsonPath('android.enabled', false)->assertJsonPath('android.firebase', null);

        $this->configure();
        $config = $this->api('GET', 'push/config')->assertOk()->json();
        $this->assertTrue($config['ios']['enabled']);
        $this->assertSame(['apiKey' => 'AIzaTest', 'appId' => '1:123456789:android:abc123', 'messagingSenderId' => '123456789', 'projectId' => 'naoero-test'], $config['android']['firebase']);
        $this->assertStringNotContainsString('PRIVATE KEY', json_encode($config));
        $this->assertStringNotContainsString('PRIVATE KEY', (string) DB::table('push_credentials')->value('config'), 'stored encrypted');
    }

    public function test_a_new_notice_is_pushed_to_each_phone_once(): void
    {
        $this->configure();
        $this->registerBoth();
        $this->notify();

        $this->assertCount(2, $this->pushes);
        [$apple, $google] = str_contains($this->pushes[0]->url(), 'apple') ? $this->pushes : array_reverse($this->pushes);
        $this->assertSame('https://api.sandbox.push.apple.com/3/device/'.self::IOS_TOKEN, $apple->url());
        $this->assertSame('com.naoero.app', $apple->header('apns-topic')[0]);
        $this->assertStringStartsWith('bearer ', $apple->header('authorization')[0]);
        $this->assertSame('Booking 003PUX is confirmed', $apple['aps']['alert']['title']);
        $this->assertSame(1, $apple['aps']['badge']);
        $this->assertSame(['screen' => 'booking', 'pnr' => '003PUX'], json_decode($apple['action'], true));

        $this->assertSame('https://fcm.googleapis.com/v1/projects/naoero-test/messages:send', $google->url());
        $this->assertSame('Bearer google-access', $google->header('Authorization')[0]);
        $this->assertSame('fcm-token-1', $google['message']['token']);
        $this->assertSame('naoero_updates', $google['message']['android']['notification']['channel_id']);
        $this->assertSame('bookings', $google['message']['data']['category']);

        $this->assertSame(['sent', 'sent'], DB::table('push_deliveries')->pluck('status')->all());
        $this->assertSame('APNS-1', DB::table('push_deliveries')->where('provider', 'apns')->value('provider_id'));

        // The job running again pushes nobody twice.
        (new SendPushNotification((string) DB::table('customer_notifications')->value('id')))->handle(app(PushNotifier::class), app(ServerLog::class));
        $this->assertCount(2, $this->pushes);
    }

    public function test_pushes_respect_choices_sessions_and_dead_tokens(): void
    {
        $this->configure();
        $this->registerBoth();
        $this->register(self::ANDROID_UID, ['platform' => 'android', 'mutedCategories' => ['bookings']])->assertOk();
        $this->apple = [410, 'Unregistered'];
        $this->notify();

        $this->assertCount(1, $this->pushes, 'the Android phone turned bookings off');
        $this->assertNotNull(DB::table('customer_devices')->where('device_uid', self::IOS_UID)->value('token_invalid_at'));
        $this->assertSame('invalid_token', DB::table('push_deliveries')->value('status'));

        // A dead token is not tried again; a phone signed out gets nothing.
        $this->register(self::ANDROID_UID, ['platform' => 'android', 'mutedCategories' => []])->assertOk();
        $this->api('POST', 'auth/logout', ['allDevices' => true], $this->token)->assertOk();
        $this->notify('shop_order_paid');
        $this->assertCount(1, $this->pushes);
    }

    public function test_the_apns_signature_is_a_valid_es256_jwt(): void
    {
        $ec = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($ec, $p8);
        $jwt = ApnsClient::sign(['team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'private_key' => $p8], 1_700_000_000);
        [$h, $c, $s] = explode('.', $jwt);
        $this->assertSame(['alg' => 'ES256', 'kid' => 'KEY1234567'], json_decode(base64_decode(strtr($h, '-_', '+/')), true));
        $raw = base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
        $this->assertSame(64, strlen($raw));
        // Back to DER, as openssl verifies it.
        $int = fn (string $b) => "\x02".chr(strlen($b = (ord(ltrim($b, "\0")[0] ?? "\0") > 0x7F ? "\0" : '').ltrim($b, "\0"))).$b;
        $der = $int(substr($raw, 0, 32)).$int(substr($raw, 32));
        $der = "\x30".chr(strlen($der)).$der;
        $public = openssl_pkey_get_details($ec)['key'];
        $this->assertSame(1, openssl_verify("{$h}.{$c}", $der, $public, OPENSSL_ALGO_SHA256));
    }

    public function test_staff_set_the_keys_see_the_phones_and_send_a_test(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $this->registerBoth();

        $this->actingAs($admin)->get(route('admin.push.index'))->assertOk()->assertSee('Push Notifications')->assertSee('Not set up')->assertSee('iPhone15,2');

        $ec = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($ec, $p8);
        $this->actingAs($admin)->post(route('admin.push.apns'), ['team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'bundle_id' => 'com.naoero.app', 'private_key' => 'nope', 'enabled' => 1])
            ->assertSessionHasErrors('private_key');
        $this->actingAs($admin)->post(route('admin.push.apns'), ['team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'bundle_id' => 'com.naoero.app', 'private_key' => $p8, 'enabled' => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue(app(PushCredentials::class)->enabled('apns'));

        // Saving again without the key keeps it.
        $this->actingAs($admin)->post(route('admin.push.apns'), ['team_id' => 'TEAM123456', 'key_id' => 'KEY1234567', 'bundle_id' => 'com.naoero.app2', 'enabled' => 1])->assertSessionHasNoErrors();
        $this->assertSame(trim($p8), app(PushCredentials::class)->config('apns')['private_key']);
        $this->actingAs($admin)->get(route('admin.push.index'))->assertOk()->assertDontSee('PRIVATE KEY-----M', false)->assertSee('saved; leave empty to keep it');

        $device = (string) DB::table('customer_devices')->where('device_uid', self::IOS_UID)->value('id');
        $this->actingAs($admin)->post(route('admin.push.test', $device))->assertSessionHas('success');
        $this->assertCount(1, $this->pushes);
        $this->assertTrue((bool) DB::table('push_deliveries')->value('is_test'));

        $viewer = User::factory()->create(['status' => 'active']);
        $this->actingAs($viewer)->get(route('admin.push.index'))->assertForbidden();
    }
}
