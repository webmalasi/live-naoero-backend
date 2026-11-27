<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permissions switched off for one staff account even though one of its
 * roles gives them, so a single admin can be limited without changing the
 * role every other admin shares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_blocked_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocked_permissions');
    }
};
