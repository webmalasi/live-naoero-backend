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
        Schema::table('notices', function (Blueprint $table) {
            $table->unsignedInteger('duration_hours')->nullable()->after('type')->comment('Duration in hours for notice to remain active');
            $table->timestamp('starts_at')->nullable()->after('duration_hours')->comment('Scheduled start time');
            $table->timestamp('expires_at')->nullable()->after('starts_at')->comment('Calculated or explicit expiration time');
            $table->integer('priority')->default(0)->after('expires_at')->comment('Display priority / sort order');
            $table->string('badge_text')->nullable()->after('title')->comment('Custom badge/label text like Alert, Weather, News');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['duration_hours', 'starts_at', 'expires_at', 'priority', 'badge_text']);
        });
    }
};
