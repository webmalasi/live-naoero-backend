<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website and app pages edited in the admin panel: each page is an ordered
 * list of sections (text, cards, lists, steps, questions, tables …), read by
 * the website and the app from `GET /api/pages/{slug}`. Every save keeps the
 * previous version, so an edit can be undone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title');
            $table->string('group', 40)->default('pages'); // pages / travel-info / news
            $table->string('path')->nullable(); // the website address, e.g. /charter
            $table->text('summary')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->string('banner_key', 120)->nullable(); // page_banners.page_key
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['group', 'sort_order']);
        });

        Schema::create('content_page_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_page_id')->constrained('content_pages')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('key', 120);
            $table->string('type', 40)->default('text');
            $table->string('heading')->nullable();
            $table->text('subheading')->nullable();
            $table->text('body')->nullable();
            $table->string('image', 1000)->nullable();
            $table->string('image_alt')->nullable();
            $table->json('items')->nullable();
            $table->json('columns')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url', 1000)->nullable();
            $table->text('note')->nullable();
            $table->string('feed', 60)->nullable();
            $table->string('show_on', 10)->default('both'); // both / website / app
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->unique(['content_page_id', 'key']);
            $table->index(['content_page_id', 'position']);
        });

        Schema::create('content_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_page_id')->constrained('content_pages')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['content_page_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_page_revisions');
        Schema::dropIfExists('content_page_sections');
        Schema::dropIfExists('content_pages');
    }
};
