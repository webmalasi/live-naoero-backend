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
        // 1. Travel Agency Contacts Table
        Schema::create('agency_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('country')->index(); // e.g. Fiji, Kiribati, Palau, Naoero
            $table->string('flag')->nullable();
            $table->string('name');
            $table->string('address')->nullable();
            $table->json('phones')->nullable();
            $table->json('emails')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 2. Flight Schedules Table
        Schema::create('flight_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // e.g. NS26-NW27 Updated Schedule
            $table->string('period')->nullable(); // e.g. 02nd July 2026 - 04th January 2027
            $table->string('route')->nullable(); // e.g. Brisbane - Naoero - Tarawa - Majuro - Nadi - Suva
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('notes')->nullable();
            $table->text('alert_notice')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 3. Gallery Items Table
        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('destination')->nullable()->default('All')->index(); // Naoero, Brisbane, Tarawa, Majuro, Pohnpei, Palau
            $table->string('category')->default('general'); // destination, aircraft, hospitality, landscape
            $table->string('image')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 4. Newsletter Subscribers Table
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('source')->default('footer');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('gallery_items');
        Schema::dropIfExists('flight_schedules');
        Schema::dropIfExists('agency_contacts');
    }
};
