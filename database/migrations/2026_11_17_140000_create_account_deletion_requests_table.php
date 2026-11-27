<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Account deletion requests. Nothing is ever deleted: a customer asks
 * (website or app, with a reason and their password), staff then disable
 * the account, which keeps it and all its data and stops it signing in, or
 * decline the request. Each request is one row, so the history stays.
 *
 * customers also say when and why an account was disabled, and by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->string('reason_code', 40);
            $table->text('reason_text')->nullable();
            // website / app
            $table->string('platform', 20)->nullable();
            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            // pending / completed (account disabled) / declined / withdrawn
            $table->string('status', 20)->default('pending');
            $table->timestampTz('requested_at', 6)->useCurrent();
            $table->timestampTz('resolved_at', 6)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            // Staff's note; for a declined request it is shown to the customer.
            $table->text('staff_note')->nullable();
            $table->index(['customer_id', 'requested_at']);
            $table->index(['status', 'requested_at']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->timestampTz('disabled_at', 6)->nullable();
            $table->text('disabled_reason')->nullable();
            // The staff user (users.id). No foreign key: adding one would make
            // SQLite rebuild customers, which breaks its two-column unique key.
            $table->unsignedBigInteger('disabled_by')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("alter table account_deletion_requests add constraint account_deletion_requests_status_check check (status in ('pending', 'completed', 'declined', 'withdrawn'))");
            // At most one open request per customer.
            DB::statement("create unique index account_deletion_requests_one_pending on account_deletion_requests (customer_id) where status = 'pending'");
            DB::statement("comment on table account_deletion_requests is 'Customers asking for their account to be deleted; staff disable the account (nothing is deleted) or decline'");
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'disabled_reason', 'disabled_by']);
        });
        Schema::dropIfExists('account_deletion_requests');
    }
};
