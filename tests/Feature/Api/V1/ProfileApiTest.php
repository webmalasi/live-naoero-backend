<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\DB;

class ProfileApiTest extends AppApiTestCase
{
    public function test_profile_is_refreshed_from_zenith(): void
    {
        $token = $this->signUp()['accessToken'];
        $this->api('GET', 'me', token: $token)->assertOk()
            ->assertJsonPath('stale', false)->assertJsonPath('customer.fullName', 'Ana Citizen');

        $this->zenith->failures['LoadCustomer'] = 'down';
        $this->api('GET', 'me', token: $token)->assertOk()->assertJsonPath('stale', true)->assertJsonPath('customer.email', 'ana@example.com');
    }

    public function test_profile_updates_go_to_zenith_with_the_login_unchanged(): void
    {
        $token = $this->signUp()['accessToken'];
        $response = $this->api('PATCH', 'me', [
            'phone' => '+61 400 999 888',
            'birthDate' => '1990-02-03',
            'address' => ['line1' => '1 Main St', 'city' => 'Brisbane', 'country' => 'au'],
            'passport' => ['number' => 'pa123456', 'issuingCountry' => 'AU', 'nationality' => 'AU', 'expiryDate' => '2031-01-01'],
            'emergencyContact' => ['name' => 'Bo', 'phone' => '+61 400 111 222'],
        ], $token)->assertOk();

        $response->assertJsonPath('customer.phone', '+61400999888')
            ->assertJsonPath('customer.birthDate', '1990-02-03')
            ->assertJsonPath('customer.address.country', 'AU')
            ->assertJsonPath('customer.passport.number', 'PA123456')
            ->assertJsonPath('customer.passport.documentType', 'PP')
            ->assertJsonPath('customer.emergencyContact', ['name' => 'Bo', 'phone' => '+61400111222', 'phoneCountry' => 'AU']);

        $sent = $this->zenith->calls('UpdateCustomer')[0]['Customer'];
        $this->assertSame('ana@example.com', $sent['Login']);
        $this->assertSame('Ana', $sent['Documents'][0]['Firstname']);
        $this->assertSame('1990-02-03T00:00:00', $sent['Documents'][0]['DateOfBirth']);
    }

    /**
     * Every saved field comes back on the next reads, which refresh from
     * Zenith each time, including the street number and state that the
     * airline's Zenith does not keep.
     */
    public function test_the_whole_profile_survives_reloads_from_zenith(): void
    {
        $token = $this->signUp()['accessToken'];
        $address = ['streetNumber' => '12', 'line1' => 'Main Street', 'line2' => 'Unit 4', 'city' => 'Brisbane', 'state' => 'QLD', 'postcode' => '4000', 'country' => 'AU'];
        $passport = ['number' => 'PA1234567', 'documentType' => 'PP', 'issuingCountry' => 'AU', 'nationality' => 'AU', 'issueDate' => '2020-01-10', 'expiryDate' => '2030-01-09', 'gender' => 'M'];
        $this->api('PATCH', 'me', ['birthDate' => '1990-05-17', 'address' => $address, 'passport' => $passport, 'emergencyContact' => ['name' => 'Jane', 'phone' => '+61 498 765 432']], $token)->assertOk()
            ->assertJsonPath('customer.address', $address);
        $this->assertSame('12', $this->zenith->calls('UpdateCustomer')[0]['Customer']['Addresses'][0]['StreetNumber']);

        foreach ([1, 2] as $read) {
            $this->api('GET', 'me', token: $token)->assertOk()->assertJsonPath('stale', false)
                ->assertJsonPath('customer.birthDate', '1990-05-17')
                ->assertJsonPath('customer.address', $address)
                ->assertJsonPath('customer.passport', $passport)
                ->assertJsonPath('customer.emergencyContact.name', 'Jane');
        }

        // A new address without them clears our copy too.
        $this->api('PATCH', 'me', ['address' => ['line1' => '5 Beach Road', 'city' => 'Yaren', 'country' => 'NR']], $token)->assertOk()
            ->assertJsonPath('customer.address.streetNumber', null)->assertJsonPath('customer.address.state', null)
            ->assertJsonPath('customer.address.line1', '5 Beach Road')->assertJsonPath('customer.address.line2', null);
        // Other fields stay as they were.
        $this->api('GET', 'me', token: $token)->assertJsonPath('customer.birthDate', '1990-05-17')->assertJsonPath('customer.passport.number', 'PA1234567');
    }

    public function test_a_rejected_update_lists_every_problem_and_is_logged(): void
    {
        $token = $this->signUp()['accessToken'];
        $this->api('PATCH', 'me', ['address' => ['line1' => '', 'country' => 'Australia'], 'passport' => ['number' => 'P 1']], $token)->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_request')
            ->assertJsonFragment(['field' => 'address.city'])
            ->assertJsonFragment(['field' => 'address.country', 'message' => 'Use a 2-letter country code.'])
            ->assertJsonFragment(['field' => 'passport.expiryDate']);
        $log = DB::table('server_logs')->where('source', 'validation')->first();
        $this->assertStringContainsString('PATCH api/v1/me rejected', $log->message);
        $detail = json_decode($log->detail, true);
        $this->assertArrayHasKey('address.city', $detail['fields']);
        $this->assertSame(['address', 'passport'], $detail['sent']);
        // The values typed are never logged.
        $this->assertStringNotContainsString('Australia', $log->detail);
    }

    public function test_profile_updates_are_validated(): void
    {
        $token = $this->signUp()['accessToken'];
        $this->api('PATCH', 'me', ['nickname' => 'x'], $token)->assertStatus(400)->assertJsonPath('error.message', 'Unknown field nickname.');
        $this->api('PATCH', 'me', ['birthDate' => '2099-01-01'], $token)->assertStatus(400)->assertJsonPath('error.message', 'Birth date cannot be in the future.');
        $this->api('PATCH', 'me', ['passport' => ['number' => 'PA123456', 'issuingCountry' => 'AU', 'nationality' => 'AU', 'issueDate' => '2030-01-01', 'expiryDate' => '2029-01-01']], $token)
            ->assertStatus(400)->assertJsonPath('error.message', 'The expiry date must be after the issue date.');
        $this->api('PATCH', 'me', ['address' => ['city' => 'Brisbane', 'country' => 'AU']], $token)->assertStatus(400);
        $this->assertCount(0, $this->zenith->calls('UpdateCustomer'));
    }

    public function test_profile_photo_upload_download_and_removal(): void
    {
        $token = $this->signUp()['accessToken'];
        $png = "\x89PNG\r\n\x1A\n".random_bytes(64);

        $put = $this->call('PUT', '/api/v1/me/avatar', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}", 'CONTENT_TYPE' => 'image/png'], $png);
        $put->assertOk()->assertJsonPath('ok', true);
        $this->api('GET', 'me', token: $token)->assertJsonPath('customer.avatarUpdatedAt', $put->json('avatarUpdatedAt'));

        $get = $this->call('GET', '/api/v1/me/avatar', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}"]);
        $get->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($png, $get->getContent());
        $this->call('GET', '/api/v1/me/avatar', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}", 'HTTP_IF_NONE_MATCH' => $get->headers->get('ETag')])->assertStatus(304);

        $this->call('PUT', '/api/v1/me/avatar', [], [], [], ['HTTP_AUTHORIZATION' => "Bearer {$token}"], 'GIF89a not allowed')
            ->assertStatus(400)->assertJsonPath('error.message', 'The photo must be a JPEG, PNG or WebP image.');

        $this->api('DELETE', 'me/avatar', token: $token)->assertOk()->assertJsonPath('avatarUpdatedAt', null);
        $this->api('GET', 'me/avatar', token: $token)->assertNotFound();
    }

    public function test_protected_endpoints_need_a_token(): void
    {
        $this->api('GET', 'me')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');
        $this->api('GET', 'bookings', token: 'not-a-jwt')->assertStatus(401);
    }
}
