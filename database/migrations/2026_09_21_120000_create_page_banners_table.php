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
        Schema::create('page_banners', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->unique()->index();
            $table->string('page_name');
            $table->string('page_route')->nullable();
            $table->string('banner_badge_text')->nullable();
            $table->text('banner_image');
            $table->string('banner_heading');
            $table->text('banner_description')->nullable();
            $table->string('banner_button_text')->nullable();
            $table->string('banner_button_link')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_banners');
    }
};
