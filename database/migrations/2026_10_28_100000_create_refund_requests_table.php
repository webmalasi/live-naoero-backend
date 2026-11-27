<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refund requests: tickets are non-refundable, so customers ask and staff
 * decide case by case in the admin panel (Flight Network → Refund Requests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('reference')->unique();
            $table->text('pnr');
            $table->text('status')->default('submitted'); // submitted / in_review / approved / declined / closed
            $table->text('customer_name')->nullable();
            $table->text('customer_email')->nullable();
            $table->text('passenger_names')->nullable();
            $table->text('reason');
            $table->text('details');
            $table->text('contact_phone')->nullable();
            $table->text('channel')->default('app');
            $table->decimal('booking_total', 12, 2)->nullable();
            $table->text('currency')->nullable();
            $table->text('customer_message')->nullable();
            $table->text('staff_note')->nullable();
            $table->json('passenger_refs');
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('policy_accepted_at', 6);
            $table->timestampTz('decided_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['customer_id', 'created_at'], 'refund_requests_customer');
            $table->index(['status', 'created_at'], 'refund_requests_status');
            $table->index('booking_id', 'refund_requests_booking');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE refund_requests IS 'Refund requests for non-refundable flight tickets; decided by staff in the admin panel'");
            foreach ([
                'reference' => 'Request number shown to the customer, e.g. RR-261028-K7Q2PD',
                'status' => 'submitted, in_review, approved, declined or closed',
                'reason' => 'Reason code chosen by the customer (RefundRequests::REASONS)',
                'customer_message' => 'Reply from staff, shown to the customer',
                'staff_note' => 'Internal note, never shown to the customer',
                'policy_accepted_at' => 'When the customer accepted that tickets are non-refundable and the request may be declined',
            ] as $column => $comment) {
                DB::statement("COMMENT ON COLUMN refund_requests.{$column} IS '".str_replace("'", "''", $comment)."'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
