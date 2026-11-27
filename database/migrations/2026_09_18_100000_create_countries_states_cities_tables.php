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
        // 1. Countries Table
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->index();
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->nullable()->index();
            $table->string('phone_code', 20)->nullable()->index();
            $table->string('currency', 10)->nullable()->index();
            $table->string('currency_symbol', 10)->nullable();
            $table->string('currency_name', 100)->nullable();
            $table->string('capital', 100)->nullable();
            $table->string('region', 100)->nullable()->index();
            $table->string('subregion', 100)->nullable()->index();
            $table->string('flag_emoji', 20)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();

            // Composite indexes for fast search and dropdown queries
            $table->index(['is_active', 'sort_order', 'name'], 'idx_countries_active_sort_name');
            $table->index(['region', 'is_active'], 'idx_countries_region_active');
        });

        // 2. States / Provinces / Regions Table
        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->string('name', 150)->index();
            $table->string('state_code', 50)->nullable()->index();
            $table->string('type', 50)->default('State'); // State, Province, Territory, District, Division
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();

            // High performance composite indexes
            $table->index(['country_id', 'is_active', 'name'], 'idx_states_country_active_name');
            $table->index(['country_id', 'state_code'], 'idx_states_country_code');
            $table->index(['country_id', 'sort_order', 'name'], 'idx_states_country_sort_name');
        });

        // 3. Cities / Towns Table
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->onDelete('cascade');
            $table->foreignId('state_id')->constrained('states')->onDelete('cascade');
            $table->string('name', 150)->index();
            $table->string('postal_code', 50)->nullable()->index();
            $table->string('iata_code', 10)->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();

            // High performance composite indexes for cascading lookups
            $table->index(['state_id', 'is_active', 'name'], 'idx_cities_state_active_name');
            $table->index(['country_id', 'is_active', 'name'], 'idx_cities_country_active_name');
            $table->index(['country_id', 'state_id', 'is_active'], 'idx_cities_country_state_active');
            $table->index(['country_id', 'state_id', 'sort_order', 'name'], 'idx_cities_c_s_sort_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};
