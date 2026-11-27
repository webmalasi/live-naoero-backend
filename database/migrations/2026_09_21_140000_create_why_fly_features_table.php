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
        Schema::create('why_fly_features', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('icon')->default('Plane'); // Lucide icon identifier (Plane, Package, UtensilsCrossed, ShieldCheck, HeartHandshake, etc.)
            $table->string('icon_image')->nullable(); // Optional custom uploaded icon/badge
            $table->string('link_url')->nullable(); // Optional CTA link
            $table->string('link_text')->nullable(); // Optional CTA text
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
        Schema::dropIfExists('why_fly_features');
    }
};
