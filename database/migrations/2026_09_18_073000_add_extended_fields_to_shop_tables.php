<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_products', function (Blueprint $table) {
            if (!Schema::hasColumn('shop_products', 'compare_at_price')) {
                $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('shop_products', 'short_description')) {
                $table->string('short_description', 500)->nullable()->after('description');
            }
            if (!Schema::hasColumn('shop_products', 'rating')) {
                $table->decimal('rating', 3, 1)->default(5.0)->after('stock_quantity');
            }
            if (!Schema::hasColumn('shop_products', 'reviews_count')) {
                $table->integer('reviews_count')->default(12)->after('rating');
            }
        });

        Schema::table('gift_card_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('gift_card_orders', 'payment_method')) {
                $table->string('payment_method')->default('credit_card')->after('delivery_type');
            }
            if (!Schema::hasColumn('gift_card_orders', 'postal_address')) {
                $table->text('postal_address')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('gift_card_orders', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('voucher_code');
            }
            if (!Schema::hasColumn('gift_card_orders', 'balance_remaining')) {
                $table->decimal('balance_remaining', 10, 2)->nullable()->after('amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'short_description', 'rating', 'reviews_count']);
        });

        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'postal_address', 'expires_at', 'balance_remaining']);
        });
    }
};
