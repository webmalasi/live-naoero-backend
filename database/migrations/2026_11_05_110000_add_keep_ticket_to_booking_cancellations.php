<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A new way to cancel a paid booking: "keep_ticket", the flights cancelled
 * and the tickets kept open for a later change or refund.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table booking_cancellations drop constraint if exists booking_cancellations_mode_check');
            DB::statement("alter table booking_cancellations add constraint booking_cancellations_mode_check check (mode in ('booking', 'flights', 'refund', 'keep_ticket'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table booking_cancellations drop constraint if exists booking_cancellations_mode_check');
            DB::statement("alter table booking_cancellations add constraint booking_cancellations_mode_check check (mode in ('booking', 'flights', 'refund'))");
        }
    }
};
