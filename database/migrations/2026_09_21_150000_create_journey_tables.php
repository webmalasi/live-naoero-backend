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
        // 1. Our Journey Timeline Milestones (About Us Page)
        Schema::create('our_journey_milestones', function (Blueprint $table) {
            $table->id();
            $table->string('year'); // e.g. 1970, 1985, 2006, 2024, 2026
            $table->string('title'); // e.g. Foundation of Air Nauru, Boeing Fleet Expansion
            $table->text('description');
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 2. Enhance Your Journey Cards (Homepage Booking Essentials)
        Schema::create('enhance_journey_items', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // e.g. Hotels, Rental Car, Travel Insurance, Activities
            $table->text('description');
            $table->string('icon')->default('Compass'); // Lucide icon: BedDouble, Car, HeartHandshake, Compass
            $table->string('image')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_text')->default('Know More');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enhance_journey_items');
        Schema::dropIfExists('our_journey_milestones');
    }
};
