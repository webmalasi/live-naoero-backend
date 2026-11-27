<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phone numbers in one form everywhere: E.164 ("+919899765000", what Zenith
 * asks for and SMS gateways need) and the ISO country it belongs to (+1 and
 * +44 are shared, so the country is kept rather than guessed). Existing rows
 * are converted by `php artisan phones:backfill`.
 */
return new class extends Migration
{
    /** table => [column => comment] */
    private const COLUMNS = [
        'customers' => [
            'phone_e164' => 'Main mobile number in E.164 (for SMS), from customer_phones position 0',
            'phone_country' => 'ISO country of phone_e164, as chosen by the customer',
        ],
        'customer_phones' => [
            'e164' => 'phone_number in E.164',
            'country_code' => 'ISO country of the number',
        ],
        'saved_travellers' => [
            'phone_country' => 'ISO country of phone (E.164)',
        ],
        'bookings' => [
            'contact_phone' => 'Contact mobile of the booking in E.164 (lead passenger CTCM, else CTCH)',
            'contact_phone_country' => 'ISO country of contact_phone',
        ],
        'shop_orders' => [
            'contact_phone' => 'Courier contact phone in E.164 (from the shipping address)',
            'contact_phone_country' => 'ISO country of contact_phone',
        ],
        'refund_requests' => [
            'contact_phone_country' => 'ISO country of contact_phone (E.164)',
        ],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach (array_keys($columns) as $column) {
                    str_ends_with($column, 'country') || $column === 'country_code'
                        ? $t->string($column, 2)->nullable()
                        : $t->string($column, 20)->nullable();
                }
            });
            if (DB::getDriverName() === 'pgsql') {
                foreach ($columns as $column => $comment) {
                    DB::statement("COMMENT ON COLUMN {$table}.{$column} IS '".str_replace("'", "''", $comment)."'");
                }
            }
        }
        Schema::table('bookings', fn (Blueprint $t) => $t->index('contact_phone', 'bookings_contact_phone'));
        Schema::table('customers', fn (Blueprint $t) => $t->index('phone_e164', 'customers_phone_e164'));
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropIndex('bookings_contact_phone'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropIndex('customers_phone_e164'));
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(array_keys($columns)));
        }
    }
};
