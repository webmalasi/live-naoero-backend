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
        Schema::create('airline_services', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type')->default('passenger')->index(); // passenger, cargo, charter, acmi, special
            $table->string('icon')->nullable(); // bottleglass, cargofreight, plane, shield, etc.
            $table->text('image')->nullable();
            $table->text('description');
            $table->json('features')->nullable(); // Array of bullet points
            $table->string('cta_text')->nullable();
            $table->string('cta_link')->nullable();
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
        Schema::dropIfExists('airline_services');
    }
};
