<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app customers (mirrored from Zenith), their sessions, profile
 * photos, bookings, payments, saved travellers, and the API's logs.
 *
 * Zenith (CitizenPlane) is the system of record for the profile and the
 * only place the password exists; nothing here ever stores a password.
 * Kept separate from the website's admin users table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        // One row per app user, linked to their Zenith customer profile.
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Preproduction and production are separate customer databases,
            // so a reference is only unique within its environment.
            $table->text('zenith_environment');
            $table->text('zenith_customer_ref');
            // The customer's login (their email). Immutable in Zenith once created.
            $table->text('zenith_login');
            $table->text('customer_type')->nullable();
            $table->text('civility_code')->nullable();
            $table->text('first_name')->nullable();
            $table->text('middle_name')->nullable();
            $table->text('last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->text('culture_name')->nullable();
            $table->text('currency')->nullable();
            $table->text('company_name')->nullable();
            $table->text('company_administrative_name')->nullable();
            $table->text('fiscal_document_number')->nullable();
            $table->text('comment')->nullable();
            $table->boolean('newsletter_opt_in')->nullable();
            // App-only data: Zenith has no field for these.
            $table->text('emergency_contact_name')->nullable();
            $table->text('emergency_contact_phone')->nullable();
            $table->text('status')->default('active');
            $table->timestampTz('removal_requested_at', 6)->nullable();
            // The full profile exactly as Zenith last returned it (passwords removed).
            $table->jsonb('zenith_profile')->default('{}');
            $table->timestampTz('profile_synced_at', 6)->nullable();
            $table->timestampTz('last_login_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->unique(['zenith_environment', 'zenith_customer_ref']);
        });
        DB::statement('create unique index customers_environment_login_unique on customers (zenith_environment, lower(zenith_login))');

        // The child tables mirror Zenith's arrays, replaced wholesale on every
        // sync, in the order Zenith returns them ("position").
        Schema::create('customer_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->text('zenith_ref')->nullable();
            $table->text('email');
            $table->unique(['customer_id', 'position']);
        });

        Schema::create('customer_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->text('zenith_ref')->nullable();
            $table->text('location_type_code')->nullable(); // Home / Office
            $table->text('phone_type_code')->nullable();    // Mobile / Voice / Fax
            $table->text('phone_number');
            $table->unique(['customer_id', 'position']);
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->text('zenith_ref')->nullable();
            $table->text('type_code')->nullable(); // Billing / Mailing / CreditCard
            $table->text('street_number')->nullable();
            $table->text('address1')->nullable();
            $table->text('address2')->nullable();
            $table->text('zip_code')->nullable();
            $table->text('city')->nullable();
            $table->text('state')->nullable();
            $table->text('country_code')->nullable();
            $table->unique(['customer_id', 'position']);
        });

        // Travel documents (passport etc.).
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('position');
            $table->text('zenith_ref')->nullable();
            $table->text('document_type_code')->nullable(); // PP = passport, NI = ID card...
            $table->text('document_number')->nullable();
            $table->text('issue_country_code')->nullable();
            $table->text('nationality_country_code')->nullable();
            $table->text('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->date('issuance_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('first_name')->nullable();
            $table->text('last_name')->nullable();
            $table->unique(['customer_id', 'position']);
        });

        // Refresh tokens for the app's sessions, kept only as SHA-256 hashes.
        // Tokens descended from one login share a family.
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->uuid('family_id')->index('refresh_tokens_by_family');
            $table->text('token_hash')->unique();
            $table->text('device_label')->nullable();
            $table->text('ip')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('last_used_at', 6)->nullable();
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->text('revoked_reason')->nullable();
            $table->uuid('replaced_by')->nullable();
            $table->index(['customer_id', 'revoked_at'], 'refresh_tokens_by_customer');
        });
        // Added once the primary key exists: it points at the token that replaced this one.
        Schema::table('refresh_tokens', function (Blueprint $table) {
            $table->foreign('replaced_by')->references('id')->on('refresh_tokens');
        });

        // Who tried what, when. Drives the attempt limits and the audit trail.
        Schema::create('auth_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('login')->nullable();
            $table->text('event');
            $table->boolean('success');
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->text('detail')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['ip', 'event', 'created_at'], 'auth_events_by_ip');
        });
        DB::statement('create index auth_events_by_login on auth_events (lower(login), event, created_at)');

        // Profile photo. Zenith has no field for one, so it lives only here.
        Schema::create('customer_avatars', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->primary()->constrained()->cascadeOnDelete();
            $table->text('content_type');
            $table->binary('image');
            $table->integer('byte_size');
            $table->text('sha256');
            $table->timestampTz('updated_at', 6)->useCurrent();
        });

        // Everything the API does: each request, each Zenith call, auth events
        // and errors, with secrets redacted. No foreign keys: logs outlive customers.
        Schema::create('server_logs', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('logged_at', 6)->index('server_logs_logged_at');
            $table->text('level');
            $table->text('source'); // http / zenith / auth / booking / server
            $table->text('message');
            $table->uuid('request_id')->nullable()->index('server_logs_request');
            $table->text('method')->nullable();
            $table->text('path')->nullable();
            $table->integer('status')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->text('ip')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->jsonb('detail')->nullable();
            $table->index(['customer_id', 'logged_at'], 'server_logs_customer');
            $table->index(['level', 'logged_at'], 'server_logs_level');
        });

        // What the app reports: screens, key actions, API calls, errors.
        Schema::create('app_logs', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('received_at', 6)->useCurrent()->index('app_logs_received_at');
            $table->timestampTz('client_time', 6)->nullable(); // the phone's clock
            $table->text('device_id');
            $table->uuid('customer_id')->nullable();
            $table->text('app_version')->nullable();
            $table->text('platform')->nullable();
            $table->text('level');
            $table->text('event');
            $table->text('message')->nullable();
            $table->jsonb('detail')->nullable();
            $table->text('ip')->nullable();
            $table->index(['device_id', 'received_at'], 'app_logs_device');
            $table->index(['customer_id', 'received_at'], 'app_logs_customer');
        });

        // A PrepareFlights result waiting for the passenger form. The raw
        // Zenith response stays on the server so the app can't alter the fare.
        Schema::create('booking_drafts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->text('offer_ref');
            $table->text('itinerary_ref');
            $table->jsonb('prepare');
            $table->jsonb('form');
            $table->timestampTz('expires_at', 6);
            $table->timestampTz('used_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['customer_id', 'created_at'], 'booking_drafts_customer');
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->text('zenith_environment');
            $table->text('pnr');
            $table->text('status')->default('pending_payment'); // pending_payment / ticketed / expired / cancelled
            $table->text('currency')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('amount_due', 12, 2)->nullable();
            $table->timestampTz('time_limit', 6)->nullable(); // ticketing deadline (UTC)
            $table->jsonb('summary')->default('{}');            // what the app shows
            $table->jsonb('ticket_numbers')->default('[]');
            $table->text('ticket_print_url')->nullable();
            $table->jsonb('zenith_booking')->default('{}');     // as Zenith last returned it
            $table->timestampTz('synced_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->unique(['zenith_environment', 'pnr']);
            $table->index(['customer_id', 'created_at'], 'bookings_customer');
        });

        Schema::create('booking_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->text('fop_code');
            $table->decimal('amount', 12, 2);
            $table->text('currency');
            // started -> accepted / declined / cancelled / closed (reported by
            // the app); confirmed once Zenith shows the tickets issued.
            $table->text('status')->default('started');
            $table->text('detail')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['booking_id', 'created_at'], 'booking_payments_booking');
        });

        // Passengers a customer chose to save while booking, offered again later.
        Schema::create('saved_travellers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->text('title')->nullable();
            $table->text('first_name');
            $table->text('last_name');
            $table->text('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('nationality')->nullable();
            $table->text('document_type')->nullable();
            $table->text('document_number')->nullable();
            $table->text('document_issue_country')->nullable();
            $table->date('document_issue_date')->nullable();
            $table->date('document_expiry')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('last_passenger_type')->nullable(); // AD / CHD / INF...
            $table->timestampTz('last_used_at', 6)->useCurrent();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['customer_id', 'last_used_at'], 'saved_travellers_customer');
        });
        // One entry per person: the same name and birth date updates the existing one.
        DB::statement($pgsql
            ? "create unique index saved_travellers_person on saved_travellers (customer_id, upper(first_name), upper(last_name), coalesce(date_of_birth, '0001-01-01'::date))"
            : "create unique index saved_travellers_person on saved_travellers (customer_id, upper(first_name), upper(last_name), coalesce(date_of_birth, '0001-01-01'))");

        if ($pgsql) {
            $checks = [
                'customers' => [
                    'customers_status_check' => "status in ('active', 'removal_requested', 'disabled')",
                    // Database-level guarantee that no password is ever stored.
                    'zenith_profile_has_no_password' => "not (zenith_profile::text ~* '\"[^\"]*(password|pwd)[^\"]*\"\\s*:')",
                ],
                'customer_avatars' => [
                    'customer_avatars_type_check' => "content_type in ('image/jpeg', 'image/png', 'image/webp')",
                    'customer_avatars_size_check' => 'byte_size > 0 and byte_size <= 2097152',
                ],
                'server_logs' => ['server_logs_level_check' => "level in ('debug', 'info', 'warn', 'error')"],
                'app_logs' => ['app_logs_level_check' => "level in ('debug', 'info', 'warn', 'error')"],
                'bookings' => ['bookings_status_check' => "status in ('pending_payment', 'ticketed', 'expired', 'cancelled')"],
                'booking_payments' => ['booking_payments_status_check' => "status in ('started', 'accepted', 'declined', 'cancelled', 'closed', 'confirmed', 'failed')"],
                'saved_travellers' => ['saved_travellers_gender_check' => "gender in ('M', 'F')"],
            ];
            foreach ($checks as $table => $constraints) {
                foreach ($constraints as $name => $check) {
                    DB::statement("alter table {$table} add constraint {$name} check ({$check})");
                }
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'saved_travellers', 'booking_payments', 'bookings', 'booking_drafts', 'app_logs', 'server_logs',
            'customer_avatars', 'auth_events', 'refresh_tokens', 'customer_documents', 'customer_addresses',
            'customer_phones', 'customer_emails', 'customers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
