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
        Schema::create('destinations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('country');
            $table->string('flag')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('banner_image')->nullable();
            $table->text('short_description')->nullable();
            $table->text('quote')->nullable();
            $table->string('discover_heading')->nullable();
            $table->text('intro')->nullable();
            $table->text('banner_text')->nullable();
            $table->text('closing_paragraph')->nullable();
            $table->json('why_visit')->nullable();
            $table->json('info_sections')->nullable();
            $table->json('detailed_info')->nullable();
            $table->text('things_to_do_intro')->nullable();
            $table->json('things_to_do')->nullable();
            $table->text('stays_intro')->nullable();
            $table->json('stays')->nullable();
            $table->boolean('is_featured')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('destinations');
    }
};
