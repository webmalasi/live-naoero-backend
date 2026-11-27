<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flight disruption events Zenith posts to us (EventMap: FlightChange,
 * SegmentFlightCancelled, FlightTransfer), and the notices customers read in
 * the app and on the website.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zenith_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event', 40);              // FlightChange / SegmentFlightCancelled / FlightTransfer
            $table->boolean('is_test')->default(false); // preproduction (Zenith adds "Test" to the address)
            $table->string('format', 10);             // json / xml
            $table->string('payload_hash', 64);       // Zenith retries up to 3 times: the same body is stored once
            $table->text('raw');
            $table->jsonb('payload')->nullable();
            $table->string('pnr', 12)->nullable();
            // received -> processed (customers notified) / ignored (no customer of ours) / failed
            $table->string('status', 20)->default('received');
            $table->text('detail')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('ip', 64)->nullable();
            $table->timestampTz('received_at', 6)->useCurrent();
            $table->timestampTz('processed_at', 6)->nullable();
            $table->unique(['event', 'payload_hash']);
            $table->index(['pnr', 'received_at']);
            $table->index(['status', 'received_at']);
        });

        Schema::create('customer_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('zenith_event_id')->nullable()->constrained('zenith_events')->nullOnDelete();
            $table->string('type', 40);               // flight_time_change / flight_cancelled / flight_transfer
            $table->string('pnr', 12)->nullable();
            $table->text('title');
            $table->text('body');
            $table->jsonb('data')->default('{}');     // the flights before and after
            $table->timestampTz('read_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->unique(['customer_id', 'zenith_event_id']);
            $table->index(['customer_id', 'created_at'], 'customer_notifications_customer');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table zenith_events add constraint zenith_events_status_check check (status in ('received', 'processed', 'ignored', 'failed'))");
            DB::statement("comment on table zenith_events is 'Events posted by Zenith (EventMap): flight time changes, cancellations and transfers'");
            DB::statement("comment on table customer_notifications is 'Notices shown to customers in the app and on the website'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
        Schema::dropIfExists('zenith_events');
    }
};
