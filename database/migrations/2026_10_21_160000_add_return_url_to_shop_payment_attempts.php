<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where the payment page takes a website customer once they have paid
 * (the website's order page), checked against the allowed hosts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_payment_attempts', function (Blueprint $table) {
            $table->string('return_url', 1000)->nullable();
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("comment on column shop_payment_attempts.return_url is 'Website page the customer is sent back to after paying (empty for the app)'");
        }
    }

    public function down(): void
    {
        Schema::table('shop_payment_attempts', function (Blueprint $table) {
            $table->dropColumn('return_url');
        });
    }
};
