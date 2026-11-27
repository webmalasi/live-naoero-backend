<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop existing restrictive check constraint
            DB::statement('ALTER TABLE inquiries DROP CONSTRAINT IF EXISTS inquiries_type_check');
            
            // Re-add with acmi and all airline service types
            DB::statement("ALTER TABLE inquiries ADD CONSTRAINT inquiries_type_check CHECK (type::text = ANY (ARRAY['contact', 'feedback', 'group_booking', 'charter', 'acmi', 'freight', 'cargo', 'general', 'support', 'quote']::text[]))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inquiries DROP CONSTRAINT IF EXISTS inquiries_type_check');
            DB::statement("ALTER TABLE inquiries ADD CONSTRAINT inquiries_type_check CHECK (type::text = ANY (ARRAY['contact', 'feedback', 'group_booking', 'charter', 'freight', 'general']::text[]))");
        }
    }
};
