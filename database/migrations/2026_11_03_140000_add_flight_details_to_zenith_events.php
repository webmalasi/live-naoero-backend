<?php

use App\Services\Notifications\FlightEvents;
use App\Services\Support\Js;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What each Zenith event says, in typed columns beside the raw post: the
 * flight as it was and as it is now, the reason, and the customer Zenith
 * names. Calls refused for the webhook key are saved with their outcome.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zenith_events', function (Blueprint $table) {
            // The flight as it was: a cancellation's flight, a time change's or transfer's Old values.
            $table->string('airline', 3)->nullable();
            $table->string('flight_number', 8)->nullable();
            $table->string('origin', 5)->nullable();
            $table->string('destination', 5)->nullable();
            $table->timestamp('departure_at')->nullable();   // the airport's local time, as Zenith gives it
            $table->timestamp('arrival_at')->nullable();
            $table->string('equipment', 120)->nullable();
            // The flight as it is now (New values); none for a cancellation.
            $table->string('new_airline', 3)->nullable();
            $table->string('new_flight_number', 8)->nullable();
            $table->string('new_origin', 5)->nullable();
            $table->string('new_destination', 5)->nullable();
            $table->timestamp('new_departure_at')->nullable();
            $table->timestamp('new_arrival_at')->nullable();
            $table->string('new_equipment', 120)->nullable();
            $table->string('origin_terminal', 20)->nullable();
            $table->string('destination_terminal', 20)->nullable();
            $table->string('segment_ref', 40)->nullable();
            $table->text('reason')->nullable();               // CancellationReason / TransferReason
            // The customer Zenith names in <Data>.
            $table->string('customer_ref', 40)->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->string('customer_email', 254)->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->index(['flight_number', 'departure_at']);
        });

        foreach (DB::table('zenith_events')->whereNotNull('payload')->get(['id', 'payload']) as $e) {
            $payload = Js::decode($e->payload);
            if (is_array($payload)) {
                DB::table('zenith_events')->where('id', $e->id)->update(FlightEvents::details($payload));
            }
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table zenith_event_calls drop constraint if exists zenith_event_calls_outcome_check');
            DB::statement("alter table zenith_event_calls add constraint zenith_event_calls_outcome_check check (outcome in ('stored', 'duplicate', 'invalid_secret', 'missing_secret', 'not_configured', 'unknown_event', 'ip_not_allowed', 'too_large', 'error'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table zenith_event_calls drop constraint if exists zenith_event_calls_outcome_check');
            DB::statement("alter table zenith_event_calls add constraint zenith_event_calls_outcome_check check (outcome in ('stored', 'duplicate', 'unknown_event', 'ip_not_allowed', 'too_large', 'error'))");
        }
        Schema::table('zenith_events', function (Blueprint $table) {
            $table->dropIndex(['flight_number', 'departure_at']);
            $table->dropColumn(['airline', 'flight_number', 'origin', 'destination', 'departure_at', 'arrival_at', 'equipment',
                'new_airline', 'new_flight_number', 'new_origin', 'new_destination', 'new_departure_at', 'new_arrival_at', 'new_equipment',
                'origin_terminal', 'destination_terminal', 'segment_ref', 'reason', 'customer_ref', 'customer_name', 'customer_email', 'customer_phone']);
        });
    }
};
