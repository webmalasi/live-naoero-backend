<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The airline's card form for a payment, served from our own HTTPS page
 * (/api/v1/bookings/payments/{id}/form) instead of being handed to the
 * client as raw HTML. Only a hash of the page link's secret is kept, and the
 * form is deleted once the payment ends.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->longText('form_html')->nullable();
            $table->string('form_token_hash', 64)->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON COLUMN booking_payments.form_html IS 'The airline card form served at /api/v1/bookings/payments/{id}/form while the payment is open; cleared when it ends'");
            DB::statement("COMMENT ON COLUMN booking_payments.form_token_hash IS 'SHA-256 of the secret in the form page link (the secret itself is never stored)'");
        }
    }

    public function down(): void
    {
        Schema::table('booking_payments', function (Blueprint $table) {
            $table->dropColumn(['form_html', 'form_token_hash']);
        });
    }
};
