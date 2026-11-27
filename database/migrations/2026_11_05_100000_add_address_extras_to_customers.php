<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The street number and state of the customer's address, as they saved
 * them. Zenith accepts both in UpdateCustomer but does not keep them for
 * our key (LoadCustomer returns them empty), so our copy is shown whenever
 * Zenith's is empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address_street_number', 20)->nullable()->after('emergency_contact_phone');
            $table->string('address_state', 80)->nullable()->after('address_street_number');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['address_street_number', 'address_state']);
        });
    }
};
