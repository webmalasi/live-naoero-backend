<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop orders paid in the app through NAB Gateway: the order and its lines
 * (prices taken from shop_products, never from the app), each payment
 * attempt, and the link from issued gift cards (gift_card_orders) back to
 * the order and customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('order_number')->unique(); // also the NAB reference
            $table->foreignUuid('customer_id')->constrained()->restrictOnDelete();
            // pending_payment -> paid | payment_review | cancelled; paid -> refunded
            $table->text('status')->default('pending_payment');
            // Merchandise: processing -> shipped -> delivered (null without merch).
            $table->text('shipping_status')->nullable();
            $table->text('currency');
            $table->decimal('items_total', 12, 2);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->text('contact_email');
            $table->jsonb('shipping_address')->nullable();
            // What the gateway confirmed (transaction id, card brand and last 4).
            $table->text('nab_transaction_id')->nullable();
            $table->jsonb('payment_details')->nullable();
            $table->timestampTz('expires_at', 6);   // unpaid orders are cancelled after this
            $table->timestampTz('paid_at', 6)->nullable();
            $table->timestampTz('cancelled_at', 6)->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestampTz('reconciled_at', 6)->nullable(); // last search at the gateway
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['customer_id', 'created_at'], 'shop_orders_customer');
            $table->index(['status', 'expires_at'], 'shop_orders_status');
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->text('type'); // merch / gift_card
            $table->text('title');
            $table->text('image')->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->integer('quantity');
            $table->decimal('line_total', 12, 2);
            // Gift cards: who receives them and the message.
            $table->jsonb('gift')->nullable();
        });

        // One per "Pay" tap: opens the payment page with a one-time secret.
        Schema::create('shop_payment_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->text('token_hash')->unique(); // SHA-256 of the secret in the page link
            $table->timestampTz('token_expires_at', 6);
            // started -> paid | declined | review | failed | cancelled
            $table->text('status')->default('started');
            $table->text('nab_transaction_id')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('detail')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['shop_order_id', 'created_at'], 'shop_payment_attempts_order');
        });

        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->uuid('shop_order_id')->nullable()->index();
            $table->uuid('customer_id')->nullable()->index();
            $table->foreign('shop_order_id')->references('id')->on('shop_orders')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        if ($pgsql) {
            DB::statement("alter table shop_orders add constraint shop_orders_status_check check (status in ('pending_payment', 'paid', 'payment_review', 'cancelled', 'refunded'))");
            DB::statement("alter table shop_orders add constraint shop_orders_shipping_check check (shipping_status is null or shipping_status in ('processing', 'shipped', 'delivered'))");
            DB::statement("alter table shop_order_items add constraint shop_order_items_type_check check (type in ('merch', 'gift_card') and quantity > 0)");
            DB::statement("alter table shop_payment_attempts add constraint shop_payment_attempts_status_check check (status in ('started', 'paid', 'declined', 'review', 'failed', 'cancelled'))");
            // At most one confirmed payment per order: never charge twice.
            DB::statement("create unique index shop_payment_attempts_one_paid on shop_payment_attempts (shop_order_id) where status = 'paid'");
        }
    }

    public function down(): void
    {
        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->dropForeign(['shop_order_id']);
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['shop_order_id', 'customer_id']);
        });
        Schema::dropIfExists('shop_payment_attempts');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
    }
};
