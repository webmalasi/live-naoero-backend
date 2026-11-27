<?php

namespace Tests\Feature\Api\V1;

use App\Services\Support\Phones;
use Illuminate\Support\Facades\DB;

/**
 * Phone numbers in one form everywhere: validated per country, sent to
 * Zenith and stored in E.164 with their country, shown with a flag.
 */
class PhoneNumbersTest extends AppApiTestCase
{
    private const ADULT = 'Traveler_Type_1_Index_0';

    public function test_numbers_are_read_however_they_are_typed(): void
    {
        foreach (['+91 98997 65000', '+919899765000', '919899765000', '0091 9899 765 000', '+91-98997-65000'] as $typed) {
            $this->assertSame('+919899765000', Phones::parse($typed)['e164'] ?? null, $typed);
        }
        $this->assertSame('IN', Phones::parse('+91 9899765000')['country']);
        $this->assertSame(['+61412345678', 'AU'], [Phones::parse('+61 0412 345 678')['e164'], Phones::parse('+61 0412 345 678')['country']], 'the trunk 0 after the code');
        $this->assertSame('+61412345678', Phones::parse('0412 345 678', 'AU')['e164'], 'a local number with the picked country');
        $this->assertSame('CA', Phones::parse('+1 416 555 0199')['country']);
        $this->assertSame('US', Phones::parse('+1 212 555 0123', 'US')['country'], 'shared +1: the picked country');
        $this->assertTrue(Phones::parse('+61 2 9374 4000')['landline']);
        foreach (['12345', '+91 123', 'call me', ''] as $bad) {
            $this->assertNull(Phones::parse($bad), $bad);
        }
        $this->assertSame('🇮🇳 +91 98997 65000', Phones::display('+919899765000'));
        $this->assertSame('not a number', Phones::display('not a number'));
    }

    public function test_the_app_gets_every_country_with_its_flag_and_code(): void
    {
        $list = $this->api('GET', 'reference/phone-codes')->assertOk()->assertJsonPath('default', 'AU')->json('countries');
        // The home markets first, then by name; codes from libphonenumber, names from Zenith.
        $this->assertSame(['AU', 'NR'], array_slice(array_column($list, 'code'), 0, 2));
        $au = $list[0];
        $this->assertSame(['AU', 'Australia', '+61', '🇦🇺'], [$au['code'], $au['name'], $au['dialCode'], $au['flag']]);
        $this->assertNotEmpty($au['example']);
        $this->assertSame('+674', collect($list)->firstWhere('code', 'NR')['dialCode']);
        $this->assertNull(collect($list)->firstWhere('code', 'XXX'), 'not a real country');

        // Zenith down: the standard names, so phone fields still work.
        $this->app['cache']->store(config('naoero.cache_store'))->flush();
        $this->zenith->failures['GetValueCodes'] = 'down';
        $fallback = $this->api('GET', 'reference/phone-codes')->assertOk()->json('countries');
        $this->assertGreaterThan(200, count($fallback));
        $this->assertSame('+91', collect($fallback)->firstWhere('code', 'IN')['dialCode']);
        $this->assertSame('India', collect($fallback)->firstWhere('code', 'IN')['name']);
    }

    public function test_signup_stores_the_mobile_in_e164_with_its_country(): void
    {
        $body = ['title' => 'MR', 'firstName' => 'Ana', 'lastName' => 'Citizen', 'email' => 'ana@example.com', 'password' => 'Passw0rd!', 'deviceLabel' => 'Test phone'];
        $this->api('POST', 'auth/signup', [...$body, 'phone' => '+61 2 9374 4000'])->assertStatus(400)
            ->assertJsonPath('error.message', 'This is a landline number. Enter a mobile number so we can send you text messages.');
        $this->api('POST', 'auth/signup', [...$body, 'phone' => '+91 123'])->assertStatus(400);

        $created = $this->api('POST', 'auth/signup', [...$body, 'phone' => '98997 65000', 'phoneCountry' => 'in'])->assertCreated();
        $created->assertJsonPath('customer.phone', '+919899765000')->assertJsonPath('customer.phoneCountry', 'IN');
        $this->assertSame('+919899765000', $this->zenith->calls('NewCustomer')[0]['Customer']['Phones'][0]['PhoneNumber']);
        $this->assertDatabaseHas('customers', ['zenith_login' => 'ana@example.com', 'phone_e164' => '+919899765000', 'phone_country' => 'IN']);
        $this->assertDatabaseHas('customer_phones', ['e164' => '+919899765000', 'country_code' => 'IN']);

        // A shared code keeps the country the customer picked.
        $token = $created->json('accessToken');
        $this->api('PATCH', 'me', ['phone' => '+1 212 555 0123', 'phoneCountry' => 'US'], $token)->assertOk()
            ->assertJsonPath('customer.phone', '+12125550123')->assertJsonPath('customer.phoneCountry', 'US');
    }

    public function test_bookings_send_e164_to_zenith_and_keep_the_contact_mobile(): void
    {
        $token = $this->signUp()['accessToken'];
        $draft = $this->api('POST', 'bookings/prepare', ['offerRef' => 'OFFER-1', 'itineraryRef' => 'IT_0'], $token)->assertOk()->json('draftId');
        $doc = ['docType' => 'PP', 'docNumber' => 'PA1234567', 'nationality' => 'AU', 'docIssueCountry' => 'AU', 'docExpiry' => '2031-01-01'];
        $adult = ['title' => 'MR', 'firstName' => 'John', 'lastName' => 'Citizen', 'dateOfBirth' => '1985-03-14', ...$doc];
        $others = [
            ['ref' => 'Traveler_Type_2_Index_1', 'values' => ['firstName' => 'Amy', 'lastName' => 'Citizen', 'gender' => 'F', 'dateOfBirth' => '2018-05-05', ...$doc, 'docNumber' => 'PB1234567']],
            ['ref' => 'Traveler_Type_3_Index_2', 'values' => ['firstName' => 'Ben', 'lastName' => 'Citizen', 'gender' => 'M', 'dateOfBirth' => '2026-01-10', ...$doc, 'docNumber' => 'PC1234567']],
        ];

        $landline = $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => [['ref' => self::ADULT, 'values' => [...$adult, 'mobilePhone' => '+61 2 9374 4000']], ...$others]], $token);
        $landline->assertStatus(422);
        $this->assertContains('mobilePhone', array_column($landline->json('error.problems'), 'field'));

        $this->api('POST', 'bookings', ['draftId' => $draft, 'passengers' => [['ref' => self::ADULT, 'values' => [...$adult, 'mobilePhone' => '98997 65000', 'mobilePhoneCountry' => 'IN']], ...$others],
            'saveTravellers' => [self::ADULT]], $token)->assertCreated();
        $sent = collect($this->zenith->calls('CreateBooking')[0]['SpecialServices']);
        $this->assertSame('+919899765000', $sent->firstWhere('Code', 'CTCM')['Text']);
        // No home phone given: the mobile again, also in E.164 (with the mobile's country).
        $this->assertSame('+919899765000', $sent->firstWhere('Code', 'CTCH')['Text']);
        // The booking keeps Zenith's contact mobile (the fixture's), the traveller ours.
        $this->assertDatabaseHas('bookings', ['pnr' => '003PUX', 'contact_phone' => '+61400000001', 'contact_phone_country' => 'AU']);
        $this->assertDatabaseHas('saved_travellers', ['first_name' => 'John', 'phone' => '+919899765000', 'phone_country' => 'IN']);
    }

    public function test_old_numbers_are_converted_by_the_backfill(): void
    {
        $this->signUp();
        $id = DB::table('customers')->value('id');
        DB::table('customers')->where('id', $id)->update(['phone_e164' => null, 'phone_country' => null, 'emergency_contact_phone' => '+61 400 111 222']);
        DB::table('customer_phones')->update(['e164' => null, 'country_code' => null]);

        $this->artisan('phones:backfill')->assertSuccessful();
        $this->assertDatabaseHas('customers', ['id' => $id, 'phone_e164' => '+61400000001', 'phone_country' => 'AU', 'emergency_contact_phone' => '+61400111222']);
        $this->assertDatabaseHas('customer_phones', ['customer_id' => $id, 'e164' => '+61400000001', 'country_code' => 'AU']);
    }
}
