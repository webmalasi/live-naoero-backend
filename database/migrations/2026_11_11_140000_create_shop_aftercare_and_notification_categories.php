<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything after a shop order is placed, and one inbox for the customer:
 *
 * - customer_notifications get a category (flights, bookings, shop, account)
 *   and a link to the shop order, so the app's notifications screen can
 *   group them and open what they are about;
 * - orders get an invoice and a receipt number, and a fuller delivery
 *   status with every tracking step (shop_shipment_events);
 * - help requests about an order, with their conversation;
 * - product reviews from customers who bought the product;
 * - returns and exchanges, item by item.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        Schema::table('customer_notifications', function (Blueprint $table) {
            $table->string('category', 20)->default('flights')->index();
            $table->uuid('shop_order_id')->nullable()->index();
            // One notice per step: the same step never notifies twice.
            $table->string('dedupe_key', 120)->nullable();
            $table->unique(['customer_id', 'dedupe_key'], 'customer_notifications_dedupe');
        });
        DB::table('customer_notifications')->whereIn('type', ['booking_cancelled', 'booking_refunded', 'flights_cancelled'])->update(['category' => 'bookings']);
        DB::table('customer_notifications')->where('type', 'like', 'back_in_stock')->update(['category' => 'shop']);

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->text('invoice_number')->nullable()->unique();
            $table->text('receipt_number')->nullable()->unique();
            $table->date('estimated_delivery')->nullable();
            // Who cancelled it: customer / staff / system.
            $table->text('cancelled_by')->nullable();
        });
        if ($pgsql) {
            DB::statement('alter table shop_orders drop constraint if exists shop_orders_shipping_check');
            DB::statement("alter table shop_orders add constraint shop_orders_shipping_check check (shipping_status is null or shipping_status in ('processing', 'packed', 'shipped', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_failed', 'cancelled', 'returned'))");
        }

        // Every delivery step the customer can follow, newest last.
        Schema::create('shop_shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->text('status');
            $table->text('message');
            $table->text('location')->nullable();
            $table->text('carrier')->nullable();
            $table->text('tracking_number')->nullable();
            $table->text('source')->default('staff'); // staff / system / carrier
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('occurred_at', 6);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['shop_order_id', 'occurred_at'], 'shop_shipment_events_order');
        });

        Schema::create('shop_support_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('number')->unique();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shop_order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('topic');
            $table->text('subject');
            // open (waiting for us) -> answered (waiting for the customer) -> closed
            $table->text('status')->default('open');
            $table->text('channel')->default('app');
            $table->timestampTz('last_message_at', 6)->useCurrent();
            $table->timestampTz('closed_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['customer_id', 'created_at'], 'shop_support_requests_customer');
            $table->index(['status', 'last_message_at'], 'shop_support_requests_status');
        });

        Schema::create('shop_support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('support_request_id')->constrained('shop_support_requests')->cascadeOnDelete();
            $table->text('author'); // customer / staff / system
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['support_request_id', 'created_at'], 'shop_support_messages_request');
        });

        Schema::create('shop_product_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('shop_order_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('rating');
            $table->text('title')->nullable();
            $table->text('body')->nullable();
            $table->text('reviewer_name');          // "Ana C."
            $table->text('status')->default('published'); // published / hidden
            $table->text('hidden_reason')->nullable();
            $table->text('staff_reply')->nullable();
            $table->timestampTz('replied_at', 6)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->unique(['shop_product_id', 'customer_id']);
            $table->index(['shop_product_id', 'status', 'created_at'], 'shop_product_reviews_product');
        });

        Schema::create('shop_return_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('return_number')->unique();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->text('type'); // return / exchange
            // requested -> approved -> received -> refunded | exchanged; requested/approved -> rejected | cancelled
            $table->text('status')->default('requested');
            $table->text('reason');
            $table->text('details')->nullable();
            $table->text('instructions')->nullable();   // how to send it back
            $table->text('staff_note')->nullable();     // why rejected, or what was found
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->uuid('refund_id')->nullable();
            $table->text('replacement_carrier')->nullable();
            $table->text('replacement_tracking')->nullable();
            $table->text('channel')->default('app');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at', 6)->nullable();
            $table->timestampTz('received_at', 6)->nullable();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['customer_id', 'created_at'], 'shop_return_requests_customer');
            $table->index(['status', 'created_at'], 'shop_return_requests_status');
        });

        Schema::create('shop_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('return_request_id')->constrained('shop_return_requests')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('shop_order_items')->cascadeOnDelete();
            $table->integer('quantity');
            // An exchange: the variant sent instead, and its stock: reserved -> sold | released.
            $table->foreignId('exchange_variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->text('exchange_title')->nullable();
            $table->text('replacement_state')->nullable();
            $table->text('condition')->nullable();       // as received: unused / damaged / ...
            $table->boolean('restocked')->default(false);
        });

        if ($pgsql) {
            DB::statement("alter table shop_shipment_events add constraint shop_shipment_events_status_check check (status in ('processing', 'packed', 'shipped', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_failed', 'cancelled', 'returned'))");
            DB::statement("alter table shop_support_requests add constraint shop_support_requests_status_check check (status in ('open', 'answered', 'closed'))");
            DB::statement("alter table shop_support_messages add constraint shop_support_messages_author_check check (author in ('customer', 'staff', 'system'))");
            DB::statement("alter table shop_product_reviews add constraint shop_product_reviews_check check (rating between 1 and 5 and status in ('published', 'hidden'))");
            DB::statement("alter table shop_return_requests add constraint shop_return_requests_check check (type in ('return', 'exchange') and status in ('requested', 'approved', 'rejected', 'received', 'refunded', 'exchanged', 'cancelled'))");
            DB::statement("alter table shop_return_items add constraint shop_return_items_check check (quantity > 0 and (replacement_state is null or replacement_state in ('reserved', 'sold', 'released')))");
        }

        $this->backfill();
    }

    /**
     * Paid orders get their invoice and receipt numbers; shipped ones get
     * their tracking steps from what was recorded; product ratings come
     * from real reviews only (none yet).
     */
    private function backfill(): void
    {
        foreach (DB::table('shop_orders')->whereNotNull('paid_at')->orderBy('paid_at')->get(['id', 'order_number', 'paid_at']) as $o) {
            $suffix = substr($o->order_number, 3); // NS-ymd-CODE -> ymd-CODE
            DB::table('shop_orders')->where('id', $o->id)->update(['invoice_number' => 'INV-'.$suffix, 'receipt_number' => 'RCT-'.$suffix]);
        }
        foreach (DB::table('shop_orders')->whereNotNull('shipping_status')->get() as $o) {
            $steps = [['processing', 'Order received: we are preparing your parcel', $o->paid_at ?? $o->created_at]];
            if ($o->shipped_at) {
                $steps[] = ['shipped', 'Shipped with '.($o->shipping_carrier ?: 'the carrier').($o->tracking_number ? ", tracking {$o->tracking_number}" : ''), $o->shipped_at];
            }
            if ($o->delivered_at) {
                $steps[] = ['delivered', 'Delivered', $o->delivered_at];
            }
            foreach ($steps as [$status, $message, $at]) {
                DB::table('shop_shipment_events')->insert([
                    'shop_order_id' => $o->id, 'status' => $status, 'message' => $message, 'carrier' => $status === 'shipped' ? $o->shipping_carrier : null,
                    'tracking_number' => $status === 'shipped' ? $o->tracking_number : null, 'source' => 'system', 'occurred_at' => $at, 'created_at' => now(),
                ]);
            }
        }
        DB::table('shop_products')->update(['rating' => 0, 'reviews_count' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_return_items');
        Schema::dropIfExists('shop_return_requests');
        Schema::dropIfExists('shop_product_reviews');
        Schema::dropIfExists('shop_support_messages');
        Schema::dropIfExists('shop_support_requests');
        Schema::dropIfExists('shop_shipment_events');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("update shop_orders set shipping_status = 'shipped' where shipping_status in ('packed', 'in_transit', 'out_for_delivery', 'delivery_failed')");
            DB::statement("update shop_orders set shipping_status = null where shipping_status in ('cancelled', 'returned')");
            DB::statement('alter table shop_orders drop constraint if exists shop_orders_shipping_check');
            DB::statement("alter table shop_orders add constraint shop_orders_shipping_check check (shipping_status is null or shipping_status in ('processing', 'shipped', 'delivered'))");
        }
        Schema::table('shop_orders', fn (Blueprint $t) => $t->dropColumn(['invoice_number', 'receipt_number', 'estimated_delivery', 'cancelled_by']));
        Schema::table('customer_notifications', function (Blueprint $table) {
            $table->dropUnique('customer_notifications_dedupe');
            $table->dropColumn(['category', 'shop_order_id', 'dedupe_key']);
        });
    }
};
