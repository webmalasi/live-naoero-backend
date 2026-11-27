<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bookings made elsewhere (website account, travel agent, airport): where
 * each booking came from, how the airline verifies access to it, and when
 * the customer's airline account was last searched for bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // app = booked through our API (app or website); account = found on
            // the customer's airline account; reference = added with booking
            // reference + passenger surname.
            $table->string('source', 20)->default('app');
            // Name-linked bookings are verified with a passenger surname, not
            // the customer reference (Zenith's rule for bookings found that way).
            $table->string('verify_surname', 60)->nullable();
            $table->timestamp('linked_at', 3)->nullable();
            // The customer removed it from their list (added bookings only).
            $table->timestamp('hidden_at', 3)->nullable();
            $table->index(['customer_id', 'hidden_at']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('bookings_synced_at', 3)->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table bookings add constraint bookings_source_check check (source in ('app', 'account', 'reference'))");
            DB::statement("comment on column bookings.source is 'Where the booking came from: app (booked through our API, app or website), account (found on the customer''s airline account), reference (added with booking reference and surname)'");
            DB::statement("comment on column bookings.verify_surname is 'Passenger surname the airline verifies access with (bookings added by reference)'");
            DB::statement("comment on column bookings.linked_at is 'When a booking made elsewhere was linked to the customer'");
            DB::statement("comment on column bookings.hidden_at is 'When the customer removed an added booking from their list'");
            DB::statement("comment on column customers.bookings_synced_at is 'Last search of the customer''s airline account for bookings (SearchBooking)'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table bookings drop constraint if exists bookings_source_check');
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'hidden_at']);
            $table->dropColumn(['source', 'verify_surname', 'linked_at', 'hidden_at']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('bookings_synced_at');
        });
    }
};
