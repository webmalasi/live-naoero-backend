<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usernames. A Zenith customer signs in with their Login, which is unique
 * and can't change; until now the app always made it the customer's email.
 * Customers now choose a username, and one email can belong to several
 * accounts (Zenith allows it). So:
 *
 * - customers.zenith_login stays the unique login (the username);
 * - customers.email is the customer's main email (the first one Zenith
 *   holds), not unique, indexed for signing in by email;
 * - the GDPR choices made at sign-up: terms accepted (when) and marketing
 *   consent (and when it was given or withdrawn);
 * - zenith_taken_logins: usernames Zenith said are taken that we have no
 *   account for (created on the airline's own site), so the "is this
 *   username free?" check knows them next time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('email')->nullable();
            $table->timestampTz('terms_accepted_at', 6)->nullable();
            $table->boolean('marketing_opt_in')->nullable();
            $table->timestampTz('marketing_opt_in_at', 6)->nullable();
        });
        DB::statement('create index customers_environment_email on customers (zenith_environment, lower(email))');

        Schema::create('zenith_taken_logins', function (Blueprint $table) {
            $table->id();
            $table->text('zenith_environment');
            $table->text('login');
            // Where we learned it: signup (NewCustomer said so).
            $table->string('source', 20)->default('signup');
            $table->timestampTz('created_at', 6)->useCurrent();
        });
        DB::statement('create unique index zenith_taken_logins_unique on zenith_taken_logins (zenith_environment, lower(login))');

        // Every account so far: its main email is Zenith's first email, else its login when that is an email.
        $first = DB::table('customer_emails')->where('position', 0)->pluck('email', 'customer_id');
        foreach (DB::table('customers')->get(['id', 'zenith_login']) as $c) {
            $email = $first[$c->id] ?? (filter_var($c->zenith_login, FILTER_VALIDATE_EMAIL) ? $c->zenith_login : null);
            DB::table('customers')->where('id', $c->id)->update(['email' => $email === null ? null : strtolower(trim($email))]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zenith_taken_logins');
        DB::statement('drop index if exists customers_environment_email');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['email', 'terms_accepted_at', 'marketing_opt_in', 'marketing_opt_in_at']);
        });
    }
};
