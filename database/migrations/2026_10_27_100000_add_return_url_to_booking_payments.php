<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The website page a flight payment sends the customer back to (the app
 * leaves it empty: it catches the return address itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->string('return_url', 1000)->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN booking_payments.return_url IS 'Website page the customer is sent back to after paying (empty for the app)'");
        }
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropColumn('return_url');
        });
    }
};
