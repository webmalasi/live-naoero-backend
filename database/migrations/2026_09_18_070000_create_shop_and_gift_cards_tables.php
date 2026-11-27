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
        // 1. Shop Products & Merchandise & Gift Cards
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('Merch')->index(); // Gift Card, Merch, Apparel, Drinkware, Toys, Caps, Tshirts
            $table->string('type')->default('merch')->index(); // merch, gift_card, apparel, accessory
            $table->decimal('price', 10, 2)->default(0.00);
            $table->string('currency')->default('AUD');
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->string('sku')->nullable()->index();
            $table->integer('stock_quantity')->nullable()->default(100);
            $table->boolean('is_recommended')->default(false)->index(); // Recommended For U section
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Homepage Shop & Gift Card Banner Cards
        Schema::create('shop_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cta')->default('Shop Collection');
            $table->string('filter')->default('Merch');
            $table->string('link')->default('/gift-cards');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Gift Card Orders & Purchase Inquiries
        Schema::create('gift_card_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('shop_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('AUD');
            $table->integer('quantity')->default(1);
            $table->text('personal_message')->nullable();
            $table->string('delivery_type')->default('digital'); // digital, postal
            $table->string('status')->default('pending')->index(); // pending, confirmed, issued, redeemed, cancelled
            $table->string('voucher_code')->nullable()->unique();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift_card_orders');
        Schema::dropIfExists('shop_banners');
        Schema::dropIfExists('shop_products');
    }
};
