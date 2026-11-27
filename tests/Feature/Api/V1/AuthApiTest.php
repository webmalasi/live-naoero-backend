<?php

namespace Tests\Feature\Api\V1;

use App\Services\Auth\AccessToken;
use Illuminate\Support\Facades\DB;

class AuthApiTest extends AppApiTestCase
{
    public function test_auth_config_offers_titles_and_password_rules(): void
    {
        $this->api('GET', 'auth/config')->assertOk()
            ->assertJsonPath('titles', [['code' => 'MR', 'label' => 'Mr'], ['code' => 'MRS', 'label' => 'Mrs'], ['code' => 'MS', 'label' => 'Ms'], ['code' => 'MISS', 'label' => 'Miss']])
            ->assertJsonPath('passwordPolicy.minLength', 8)
            ->assertJsonPath('accountDeletionAvailable', true)
            ->assertJsonPath('accountDeletionReasons.0.code', 'not_flying');
        $this->api('GET', 'auth/password-policy')->assertOk()->assertJsonPath('maxLength', 64);
    }

    public function test_signup_creates_the_zenith_customer_and_signs_in(): void
    {
        $session = $this->signUp('Ana@Example.com');
        $this->assertSame('ana@example.com', $session['customer']['email']);
        $this->assertSame(900, $session['accessTokenExpiresIn']);
        $this->assertNotEmpty($session['refreshToken']);

        $sent = $this->zenith->calls('NewCustomer')[0];
        $this->assertSame('MR', $sent['Customer']['CivilityCode']);
        $this->assertSame('+61400000001', $sent['Customer']['Phones'][0]['PhoneNumber']);
        $this->assertSame('AUD', $sent['Customer']['Currency']);
    }

    public function test_signup_checks_the_password_and_title(): void
    {
        $base = ['title' => 'MR', 'firstName' => 'A', 'lastName' => 'B', 'email' => 'a@example.com', 'phone' => '+61400000001'];
        $this->api('POST', 'auth/signup', [...$base, 'password' => 'short'])->assertStatus(400)
            ->assertJsonPath('error.message', 'Your password needs at least 8 characters, an uppercase letter, a number and a symbol.');
        $this->api('POST', 'auth/signup', [...$base, 'title' => 'DR', 'password' => 'Passw0rd!'])->assertStatus(400)
            ->assertJsonPath('error.message', 'Choose a valid title.');
        $this->api('POST', 'auth/signup', [...$base, 'phone' => '12', 'password' => 'Passw0rd!'])->assertStatus(400)
            ->assertJsonPath('error.message', 'Enter a valid phone number with its country code, for example +61 412 345 678.');
        $this->assertCount(0, $this->zenith->calls('NewCustomer'));
    }

    public function test_signup_with_a_taken_email_is_refused(): void
    {
        $this->zenith->addAccount('ana@example.com', 'Passw0rd!');
        $this->api('POST', 'auth/signup', [
            'title' => 'MR', 'firstName' => 'A', 'lastName' => 'B', 'email' => 'ana@example.com', 'phone' => '+61400000001', 'password' => 'Passw0rd!',
        ])->assertStatus(409)->assertJsonPath('error.code', 'email_taken');
    }

    public function test_an_account_made_on_the_booking_site_logs_in_and_is_added_here(): void
    {
        // Created directly in Zenith (the airline's booking site), never through the app or website.
        $made = $this->zenith->addAccount('Booker@Example.com', 'Passw0rd!');
        $this->assertSame(0, DB::table('customers')->count());

        $this->api('POST', 'auth/login', ['email' => 'booker@example.com', 'password' => 'Passw0rd!'])->assertOk()
            ->assertJsonPath('customer.email', 'booker@example.com')->assertJsonPath('customer.username', 'Booker@Example.com')
            ->assertJsonPath('customer.firstName', 'Ana');

        $customer = DB::table('customers')->first();
        $this->assertSame($made['Ref'], $customer->zenith_customer_ref);
        $this->assertSame('first login of an account created in Zenith: added here',
            DB::table('auth_events')->where('event', 'login')->where('success', true)->value('detail'));

        // From then on it is an ordinary account: same row, no second copy.
        $this->api('POST', 'auth/login', ['email' => 'BOOKER@example.com', 'password' => 'Passw0rd!'])->assertOk();
        $this->assertSame(1, DB::table('customers')->count());
        $this->api('POST', 'auth/signup', [
            'title' => 'MR', 'firstName' => 'A', 'lastName' => 'B', 'email' => 'booker@example.com', 'phone' => '+61400000001', 'password' => 'Passw0rd!',
        ])->assertStatus(409)->assertJsonPath('error.code', 'email_taken');
    }

    public function test_an_unknown_email_that_zenith_refuses_is_not_registered(): void
    {
        $this->zenith->addAccount('booker@example.com', 'Passw0rd!');
        // A wrong password on a booking-site account, and an email nobody has: the same answer.
        foreach (['booker@example.com' => 'Wrong1!x', 'nobody@example.com' => 'Passw0rd!'] as $email => $password) {
            $this->api('POST', 'auth/login', ['email' => $email, 'password' => $password])
                ->assertStatus(404)->assertJsonPath('error.code', 'email_not_registered')
                ->assertJsonPath('error.message', "We couldn't sign you in with this email and password. If you already have a Naoero Airlines account, check your password or reset it. Otherwise, please sign up.");
        }
        $this->assertCount(2, $this->zenith->calls('LoadCustomer'));
        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(2, DB::table('auth_events')->where('detail', 'email not registered here; Zenith refused it too')->count());
    }

    public function test_an_unknown_email_gets_fewer_tries_than_zenith_allows(): void
    {
        $this->zenith->addAccount('booker@example.com', 'Passw0rd!');
        foreach (range(1, 3) as $try) {
            $this->api('POST', 'auth/login', ['email' => 'booker@example.com', 'password' => 'Wrong1!x'])->assertStatus(404);
        }
        // Zenith locks an account after about 4 wrong passwords: the 4th try never reaches it.
        $this->api('POST', 'auth/login', ['email' => 'booker@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(429)->assertJsonPath('error.code', 'too_many_attempts');
        $this->assertCount(3, $this->zenith->calls('LoadCustomer'));
    }

    public function test_agency_logins_from_zenith_are_not_added(): void
    {
        $this->zenith->addAccount('agent@example.com', 'Passw0rd!', ['TypeCode' => 'TravelAgency']);
        $this->api('POST', 'auth/login', ['email' => 'agent@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(404)->assertJsonPath('error.code', 'email_not_registered');
        $this->assertSame(0, DB::table('customers')->count());
        $this->assertSame(0, DB::table('refresh_tokens')->count());
    }

    public function test_login_is_decided_by_zenith(): void
    {
        $this->signUp();
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Wrong1!x'])
            ->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');

        $login = $this->api('POST', 'auth/login', ['email' => 'ANA@example.com', 'password' => 'Passw0rd!'])->assertOk();
        $login->assertJsonPath('customer.email', 'ana@example.com')->assertJsonPath('otherActiveSessions', 1)
            ->assertJsonPath('suggestSignOutOtherDevices', false);
        $this->assertSame('Passw0rd!', $this->zenith->calls('LoadCustomer')[1]['ByLoginAndPassword']['Password']);
    }

    public function test_no_password_is_ever_stored(): void
    {
        $this->signUp('ana@example.com', 'Sup3r$ecretValue');
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Sup3r$ecretValue'])->assertOk();
        foreach (['customers', 'auth_events', 'refresh_tokens', 'server_logs'] as $table) {
            $this->assertStringNotContainsString('Sup3r$ecretValue', DB::table($table)->get()->toJson(), $table);
        }
    }

    public function test_repeated_failures_are_limited_before_zenith(): void
    {
        $this->signUp();
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Wrong1!x'])->assertStatus(401);
        }
        $calls = count($this->zenith->calls('LoadCustomer'));
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(429)->assertJsonPath('error.code', 'too_many_attempts')->assertHeader('Retry-After', '900');
        $this->assertCount($calls, $this->zenith->calls('LoadCustomer'));
    }

    public function test_zenith_lockout_is_reported_as_too_many_attempts(): void
    {
        $this->signUp();
        $this->zenith->failures['LoadCustomer'] = FakeZenith::error('', 'Customer attempt limit reached');
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])
            ->assertStatus(429)->assertJsonPath('error.code', 'too_many_attempts');
    }

    public function test_refresh_rotates_and_a_reused_token_ends_the_session(): void
    {
        $session = $this->signUp();
        $first = $session['refreshToken'];
        $next = $this->api('POST', 'auth/refresh', ['refreshToken' => $first])->assertOk()->json();
        $this->assertNotSame($first, $next['refreshToken']);
        $this->assertSame($session['sessionId'], $next['sessionId']);

        // The old token again: stolen. The whole session ends, new token included.
        $this->api('POST', 'auth/refresh', ['refreshToken' => $first])->assertStatus(401)->assertJsonPath('error.code', 'session_expired');
        $this->api('POST', 'auth/refresh', ['refreshToken' => $next['refreshToken']])->assertStatus(401);
        $this->api('GET', 'me', token: $next['accessToken'])->assertStatus(401)->assertJsonPath('error.code', 'session_expired');
        $this->assertDatabaseHas('auth_events', ['event' => 'token_reuse']);
    }

    public function test_sessions_end_after_thirty_days(): void
    {
        $session = $this->signUp();
        $token = $session['refreshToken'];
        foreach ([6, 12, 18, 24] as $day) {
            $this->travelTo(now()->addDays(6));
            $token = $this->api('POST', 'auth/refresh', ['refreshToken' => $token])->assertOk()->json('refreshToken');
        }
        $this->travelTo(now()->addDays(6)->addMinute());
        $this->api('POST', 'auth/refresh', ['refreshToken' => $token])->assertStatus(401);
    }

    public function test_logout_ends_this_device_or_all_devices(): void
    {
        $a = $this->signUp();
        $b = $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->json();

        $this->api('POST', 'auth/logout', ['refreshToken' => $a['refreshToken']])->assertOk()->assertJson(['ok' => true]);
        $this->api('GET', 'me', token: $a['accessToken'])->assertStatus(401);
        $this->api('GET', 'me', token: $b['accessToken'])->assertOk();

        $this->api('POST', 'auth/logout', ['allDevices' => true], $b['accessToken'])->assertOk()->assertJsonPath('sessionsEnded', 1);
        $this->api('GET', 'me', token: $b['accessToken'])->assertStatus(401);
        $this->api('POST', 'auth/logout', [])->assertStatus(400)->assertJsonPath('error.message', 'refreshToken is required.');
    }

    public function test_change_password_goes_through_zenith_and_signs_out_other_devices(): void
    {
        $a = $this->signUp();
        $b = $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->json();

        $this->api('POST', 'auth/change-password', ['currentPassword' => 'Nope1234!', 'newPassword' => 'N3w-Passw0rd'], $a['accessToken'])
            ->assertStatus(400)->assertJsonPath('error.code', 'wrong_current_password');
        $this->api('POST', 'auth/change-password', ['currentPassword' => 'Passw0rd!', 'newPassword' => 'N3w-Passw0rd'], $a['accessToken'])
            ->assertOk()->assertJsonPath('otherSessionsSignedOut', 1);
        $this->api('GET', 'me', token: $b['accessToken'])->assertStatus(401);
        $this->api('GET', 'me', token: $a['accessToken'])->assertOk();
    }

    public function test_forgot_password_answers_the_same_for_everyone(): void
    {
        $known = $this->api('POST', 'auth/forgot-password', ['email' => 'ana@example.com'])->assertOk()->json('message');
        $unknown = $this->api('POST', 'auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');
        $this->assertSame($known, $unknown);
        $this->assertCount(2, $this->zenith->calls('LostCustomerPassword'));
    }

    public function test_tokens_from_the_previous_server_stay_valid(): void
    {
        // Same issuer, audience and HS256 secret as the Next.js API.
        $token = AccessToken::sign('6f1c1c5e-0000-4000-8000-000000000001', '6f1c1c5e-0000-4000-8000-000000000002');
        [$header, $payload] = explode('.', $token);
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], json_decode(base64_decode($header), true));
        $this->assertSame('naoero-proxy', $claims['iss']);
        $this->assertSame('naoero-app', $claims['aud']);
        $this->assertSame(['customerId' => '6f1c1c5e-0000-4000-8000-000000000001', 'sessionId' => '6f1c1c5e-0000-4000-8000-000000000002'], AccessToken::verify($token));
        $this->api('GET', 'me', token: substr($token, 0, -2).'xx')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
    }
}
