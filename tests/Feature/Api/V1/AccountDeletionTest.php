<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Account deletion: the customer asks (reason and password, on the website
 * or in the app), staff disable the account or decline. Nothing is ever
 * deleted, here or in Zenith; a disabled account can't sign in anywhere
 * until staff re-enable it.
 */
class AccountDeletionTest extends AppApiTestCase
{
    private string $token;

    private string $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = $this->signUp()['accessToken'];
        $this->customerId = (string) DB::table('customers')->value('id');
    }

    private function staff(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');

        return $admin;
    }

    private function ask(array $body, ?string $token = null)
    {
        return $this->api('POST', 'me/delete-request', $body, $token ?? $this->token, ['X-Client-Platform' => 'website']);
    }

    public function test_a_customer_asks_with_a_reason_and_their_password(): void
    {
        $this->api('GET', 'me/delete-request', token: $this->token)->assertOk()
            ->assertJsonPath('request', null)
            ->assertJsonPath('reasons.0', ['code' => 'not_flying', 'label' => 'I no longer fly with Naoero Airlines', 'needsDetails' => false])
            ->assertJsonPath('reasons.6.code', 'other');

        // Wrong password, unknown reason, "other" without words: refused, nothing saved.
        $this->ask(['reasonCode' => 'privacy', 'password' => 'Wrong-pass1'])->assertStatus(400)->assertJsonPath('error.code', 'wrong_current_password');
        $this->ask(['reasonCode' => 'bored', 'password' => 'Passw0rd!'])->assertStatus(400);
        $this->ask(['reasonCode' => 'other', 'password' => 'Passw0rd!'])->assertStatus(400);
        $this->assertSame(0, DB::table('account_deletion_requests')->count());

        $this->ask(['reasonCode' => 'privacy', 'reasonText' => '  Please remove me  ', 'password' => 'Passw0rd!'])->assertCreated()
            ->assertJsonPath('alreadyRequested', false)
            ->assertJsonPath('request.status', 'pending')
            ->assertJsonPath('request.reason', 'Privacy concerns about my data')
            ->assertJsonPath('request.details', 'Please remove me');
        $r = DB::table('account_deletion_requests')->first();
        $this->assertSame(['privacy', 'website', 'pending'], [$r->reason_code, $r->platform, $r->status]);

        // Still active: signed in, can use the account, sees the request.
        $this->assertSame('active', DB::table('customers')->value('status'));
        $this->api('GET', 'me', token: $this->token)->assertOk()->assertJsonPath('customer.status', 'active')->assertJsonPath('customer.deletionRequestedAt', fn ($v) => $v !== null);
        $this->api('GET', 'me/delete-request', token: $this->token)->assertOk()->assertJsonPath('request.status', 'pending');
        $this->api('GET', 'me/notifications', token: $this->token)->assertOk()
            ->assertJsonPath('notifications.0.type', 'account_deletion_requested')->assertJsonPath('notifications.0.category', 'account');

        // Asking again: the same request.
        $this->ask(['reasonCode' => 'not_flying', 'password' => 'Passw0rd!'])->assertOk()->assertJsonPath('alreadyRequested', true);
        $this->assertSame(1, DB::table('account_deletion_requests')->count());

        // Never sent to Zenith; nothing deleted.
        $this->assertCount(0, $this->zenith->calls('SendCustomerRemovalRequest'));
    }

    public function test_an_older_app_sending_only_its_own_words_is_understood(): void
    {
        $this->api('POST', 'me/delete-request', ['reason' => 'Moving overseas', 'password' => 'Passw0rd!'], $this->token, ['X-Client-Platform' => 'app'])->assertCreated()
            ->assertJsonPath('request.reasonCode', 'other')->assertJsonPath('request.details', 'Moving overseas');
        $this->assertSame('app', DB::table('account_deletion_requests')->value('platform'));
    }

    public function test_the_customer_can_withdraw_a_waiting_request(): void
    {
        $this->api('DELETE', 'me/delete-request', token: $this->token)->assertNotFound();
        $this->ask(['reasonCode' => 'hard_to_use', 'password' => 'Passw0rd!'])->assertCreated();
        $this->api('DELETE', 'me/delete-request', token: $this->token)->assertOk()->assertJsonPath('request.status', 'withdrawn');
        $this->api('GET', 'me', token: $this->token)->assertOk()->assertJsonPath('customer.deletionRequestedAt', null);

        // A new request can follow; the old one stays in the history.
        $this->ask(['reasonCode' => 'not_flying', 'password' => 'Passw0rd!'])->assertCreated();
        $this->assertSame(['withdrawn', 'pending'], DB::table('account_deletion_requests')->orderBy('requested_at')->orderBy('status', 'desc')->pluck('status')->all());
    }

    public function test_staff_disable_the_account_and_it_can_no_longer_sign_in(): void
    {
        $admin = $this->staff();
        $this->ask(['reasonCode' => 'privacy', 'password' => 'Passw0rd!'])->assertCreated();
        $request = (string) DB::table('account_deletion_requests')->value('id');
        $bookingsBefore = DB::table('bookings')->count();

        $this->actingAs($admin)->get(route('admin.customers.index', ['status' => 'removal_requested']))->assertOk()->assertSee('ana@example.com')->assertSee('Deletion requested');
        $this->actingAs($admin)->get(route('admin.customers.show', $this->customerId))->assertOk()
            ->assertSee('This customer asked for their account to be deleted')->assertSee('Privacy concerns about my data')->assertSee('on the website');

        $this->actingAs($admin)->post(route('admin.customers.deletion.complete', [$this->customerId, $request]), ['note' => 'Checked with the customer'])
            ->assertSessionHas('success');

        $c = DB::table('customers')->where('id', $this->customerId)->first();
        $this->assertSame('disabled', $c->status);
        $this->assertSame($admin->id, (int) $c->disabled_by);
        $this->assertStringContainsString('Privacy concerns about my data', $c->disabled_reason);
        $this->assertSame('completed', DB::table('account_deletion_requests')->value('status'));
        $this->assertSame(0, DB::table('refresh_tokens')->where('customer_id', $this->customerId)->whereNull('revoked_at')->count(), 'signed out everywhere');
        $this->assertSame($bookingsBefore, DB::table('bookings')->count());
        $this->assertSame(1, DB::table('customers')->count(), 'the account and its data stay');

        // Can't use the old session or sign in again, without asking Zenith.
        $this->api('GET', 'me', token: $this->token)->assertForbidden()->assertJsonPath('error.code', 'account_disabled');
        $before = count($this->zenith->calls('LoadCustomer'));
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->assertForbidden()->assertJsonPath('error.code', 'account_disabled');
        $this->assertCount($before, $this->zenith->calls('LoadCustomer'));

        $this->actingAs($admin)->get(route('admin.customers.show', $this->customerId))->assertOk()
            ->assertSee('This account is disabled')->assertSee('Re-enable account')->assertSee('Account disabled');
        // Handled: not again.
        $this->actingAs($admin)->post(route('admin.customers.deletion.complete', [$this->customerId, $request]))->assertSessionHas('error');

        // Staff re-enable it: the same password works again.
        $this->actingAs($admin)->patch(route('admin.customers.toggle-status', $this->customerId), ['reason' => 'Customer changed their mind'])->assertSessionHas('success');
        $this->assertSame('active', DB::table('customers')->value('status'));
        $this->assertNull(DB::table('customers')->value('disabled_at'));
        $this->api('POST', 'auth/login', ['email' => 'ana@example.com', 'password' => 'Passw0rd!'])->assertOk();
        $this->assertCount(0, $this->zenith->calls('SendCustomerRemovalRequest'));
    }

    public function test_staff_decline_and_the_customer_is_told_why(): void
    {
        $admin = $this->staff();
        $this->ask(['reasonCode' => 'other', 'reasonText' => 'Too many emails', 'password' => 'Passw0rd!'])->assertCreated();
        $request = (string) DB::table('account_deletion_requests')->value('id');

        $this->travel(1)->minutes();
        $this->actingAs($admin)->post(route('admin.customers.deletion.decline', [$this->customerId, $request]), [])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post(route('admin.customers.deletion.decline', [$this->customerId, $request]), ['note' => 'You have a trip on 20 Nov; please ask again after it.'])
            ->assertSessionHas('success');

        $this->assertSame('active', DB::table('customers')->value('status'));
        $this->api('GET', 'me/delete-request', token: $this->token)->assertOk()
            ->assertJsonPath('request.status', 'declined')->assertJsonPath('request.note', 'You have a trip on 20 Nov; please ask again after it.');
        $this->api('GET', 'me/notifications', token: $this->token)->assertOk()
            ->assertJsonPath('notifications.0.type', 'account_deletion_declined')
            ->assertJsonPath('notifications.0.body', "We haven't disabled your account. You have a trip on 20 Nov; please ask again after it.");
    }

    public function test_disabling_by_hand_completes_a_waiting_request(): void
    {
        $admin = $this->staff();
        $this->ask(['reasonCode' => 'not_flying', 'password' => 'Passw0rd!'])->assertCreated();
        $this->actingAs($admin)->patch(route('admin.customers.toggle-status', $this->customerId), ['reason' => 'As the customer asked'])->assertSessionHas('success');
        $this->assertSame('disabled', DB::table('customers')->value('status'));
        $r = DB::table('account_deletion_requests')->first();
        $this->assertSame(['completed', 'As the customer asked'], [$r->status, $r->staff_note]);

        // Staff without customers.manage can't act.
        $viewer = User::factory()->create(['status' => 'active']);
        $this->actingAs($viewer)->post(route('admin.customers.deletion.complete', [$this->customerId, $r->id]))->assertForbidden();
    }
}
