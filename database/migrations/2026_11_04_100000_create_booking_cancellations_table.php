<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cancellations customers make themselves (Zenith PrepareCancel -> Cancel):
 * an unpaid booking or some of its flights, or a paid booking refunded to a
 * credit voucher (MCO). One row per attempt, with what Zenith quoted and
 * answered, so staff can see every cancellation and refund in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zenith_environment', 40);
            $table->string('pnr', 12);
            $table->string('mode', 20);                        // booking / flights / refund
            $table->jsonb('segment_refs')->default('[]');      // the flights cancelled (Zenith segment refs)
            $table->string('currency', 3)->nullable();
            $table->decimal('refund_amount', 12, 2)->nullable();   // to the voucher
            $table->decimal('penalty_amount', 12, 2)->nullable();  // kept by the fare rules
            $table->string('refund_method', 20)->nullable();   // voucher (MCO)
            // pending -> done / failed; unknown when the airline did not answer and the booking could not be checked
            $table->string('status', 20)->default('pending');
            $table->string('error_code', 60)->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('quote')->nullable();                // PrepareCancel: amounts and penalties
            $table->jsonb('result')->nullable();               // Cancel: FareInfo as Zenith answered
            $table->jsonb('vouchers')->nullable();             // the customer's vouchers after a refund
            $table->string('ip', 64)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->index(['pnr', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table booking_cancellations add constraint booking_cancellations_mode_check check (mode in ('booking', 'flights', 'refund'))");
            DB::statement("alter table booking_cancellations add constraint booking_cancellations_status_check check (status in ('pending', 'done', 'failed', 'unknown'))");
            DB::statement("comment on table booking_cancellations is 'Cancellations and refunds customers made themselves (Zenith PrepareCancel / Cancel)'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_cancellations');
    }
};
