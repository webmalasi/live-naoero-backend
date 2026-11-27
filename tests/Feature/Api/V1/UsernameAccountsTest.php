<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Usernames: customers choose the login they sign in with (Zenith's Login,
 * unique, fixed); one email can belong to several accounts. Sign-up is the
 * airline's "Create a new profile" form; sign-in takes the username or the
 * email.
 */
class UsernameAccountsTest extends AppApiTestCase
{
    private function newAccount(array $override = []): array
    {
        return [
            'username' => 'ana.citizen', 'password' => 'Passw0rd!', 'title' => 'MS', 'firstName' => 'Ana', 'lastName' => 'Citizen',
            'email' => 'ana@example.com', 'birthDate' => '1990-04-12', 'phone' => '+61 400 000 001', 'phoneCountry' => 'AU',
            'acceptTerms' => true, 'marketingOptIn' => false, 'deviceLabel' => 'Test phone', ...$override,
        ];
    }

    public function test_sign_up_with_a_username_creates_that_login_in_zenith(): void
    {
        $res = $this->api('POST', 'auth/signup', $this->newAccount())->assertCreated()
            ->assertJsonPath('customer.username', 'ana.citizen')
            ->assertJsonPath('customer.email', 'ana@example.com')
            ->assertJsonPath('customer.birthDate', '1990-04-12')
            ->assertJsonPath('customer.marketingOptIn', false)
            ->assertJsonPath('customer.termsAcceptedAt', fn ($v) => $v !== null);

        $sent = $this->zenith->calls('NewCustomer')[0]['Customer'];
        $this->assertSame(['ana.citizen', 'ana@example.com', '1990-04-12T00:00:00', 'Mobile'], [$sent['Login'], $sent['Emails'][0]['Email'], $sent['BirthDate'], $sent['Phones'][0]['PhoneTypeCode']]);
        $c = DB::table('customers')->first();
        $this->assertSame(['ana.citizen', 'ana@example.com'], [$c->zenith_login, $c->email]);
        $this->assertNotNull($res->json('accessToken'));
    }

    public function test_the_username_check_and_its_rules(): void
    {
        $this->api('GET', 'auth/username-available?username=ana.citizen')->assertOk()
            ->assertJsonPath('available', true)->assertJsonPath('valid', true);
        foreach (['ab', 'has space', '.dot-first', 'double..dot', 'end-', 'admin', str_repeat('a', 51), 'émile'] as $bad) {
            $this->api('GET', 'auth/username-available?username='.urlencode($bad))->assertOk()
                ->assertJsonPath('available', false)->assertJsonPath('valid', false);
        }

        $this->api('POST', 'auth/signup', $this->newAccount())->assertCreated();
        // Taken, whatever the case.
        $this->api('GET', 'auth/username-available?username=ANA.Citizen')->assertOk()
            ->assertJsonPath('available', false)->assertJsonPath('valid', true)->assertJsonPath('message', 'This username is taken. Please choose another one.');
        $this->api('GET', 'auth/username-available')->assertStatus(400);

        // Sign-up refuses a bad or taken username before Zenith is asked.
        $this->api('POST', 'auth/signup', $this->newAccount(['username' => 'a b']))->assertStatus(400)->assertJsonPath('error.code', 'invalid_username');
        $this->api('POST', 'auth/signup', $this->newAccount(['username' => 'Ana.Citizen']))->assertStatus(409)
            ->assertJsonPath('error.code', 'username_taken')->assertJsonPath('error.field', 'username');
        $this->assertCount(1, $this->zenith->calls('NewCustomer'));
    }

    public function test_a_username_taken_on_the_airline_site_is_refused_by_zenith_and_remembered(): void
    {
        $this->zenith->addAccount('booker', 'Other1!pw', ['Emails' => [['Email' => 'booker@example.com']]]);
        // We don't know it yet: it looks free...
        $this->api('GET', 'auth/username-available?username=booker')->assertOk()->assertJsonPath('available', true);
        // ...Zenith says otherwise at sign-up.
        $this->api('POST', 'auth/signup', $this->newAccount(['username' => 'booker']))->assertStatus(409)->assertJsonPath('error.code', 'username_taken');
        $this->api('GET', 'auth/username-available?username=Booker')->assertOk()->assertJsonPath('available', false);
        $this->assertSame(0, DB::table('customers')->count());
    }

    public function test_the_new_form_needs_birth_date_and_terms(): void
    {
        $this->api('POST', 'auth/signup', $this->newAccount(['birthDate' => null]))->assertStatus(400);
        $this->api('POST', 'auth/signup', $this->newAccount(['acceptTerms' => false]))->assertStatus(400);
        $this->api('POST', 'auth/signup', $this->newAccount(['birthDate' => '2999-01-01']))->assertStatus(400);
        $this->api('POST', 'auth/signup', $this->newAccount(['password' => 'Ana.Citizen']))->assertStatus(400);
        $this->assertCount(0, $this->zenith->calls('NewCustomer'));
    }

    public function test_several_accounts_can_share_an_email(): void
    {
        $this->api('POST', 'auth/signup', $this->newAccount())->assertCreated();
        $this->api('POST', 'auth/signup', $this->newAccount(['username' => 'ana-work', 'phone' => '+61 400 000 002']))->assertCreated()
            ->assertJsonPath('customer.email', 'ana@example.com');
        $this->assertSame(2, DB::table('customers')->where('email', 'ana@example.com')->count());

        // By username: each account.
        $this->api('POST', 'auth/login', ['username' => 'ANA-WORK', 'password' => 'Passw0rd!'])->assertOk()->assertJsonPath('customer.username', 'ana-work');
        $this->api('POST', 'auth/login', ['username' => 'ana.citizen', 'password' => 'Passw0rd!'])->assertOk()->assertJsonPath('customer.username', 'ana.citizen');
        // By email: which one? The username is needed; Zenith is not asked.
        $before = count($this->zenith->calls('LoadCustomer'));
        $this->api('POST', 'auth/login', ['email' => 'Ana@Example.com', 'password' => 'Passw0rd!'])->assertStatus(409)->assertJsonPath('error.code', 'several_accounts');
        $this->assertCount($before, $this->zenith->calls('LoadCustomer'));
    }

    public function test_sign_in_by_email_finds_the_username(): void
    {
        $this->api('POST', 'auth/signup', $this->newAccount())->assertCreated();
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->assertOk()->assertJsonPath('customer.username', 'ana.citizen');
        $this->assertSame('ana.citizen', collect($this->zenith->calls('LoadCustomer'))->last()['ByLoginAndPassword']['Login']);

        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Wrong1!x'])->assertStatus(401)
            ->assertJsonPath('error.message', 'Incorrect email or password.');
        $this->api('POST', 'auth/login', ['username' => 'ana.citizen', 'password' => 'Wrong1!x'])->assertStatus(401)
            ->assertJsonPath('error.message', 'Incorrect username or password.');
        $this->api('POST', 'auth/login', ['username' => 'nobody.here', 'password' => 'Wrong1!x'])->assertStatus(404)->assertJsonPath('error.code', 'username_not_registered');
        $this->api('POST', 'auth/login', ['password' => 'Passw0rd!'])->assertStatus(400);
    }

    public function test_an_account_made_on_the_airline_site_signs_in_by_username_and_is_added(): void
    {
        $this->zenith->addAccount('rob.eoe', 'Booker1!pw', ['Emails' => [['Email' => 'rob@example.com']]]);
        $this->api('POST', 'auth/login', ['username' => 'rob.eoe', 'password' => 'Booker1!pw'])->assertOk()
            ->assertJsonPath('customer.username', 'rob.eoe')->assertJsonPath('customer.email', 'rob@example.com');
        // Known now: by email too.
        $this->api('POST', 'auth/login', ['email' => 'rob@example.com', 'password' => 'Booker1!pw'])->assertOk();
    }

    public function test_older_apps_still_sign_up_and_in_with_their_email(): void
    {
        $this->api('POST', 'auth/signup', [
            'title' => 'MR', 'firstName' => 'Old', 'lastName' => 'App', 'email' => 'old@example.com', 'phone' => '+61400000003', 'password' => 'Passw0rd!',
        ])->assertCreated()->assertJsonPath('customer.username', 'old@example.com')->assertJsonPath('customer.email', 'old@example.com');
        $this->api('POST', 'auth/login', ['email' => 'old@example.com', 'password' => 'Passw0rd!'])->assertOk();
        $this->api('POST', 'auth/signup', [
            'title' => 'MR', 'firstName' => 'Old', 'lastName' => 'App', 'email' => 'old@example.com', 'phone' => '+61400000003', 'password' => 'Passw0rd!',
        ])->assertStatus(409)->assertJsonPath('error.code', 'email_taken');
    }

    public function test_password_reset_by_email_and_username(): void
    {
        $this->api('POST', 'auth/signup', $this->newAccount())->assertCreated();

        $this->api('POST', 'auth/forgot-password', ['email' => 'ana@example.com', 'username' => 'ana.citizen'])->assertOk();
        $this->assertSame(['EmailAddress' => 'ana@example.com', 'CustomerLogin' => 'ana.citizen'], array_intersect_key(collect($this->zenith->calls('LostCustomerPassword'))->last(), ['EmailAddress' => 1, 'CustomerLogin' => 1]));

        // A username alone: its email is ours to send to.
        $this->api('POST', 'auth/forgot-password', ['username' => 'ana.citizen'])->assertOk();
        $this->assertSame('ana@example.com', collect($this->zenith->calls('LostCustomerPassword'))->last()['EmailAddress']);
        // Unknown username: the same answer, nothing sent.
        $count = count($this->zenith->calls('LostCustomerPassword'));
        $this->api('POST', 'auth/forgot-password', ['username' => 'someone.else'])->assertOk();
        $this->assertCount($count, $this->zenith->calls('LostCustomerPassword'));
        $this->api('POST', 'auth/forgot-password', [])->assertStatus(400);
    }

    public function test_password_reset_asks_for_the_login_when_several_accounts_use_the_email(): void
    {
        $this->api('POST', 'auth/signup', $this->newAccount())->assertCreated();
        $logins = fn () => collect($this->zenith->calls('LostCustomerPassword'))->pluck('CustomerLogin')->all();

        // One account: the email alone, and Zenith is told which login.
        $this->api('POST', 'auth/forgot-password', ['email' => 'ana@example.com'])->assertOk();
        $this->assertSame(['ana.citizen'], $logins());

        // A second account on the same email: the login is asked for, nothing sent yet.
        $this->api('POST', 'auth/signup', $this->newAccount(['username' => 'ana.work']))->assertCreated();
        $this->api('POST', 'auth/forgot-password', ['email' => 'ANA@example.com'])->assertStatus(409)
            ->assertJsonPath('error.code', 'several_accounts')
            ->assertJsonPath('error.message', fn ($m) => str_contains($m, 'Several accounts are linked to this email address'));
        $this->assertSame(['ana.citizen'], $logins());

        // A client that shows the notice itself asks for a 200 instead of the 409.
        $this->api('POST', 'auth/forgot-password', ['email' => 'ana@example.com', 'askForLogin' => true])->assertOk()
            ->assertJson(['ok' => false, 'needsLogin' => true, 'code' => 'several_accounts'])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Several accounts are linked to this email address'));
        $this->assertSame(['ana.citizen'], $logins());

        // The login given: that account only.
        $this->api('POST', 'auth/forgot-password', ['email' => 'ana@example.com', 'username' => 'ana.work'])->assertOk();
        $this->assertSame(['ana.citizen', 'ana.work'], $logins());

        // No login given: one email per account.
        $this->api('POST', 'auth/forgot-password', ['email' => 'ana@example.com', 'allAccounts' => true])->assertOk();
        $this->assertSame(['ana.citizen', 'ana.work', 'ana.citizen', 'ana.work'], $logins());
    }

    public function test_staff_see_and_search_usernames_and_emails(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $this->api('POST', 'auth/signup', $this->newAccount(['marketingOptIn' => true]))->assertCreated();
        $id = (string) DB::table('customers')->value('id');

        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => 'ana.citi']))->assertOk()->assertSee('ana@example.com')->assertSee('ana.citizen');
        $this->actingAs($admin)->get(route('admin.customers.index', ['search' => 'ana@exam']))->assertOk()->assertSee('ana.citizen');
        $this->actingAs($admin)->get(route('admin.customers.show', $id))->assertOk()
            ->assertSee('Username (login)')->assertSee('ana.citizen')->assertSee('Main email')->assertSee('Agreed')->assertSee('Accepted');
        $csv = $this->actingAs($admin)->get(route('admin.customers.index', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Name,Username,Email', $csv);
        $this->assertStringContainsString('ana.citizen,ana@example.com', $csv);
    }
}
