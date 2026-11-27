<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Comprehensive database indexing and relationship optimization for PostgreSQL 16.
     */
    public function up(): void
    {
        // 1. Activity Logs: Index foreign key and composite search/audit filters
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('user_id', 'activity_logs_user_id_idx');
            $table->index(['module', 'action'], 'activity_logs_module_action_idx');
            $table->index('created_at', 'activity_logs_created_at_idx');
        });

        // 2. Job Applications: Index foreign key and status/date composites
        Schema::table('job_applications', function (Blueprint $table) {
            $table->index('career_id', 'job_applications_career_id_idx');
            $table->index(['career_id', 'status'], 'job_applications_career_status_idx');
            $table->index(['status', 'created_at'], 'job_applications_status_created_idx');
        });

        // 3. Gift Card Orders: Index foreign key, status, voucher, and date lookups
        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->index('shop_product_id', 'gift_card_orders_product_id_idx');
            $table->index(['status', 'created_at'], 'gift_card_orders_status_created_idx');
            $table->index('buyer_email', 'gift_card_orders_buyer_email_idx');
            $table->index('expires_at', 'gift_card_orders_expires_at_idx');
        });

        // 4. Shop Products: Composite indexes for category/type filtering + sorting
        Schema::table('shop_products', function (Blueprint $table) {
            $table->index(['category', 'is_active', 'sort_order'], 'shop_products_cat_active_sort_idx');
            $table->index(['type', 'is_active'], 'shop_products_type_active_idx');
            $table->index(['is_recommended', 'is_active'], 'shop_products_recommended_active_idx');
            $table->index(['is_featured', 'is_active'], 'shop_products_featured_active_idx');
            $table->index('price', 'shop_products_price_idx');
        });

        // 5. Shop Banners: Active + sort order composite index
        Schema::table('shop_banners', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order'], 'shop_banners_active_sort_idx');
        });

        // 6. Notices: Priority, status, and time-range validity indexes
        Schema::table('notices', function (Blueprint $table) {
            $table->index(['is_active', 'priority'], 'notices_active_priority_idx');
            $table->index(['starts_at', 'expires_at'], 'notices_schedule_idx');
            $table->index('type', 'notices_type_idx');
        });

        // 7. Posts: Published status + date/category composites
        Schema::table('posts', function (Blueprint $table) {
            $table->index(['is_published', 'published_at'], 'posts_published_date_idx');
            $table->index(['category', 'is_published'], 'posts_category_published_idx');
            $table->index(['is_featured', 'is_published'], 'posts_featured_published_idx');
        });

        // 8. Destinations: Composite featured + active + sort indexes
        Schema::table('destinations', function (Blueprint $table) {
            $table->index(['is_active', 'sort_order'], 'destinations_active_sort_idx');
            $table->index(['is_featured', 'is_active'], 'destinations_featured_active_idx');
            $table->index('country', 'destinations_country_idx');
        });

        // 9. Inquiries: Type + status + date composites for inbox filtering
        Schema::table('inquiries', function (Blueprint $table) {
            $table->index(['type', 'status'], 'inquiries_type_status_idx');
            $table->index('created_at', 'inquiries_created_at_idx');
            $table->index('email', 'inquiries_email_idx');
        });

        // 10. Travel Agency Contacts: Country + active composite
        Schema::table('agency_contacts', function (Blueprint $table) {
            $table->index(['country', 'is_active', 'sort_order'], 'agency_contacts_country_active_idx');
        });

        // 11. Flight Schedules: Seasonal validity dates
        Schema::table('flight_schedules', function (Blueprint $table) {
            $table->index(['effective_from', 'effective_until'], 'flight_schedules_validity_idx');
            $table->index(['is_active', 'sort_order'], 'flight_schedules_active_sort_idx');
        });

        // 12. Media Gallery: Destination + category composite
        Schema::table('gallery_items', function (Blueprint $table) {
            $table->index(['destination', 'category', 'is_active', 'sort_order'], 'gallery_dest_cat_active_idx');
        });

        // 13. Special Offers: Active + validity dates
        Schema::table('special_offers', function (Blueprint $table) {
            $table->index(['is_active', 'valid_from', 'valid_until'], 'special_offers_active_validity_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_user_id_idx');
            $table->dropIndex('activity_logs_module_action_idx');
            $table->dropIndex('activity_logs_created_at_idx');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropIndex('job_applications_career_id_idx');
            $table->dropIndex('job_applications_career_status_idx');
            $table->dropIndex('job_applications_status_created_idx');
        });

        Schema::table('gift_card_orders', function (Blueprint $table) {
            $table->dropIndex('gift_card_orders_product_id_idx');
            $table->dropIndex('gift_card_orders_status_created_idx');
            $table->dropIndex('gift_card_orders_buyer_email_idx');
            $table->dropIndex('gift_card_orders_expires_at_idx');
        });

        Schema::table('shop_products', function (Blueprint $table) {
            $table->dropIndex('shop_products_cat_active_sort_idx');
            $table->dropIndex('shop_products_type_active_idx');
            $table->dropIndex('shop_products_recommended_active_idx');
            $table->dropIndex('shop_products_featured_active_idx');
            $table->dropIndex('shop_products_price_idx');
        });

        Schema::table('shop_banners', function (Blueprint $table) {
            $table->dropIndex('shop_banners_active_sort_idx');
        });

        Schema::table('notices', function (Blueprint $table) {
            $table->dropIndex('notices_active_priority_idx');
            $table->dropIndex('notices_schedule_idx');
            $table->dropIndex('notices_type_idx');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_published_date_idx');
            $table->dropIndex('posts_category_published_idx');
            $table->dropIndex('posts_featured_published_idx');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->dropIndex('destinations_active_sort_idx');
            $table->dropIndex('destinations_featured_active_idx');
            $table->dropIndex('destinations_country_idx');
        });

        Schema::table('inquiries', function (Blueprint $table) {
            $table->dropIndex('inquiries_type_status_idx');
            $table->dropIndex('inquiries_created_at_idx');
            $table->dropIndex('inquiries_email_idx');
        });

        Schema::table('agency_contacts', function (Blueprint $table) {
            $table->dropIndex('agency_contacts_country_active_idx');
        });

        Schema::table('flight_schedules', function (Blueprint $table) {
            $table->dropIndex('flight_schedules_validity_idx');
            $table->dropIndex('flight_schedules_active_sort_idx');
        });

        Schema::table('gallery_items', function (Blueprint $table) {
            $table->dropIndex('gallery_dest_cat_active_idx');
        });

        Schema::table('special_offers', function (Blueprint $table) {
            $table->dropIndex('special_offers_active_validity_idx');
        });
    }
};
