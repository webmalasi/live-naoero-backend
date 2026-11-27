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
        // 1. Agency Resources Policies & Guides
        Schema::create('agency_resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable()->default('policy');
            $table->string('file_url')->nullable();
            $table->string('external_url')->nullable();
            $table->string('icon')->nullable()->default('FileText');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 2. Airport Port Check-In Timetables
        Schema::create('check_in_times', function (Blueprint $table) {
            $table->id();
            $table->string('port');
            $table->string('airport_code', 10)->nullable();
            $table->string('opens');
            $table->string('closes');
            $table->boolean('is_highlighted')->default(false);
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 3. Dangerous Goods Classifications & Regulations
        Schema::create('dangerous_goods_items', function (Blueprint $table) {
            $table->id();
            $table->string('item_name');
            $table->string('carry_on')->nullable()->default('Yes');
            $table->string('checked')->nullable()->default('No');
            $table->string('approval_required')->nullable()->default('No');
            $table->string('category')->default('quick_reference'); // quick_reference, prohibited, conditional, topic
            $table->string('icon')->nullable();
            $table->text('details')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active', 'sort_order']);
        });

        // 4. Helpful Information For Your Journey Cards
        Schema::create('travel_info_cards', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->default('Send');
            $table->string('route_url')->nullable();
            $table->boolean('has_link')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // 5. Legal Documents (Privacy Policy, Terms of Use, Conditions of Carriage)
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // privacy-policy, terms-of-use, conditions-of-carriage
            $table->string('title');
            $table->string('heading')->nullable();
            $table->text('subheading')->nullable();
            $table->string('badge_text')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('pdf_url')->nullable();
            $table->string('contact_email')->nullable()->default('privacy@naoeroairlines.com.au');
            $table->text('summary')->nullable();
            $table->json('sections')->nullable(); // structured sections with clauses
            $table->longText('content')->nullable(); // optional raw/HTML content
            $table->date('last_reviewed_at')->nullable();
            $table->date('next_review_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['slug', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
        Schema::dropIfExists('travel_info_cards');
        Schema::dropIfExists('dangerous_goods_items');
        Schema::dropIfExists('check_in_times');
        Schema::dropIfExists('agency_resources');
    }
};
