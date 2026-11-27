<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every call to Zenith's event addresses, accepted or turned away: what
 * came in (headers, body) and exactly what we answered (status, headers,
 * body). The stored events themselves are in zenith_events.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zenith_event_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('received_at', 6)->useCurrent();
            $table->boolean('is_test')->default(false);     // preproduction ("Test" address)
            $table->string('address', 80);                   // the last part of the path as called, e.g. flight-changeTest
            $table->string('event', 40)->nullable();          // FlightChange / SegmentFlightCancelled / FlightTransfer; null when unknown
            // stored / duplicate (a retry already stored) / unknown_event / ip_not_allowed / too_large / error
            $table->string('outcome', 20);
            $table->foreignUuid('zenith_event_id')->nullable()->constrained('zenith_events')->nullOnDelete();
            $table->uuid('request_id')->nullable();          // the request's entries in server_logs
            $table->string('method', 10);
            $table->text('path');
            $table->text('query_string')->nullable();
            $table->ipAddress('ip')->nullable();             // inet on PostgreSQL
            $table->text('user_agent')->nullable();
            $table->string('content_type', 200)->nullable();
            $table->jsonb('request_headers');                // cookies and authorization removed
            $table->unsignedInteger('request_bytes');
            $table->char('request_sha256', 64);
            $table->text('request_body')->nullable();        // as UTF-8, first 64 KB (the full body of a stored event is in zenith_events.raw)
            $table->boolean('request_body_truncated')->default(false);
            $table->unsignedSmallInteger('response_status');
            $table->jsonb('response_headers');
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms');
            $table->index(['is_test', 'received_at'], 'zenith_event_calls_env');
            $table->index(['outcome', 'received_at']);
            $table->index('zenith_event_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table zenith_event_calls add constraint zenith_event_calls_outcome_check check (outcome in ('stored', 'duplicate', 'unknown_event', 'ip_not_allowed', 'too_large', 'error'))");
            DB::statement('alter table zenith_event_calls add constraint zenith_event_calls_status_check check (response_status between 100 and 599)');
            DB::statement("comment on table zenith_event_calls is 'Every call to the Zenith event addresses, with the request and the response we sent'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('zenith_event_calls');
    }
};
