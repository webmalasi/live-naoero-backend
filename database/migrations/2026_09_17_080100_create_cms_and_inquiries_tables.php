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
        // 1. Fleets Table
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // e.g. B737-300, B737-700, B737-800, B737-300F, B737-800F
            $table->string('registration')->unique(); // e.g. VH-XNU, VH-INU, VH-ONU
            $table->enum('category', ['passenger', 'freight'])->default('passenger')->index();
            $table->string('seating')->nullable(); // e.g. 18J/102Y
            $table->string('configuration')->nullable(); // e.g. 8 x PAG ULD's 10-12m3 EA
            $table->string('image')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 2. Posts (News & Blog) Table
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('General')->index();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->string('author')->nullable();
            $table->dateTime('published_at')->nullable()->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
        });

        // 3. Testimonials Table
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->text('quote');
            $table->string('image')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 4. Team Members Table
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role');
            $table->string('department')->default('Operations')->index();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 5. Careers (Job Postings) Table
        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('department')->nullable();
            $table->string('location')->default('Brisbane, Australia');
            $table->string('employment_type')->default('Full-Time');
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('requirements')->nullable();
            $table->string('seek_url')->nullable();
            $table->date('deadline')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 6. Job Applications Table
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->nullable()->constrained('careers')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('cover_letter')->nullable();
            $table->string('resume_path')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'shortlisted', 'rejected'])->default('pending')->index();
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        // 7. Special Offers Table
        Schema::create('special_offers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 10)->default('AUD');
            $table->string('badge')->nullable(); // e.g. Special Fare, Hot Deal
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('image')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 8. FAQs Table
        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('category')->default('General')->index();
            $table->text('question');
            $table->longText('answer');
            $table->boolean('is_active')->default(true)->index();
            $table->integer('sort_order')->default(0)->index();
            $table->timestamps();
        });

        // 9. Inquiries & Submissions Table
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->default('contact')->index();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->longText('message')->nullable();
            $table->json('details')->nullable(); // additional form fields like class of travel, passenger count, departure date, etc.
            $table->enum('status', ['new', 'in_progress', 'resolved', 'archived'])->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        // 10. Site Notices (Notice Bar) & Global Settings
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title')->default('Update and News');
            $table->text('message');
            $table->string('link')->nullable();
            $table->string('type')->default('info'); // info, warning, alert
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general')->index(); // general, contact, social, footer
            $table->string('type')->default('text'); // text, textarea, boolean, json
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('inquiries');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('special_offers');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('careers');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('fleets');
    }
};
