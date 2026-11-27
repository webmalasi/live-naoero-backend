<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Dynamic master dropdown options and taxonomy management table.
     */
    public function up(): void
    {
        Schema::create('dropdown_options', function (Blueprint $table) {
            $table->id();
            $table->string('group', 100)->index(); // e.g. shop_categories, fleet_types, post_categories, etc.
            $table->string('name', 255); // Display Label (e.g. Signature Apparel, Boeing 737-800)
            $table->string('value', 255)->index(); // Machine value / slug (e.g. Apparel, B737-800)
            $table->string('badge_color', 50)->default('blue'); // blue, emerald, amber, rose, purple, slate, indigo
            $table->string('icon', 100)->nullable(); // Emoji or icon identifier (e.g. 🧥, ✈️, 💼)
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_system')->default(false); // Prevents accidental deletion of vital system defaults
            $table->timestamps();

            // Composite indexes for ultra-fast group queries
            $table->index(['group', 'is_active', 'sort_order'], 'dropdown_opts_group_active_sort_idx');
            $table->index(['group', 'value'], 'dropdown_opts_group_value_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dropdown_options');
    }
};
