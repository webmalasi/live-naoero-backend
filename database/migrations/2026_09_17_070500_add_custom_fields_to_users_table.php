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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar')->nullable()->after('phone');
            $table->string('job_title')->nullable()->after('avatar');
            $table->string('department')->nullable()->after('job_title');
            $table->string('status')->default('active')->after('department'); // active, inactive, suspended
            $table->text('bio')->nullable()->after('status');
            $table->timestamp('last_login_at')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'avatar', 'job_title', 'department', 'status', 'bio', 'last_login_at']);
        });
    }
};
