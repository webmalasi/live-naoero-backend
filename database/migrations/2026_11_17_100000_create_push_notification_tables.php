<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone push notifications, kept in our own database (CitizenPlane agreed
 * that we store the device identifiers here rather than in the booking as
 * the UDID special service):
 *
 * - customer_devices: each app install (its UID), the customer and session
 *   it belongs to, its push token (encrypted) and what it wants to receive;
 * - push_credentials: the keys to send with, APNs for iPhones and FCM for
 *   Android, encrypted with the app key and set in the admin panel;
 * - push_deliveries: every push sent for a notice, to which device, and
 *   what Apple or Google answered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // The install's own identifier, made by the app on first launch.
            $table->string('device_uid', 64)->unique();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            // The sign-in session (refresh token family): no push once it ends.
            $table->uuid('session_family_id')->nullable()->index();
            $table->string('platform', 10); // ios / android
            $table->string('provider', 10); // apns / fcm
            $table->text('push_token')->nullable(); // encrypted
            $table->string('token_hash', 64)->nullable()->unique();
            $table->string('apns_environment', 12)->nullable(); // sandbox / production
            $table->string('app_version', 40)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->string('device_model', 80)->nullable();
            $table->string('locale', 20)->nullable();
            $table->string('timezone', 64)->nullable();
            // The phone allows notifications for the app.
            $table->boolean('notifications_enabled')->default(true);
            // The customer's own switches: all pushes, and categories turned off.
            $table->boolean('push_enabled')->default(true);
            $table->json('muted_categories')->nullable();
            $table->timestampTz('last_registered_at', 6)->nullable();
            $table->timestampTz('last_push_at', 6)->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->text('last_error')->nullable();
            // Apple or Google said the token is no longer valid.
            $table->timestampTz('token_invalid_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->index(['customer_id', 'platform']);
        });

        Schema::create('push_credentials', function (Blueprint $table) {
            $table->string('provider', 10)->primary(); // apns / fcm
            $table->boolean('enabled')->default(false);
            $table->text('config'); // encrypted JSON
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('last_test_at', 6)->nullable();
            $table->text('last_test_result')->nullable();
            $table->timestampsTz(6);
        });

        Schema::create('push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('notification_id')->nullable()->constrained('customer_notifications')->nullOnDelete();
            $table->foreignUuid('device_id')->constrained('customer_devices')->cascadeOnDelete();
            $table->uuid('customer_id')->nullable()->index();
            $table->string('provider', 10);
            // sending / sent / failed / invalid_token
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('provider_id')->nullable(); // apns-id or the FCM message name
            $table->text('error')->nullable();
            $table->string('title', 200)->nullable();
            $table->boolean('is_test')->default(false);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('sent_at', 6)->nullable();
            // A retried job never pushes the same notice to a device twice.
            $table->unique(['notification_id', 'device_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_deliveries');
        Schema::dropIfExists('push_credentials');
        Schema::dropIfExists('customer_devices');
    }
};
