<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Inventory for the shop's merchandise: every product's stock kept per
 * variant (a size, a colour, or the product itself), a ledger of every
 * stock movement, suppliers, purchase orders received into stock, stock
 * counts, and customers waiting for something sold out to come back.
 *
 * shop_products.stock_quantity stays, as what is still available to sell
 * (on hand less what unpaid orders hold), kept up to date by the inventory.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'shop.inventory.view' => 'View shop stock, movements, suppliers, purchase orders and counts',
        'shop.inventory.manage' => 'Adjust shop stock and manage product variants',
        'shop.inventory.purchasing' => 'Manage suppliers and purchase orders, receive stock',
        'shop.inventory.count' => 'Run stock counts',
        'shop.inventory.export' => 'Export stock and movements (CSV)',
    ];

    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        Schema::table('shop_products', function (Blueprint $table) {
            // [{"name": "Size", "values": ["S", "M", "L"]}]; null: one variant.
            $table->jsonb('options')->nullable();
        });

        Schema::create('shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->text('sku')->unique();
            $table->text('barcode')->nullable()->index();
            $table->text('title')->nullable();            // "M / Navy"; null for a product's only variant
            $table->jsonb('option_values')->nullable();   // {"Size": "M", "Colour": "Navy"}
            $table->decimal('price', 12, 2)->nullable();  // null: the product's price
            $table->decimal('cost_price', 12, 2)->nullable(); // average cost of what is on hand
            $table->integer('on_hand')->default(0);
            $table->integer('reserved')->default(0);      // held by orders awaiting payment
            $table->boolean('track_inventory')->default(true); // false: never runs out
            $table->integer('low_stock_threshold')->nullable(); // null: the shop default
            $table->integer('reorder_point')->nullable();
            $table->integer('reorder_quantity')->nullable();
            $table->text('bin_location')->nullable();     // where it sits in the storeroom
            $table->integer('weight_grams')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['shop_product_id', 'sort_order'], 'shop_product_variants_product');
        });

        Schema::create('shop_suppliers', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->text('contact_name')->nullable();
            $table->text('email')->nullable();
            $table->text('phone')->nullable();
            $table->text('website')->nullable();
            $table->text('address')->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->text('currency')->default('AUD');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
        });

        Schema::create('shop_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->text('po_number')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('shop_suppliers')->nullOnDelete();
            // draft -> ordered -> partially_received -> received; draft/ordered -> cancelled
            $table->text('status')->default('draft');
            $table->text('currency')->default('AUD');
            $table->text('supplier_reference')->nullable();
            $table->date('expected_at')->nullable();
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('ordered_at', 6)->nullable();
            $table->timestampTz('received_at', 6)->nullable();
            $table->timestampTz('cancelled_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['status', 'created_at'], 'shop_purchase_orders_status');
        });

        Schema::create('shop_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('shop_purchase_orders')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->text('sku');
            $table->text('title');
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0);
        });

        Schema::create('shop_stock_counts', function (Blueprint $table) {
            $table->id();
            $table->text('count_number')->unique();
            $table->text('status')->default('open'); // open -> completed | cancelled
            $table->text('scope');                   // what was counted: "All merchandise", a category
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
        });

        Schema::create('shop_stock_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained('shop_stock_counts')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->text('sku');
            $table->text('title');
            $table->integer('expected');             // on hand when the count started
            $table->integer('counted')->nullable();
            $table->integer('on_hand_before')->nullable(); // on hand when the count was applied
            $table->integer('variance')->nullable();
        });

        // Every change to stock, never edited or deleted. The product and
        // variant are copied so the history reads the same after a delete.
        Schema::create('shop_inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->foreignId('shop_product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->text('sku');
            $table->text('product_title');
            $table->text('variant_title')->nullable();
            // opening, receipt, adjustment, count, reserve, release, sale, return
            $table->text('type');
            $table->integer('on_hand_change')->default(0);
            $table->integer('reserved_change')->default(0);
            $table->integer('on_hand_after');
            $table->integer('reserved_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->text('reason')->nullable();       // damaged, lost, found, correction...
            $table->text('note')->nullable();
            $table->text('reference_type')->nullable(); // order / purchase_order / stock_count
            $table->text('reference_id')->nullable();
            $table->text('reference_label')->nullable(); // NS-..., PO-..., SC-...
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['variant_id', 'created_at'], 'shop_inventory_movements_variant');
            $table->index(['type', 'created_at'], 'shop_inventory_movements_type');
            $table->index(['reference_type', 'reference_id'], 'shop_inventory_movements_reference');
        });

        // "Tell me when it's back": a customer waiting for a sold-out item.
        Schema::create('shop_stock_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->cascadeOnDelete();
            $table->text('channel')->default('app');
            $table->timestampTz('notified_at', 6)->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['shop_product_id', 'notified_at'], 'shop_stock_alerts_product');
            $table->index(['customer_id', 'created_at'], 'shop_stock_alerts_customer');
        });

        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->text('variant_title')->nullable();
            $table->text('sku')->nullable();
            // Merchandise: reserved (order unpaid) -> sold | released.
            $table->text('stock_state')->nullable();
            $table->integer('returned_quantity')->default(0); // put back in stock after a refund
        });

        if ($pgsql) {
            DB::statement('alter table shop_product_variants add constraint shop_product_variants_stock_check check (on_hand >= 0 and reserved >= 0)');
            DB::statement("alter table shop_purchase_orders add constraint shop_purchase_orders_status_check check (status in ('draft', 'ordered', 'partially_received', 'received', 'cancelled'))");
            DB::statement('alter table shop_purchase_order_items add constraint shop_purchase_order_items_quantity_check check (quantity_ordered > 0 and quantity_received >= 0 and unit_cost >= 0)');
            DB::statement("alter table shop_stock_counts add constraint shop_stock_counts_status_check check (status in ('open', 'completed', 'cancelled'))");
            DB::statement("alter table shop_inventory_movements add constraint shop_inventory_movements_type_check check (type in ('opening', 'receipt', 'adjustment', 'count', 'reserve', 'release', 'sale', 'return'))");
            DB::statement("alter table shop_order_items add constraint shop_order_items_stock_state_check check (stock_state is null or stock_state in ('reserved', 'sold', 'released'))");
            // One waiting alert per customer and item.
            DB::statement('create unique index shop_stock_alerts_one_waiting on shop_stock_alerts (customer_id, shop_product_id, coalesce(variant_id, 0)) where notified_at is null');
            // One variant per product per set of options.
            DB::statement('create unique index shop_product_variants_one_default on shop_product_variants (shop_product_id) where is_default');
        }

        $this->backfill();
        $this->grantPermissions();
    }

    /**
     * Every merchandise product gets its one variant, holding its stock.
     * Unpaid orders had already taken their stock off the product, so it is
     * added back to on hand and held for them instead; paid lines are sold.
     */
    private function backfill(): void
    {
        $now = now();
        $products = DB::table('shop_products')->where('type', '!=', 'gift_card')->whereNotIn('category', ['Gift Card', 'Gift Cards'])->orderBy('id')->get();
        foreach ($products as $p) {
            $held = (int) DB::table('shop_order_items as i')->join('shop_orders as o', 'o.id', '=', 'i.shop_order_id')
                ->where('i.shop_product_id', $p->id)->where('i.type', 'merch')->where('o.status', 'pending_payment')->sum('i.quantity');
            $tracked = $p->stock_quantity !== null;
            $onHand = $tracked ? max(0, (int) $p->stock_quantity) + $held : 0;
            $reserved = $tracked ? $held : 0;
            $sku = $p->sku ?: 'NA-'.$p->id;
            if (DB::table('shop_product_variants')->where('sku', $sku)->exists()) {
                $sku .= '-'.$p->id;
            }
            $variantId = DB::table('shop_product_variants')->insertGetId([
                'shop_product_id' => $p->id, 'sku' => $sku, 'on_hand' => $onHand, 'reserved' => $reserved,
                'track_inventory' => $tracked, 'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('shop_inventory_movements')->insert([
                'variant_id' => $variantId, 'shop_product_id' => $p->id, 'sku' => $sku, 'product_title' => $p->title, 'type' => 'opening',
                'on_hand_change' => $onHand, 'reserved_change' => $reserved, 'on_hand_after' => $onHand, 'reserved_after' => $reserved,
                'note' => $tracked ? 'Stock when inventory started'.($held ? " ({$held} held by unpaid orders)" : '') : 'Not tracked: never runs out',
                'created_at' => $now,
            ]);
            foreach (['pending_payment' => 'reserved', 'cancelled' => 'released'] as $status => $state) {
                DB::table('shop_order_items')->where('shop_product_id', $p->id)->where('type', 'merch')
                    ->whereIn('shop_order_id', DB::table('shop_orders')->where('status', $status)->select('id'))
                    ->update(['variant_id' => $variantId, 'sku' => $sku, 'stock_state' => $tracked ? $state : null]);
            }
            DB::table('shop_order_items')->where('shop_product_id', $p->id)->where('type', 'merch')->whereNull('variant_id')
                ->update(['variant_id' => $variantId, 'sku' => $sku, 'stock_state' => $tracked ? 'sold' : null]);
        }
    }

    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $now = now();
        foreach (array_keys(self::PERMISSIONS) as $name) {
            if (! DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $grants = [
            'Super Admin' => array_keys(self::PERMISSIONS),
            'Admin' => array_keys(self::PERMISSIONS),
            'Staff / Support' => ['shop.inventory.view', 'shop.inventory.count'],
            'Data Analyst' => ['shop.inventory.view', 'shop.inventory.export'],
        ];
        foreach ($grants as $roleName => $permissions) {
            $roleId = DB::table('roles')->where('name', $roleName)->where('guard_name', 'web')->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($permissions as $name) {
                $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
                if (! DB::table('role_has_permissions')->where('permission_id', $permissionId)->where('role_id', $roleId)->exists()) {
                    DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Back to one stock number per product: what is available to sell.
        foreach (DB::table('shop_product_variants')->get()->groupBy('shop_product_id') as $productId => $variants) {
            $tracked = $variants->where('track_inventory', true);
            DB::table('shop_products')->where('id', $productId)->update([
                'stock_quantity' => $tracked->isEmpty() ? null : $tracked->sum(fn ($v) => max(0, $v->on_hand - $v->reserved)),
            ]);
        }
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropColumn(['variant_id', 'variant_title', 'sku', 'stock_state', 'returned_quantity']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table shop_order_items drop constraint if exists shop_order_items_stock_state_check');
        }
        Schema::dropIfExists('shop_stock_alerts');
        Schema::dropIfExists('shop_inventory_movements');
        Schema::dropIfExists('shop_stock_count_items');
        Schema::dropIfExists('shop_stock_counts');
        Schema::dropIfExists('shop_purchase_order_items');
        Schema::dropIfExists('shop_purchase_orders');
        Schema::dropIfExists('shop_suppliers');
        Schema::dropIfExists('shop_product_variants');
        Schema::table('shop_products', fn (Blueprint $t) => $t->dropColumn('options'));
    }
};
