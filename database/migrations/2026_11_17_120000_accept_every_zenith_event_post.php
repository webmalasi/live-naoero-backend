<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every post to the event addresses is now kept as an event and answered
 * 200, whatever it is. Each event records the address it came to and
 * whether the webhook key in that address was right (key_status). A post
 * without the right key is kept as "unverified": shown in the admin panel,
 * never turned into customer notices unless staff process it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zenith_events', function (Blueprint $table) {
            $table->string('address', 80)->nullable();
            // valid / invalid / missing / not_configured / ip_not_allowed
            $table->string('key_status', 20)->default('valid');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table zenith_events drop constraint if exists zenith_events_status_check');
            DB::statement("alter table zenith_events add constraint zenith_events_status_check check (status in ('received', 'processed', 'ignored', 'failed', 'unverified'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table zenith_events drop constraint if exists zenith_events_status_check');
            DB::statement("alter table zenith_events add constraint zenith_events_status_check check (status in ('received', 'processed', 'ignored', 'failed'))");
        }
        Schema::table('zenith_events', function (Blueprint $table) {
            $table->dropColumn(['address', 'key_status']);
        });
    }
};
