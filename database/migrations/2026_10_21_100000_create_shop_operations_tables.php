<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shop operations: a tagged timeline of everything that happens to every
 * order (shop_order_events), refunds through NAB (shop_refunds), shipping
 * and refund totals on orders, and the staff permissions for the admin
 * panel's sales pages.
 */
return new class extends Migration
{
    public const PERMISSIONS = [
        'shop.sales.view' => 'View shop orders, payments and activity',
        'shop.sales.manage' => 'Ship orders, add notes, cancel unpaid orders, resend gift cards',
        'shop.sales.refund' => 'Refund shop payments through NAB',
        'shop.sales.export' => 'Export shop orders and payments (CSV)',
    ];

    public function up(): void
    {
        $pgsql = DB::getDriverName() === 'pgsql';

        // Every step of every order, tagged with its outcome: who did what,
        // when, and what came of it. Never edited or deleted.
        Schema::create('shop_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            // order_placed, payment_started, payment_succeeded, payment_declined,
            // refund_succeeded, shipped, note_added... (see ShopEvents)
            $table->text('type');
            // success / declined / failed / review / pending / cancelled /
            // refunded / info / warning
            $table->text('outcome');
            $table->text('source'); // customer / system / nab / staff
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message');
            $table->decimal('amount', 12, 2)->nullable();
            $table->jsonb('detail')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->index(['shop_order_id', 'created_at'], 'shop_order_events_order');
            $table->index(['type', 'created_at'], 'shop_order_events_type');
            $table->index(['outcome', 'created_at'], 'shop_order_events_outcome');
        });

        Schema::create('shop_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shop_order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('currency');
            $table->text('reason');
            $table->text('status'); // pending / succeeded / failed
            $table->text('nab_refund_id')->nullable();
            $table->text('nab_status')->nullable();
            $table->text('failure_reason')->nullable();
            $table->integer('gift_cards_cancelled')->default(0);
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('detail')->nullable();
            $table->timestampTz('created_at', 6)->useCurrent();
            $table->timestampTz('updated_at', 6)->useCurrent();
            $table->index(['shop_order_id', 'created_at'], 'shop_refunds_order');
        });

        Schema::table('shop_orders', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->timestampTz('refunded_at', 6)->nullable();
            $table->text('shipping_carrier')->nullable();
            $table->text('tracking_number')->nullable();
            $table->text('tracking_url')->nullable();
            $table->timestampTz('shipped_at', 6)->nullable();
            $table->timestampTz('delivered_at', 6)->nullable();
        });

        Schema::table('shop_payment_attempts', function (Blueprint $table) {
            $table->text('card_brand')->nullable();
            $table->text('card_last4')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
        });

        if ($pgsql) {
            DB::statement('alter table shop_orders drop constraint if exists shop_orders_status_check');
            DB::statement("alter table shop_orders add constraint shop_orders_status_check check (status in ('pending_payment', 'paid', 'payment_review', 'cancelled', 'partially_refunded', 'refunded'))");
            DB::statement("alter table shop_order_events add constraint shop_order_events_outcome_check check (outcome in ('success', 'declined', 'failed', 'review', 'pending', 'cancelled', 'refunded', 'info', 'warning'))");
            DB::statement("alter table shop_order_events add constraint shop_order_events_source_check check (source in ('customer', 'system', 'nab', 'staff'))");
            DB::statement("alter table shop_refunds add constraint shop_refunds_status_check check (status in ('pending', 'succeeded', 'failed') and amount > 0)");
        }

        // Past orders get their story from what was already recorded.
        $this->backfill();
        $this->grantPermissions();
    }

    private function backfill(): void
    {
        foreach (DB::table('shop_orders')->orderBy('created_at')->get() as $o) {
            $events = [[$o->created_at, 'order_placed', 'info', 'customer', "Order placed for {$o->currency} {$o->total}", $o->total]];
            foreach (DB::table('shop_payment_attempts')->where('shop_order_id', $o->id)->orderBy('created_at')->get() as $a) {
                $events[] = [$a->created_at, 'payment_started', 'info', 'customer', 'Payment page opened', null];
                $map = ['paid' => ['payment_succeeded', 'success'], 'declined' => ['payment_declined', 'declined'], 'review' => ['payment_review', 'review'], 'failed' => ['payment_failed', 'failed'], 'cancelled' => ['payment_cancelled', 'cancelled']];
                if (isset($map[$a->status])) {
                    $events[] = [$a->updated_at, $map[$a->status][0], $map[$a->status][1], 'nab', ucfirst(str_replace('_', ' ', $map[$a->status][0])).($a->reason ? ": {$a->reason}" : ''), null];
                }
            }
            if ($o->cancelled_at) {
                $expired = $o->cancel_reason === 'not paid in time';
                $events[] = [$o->cancelled_at, $expired ? 'order_expired' : 'order_cancelled', 'cancelled', $expired ? 'system' : 'customer', $o->cancel_reason ? ucfirst($o->cancel_reason) : 'Order cancelled', null];
            }
            foreach ($events as [$at, $type, $outcome, $source, $message, $amount]) {
                DB::table('shop_order_events')->insert([
                    'shop_order_id' => $o->id, 'type' => $type, 'outcome' => $outcome, 'source' => $source,
                    'message' => $message, 'amount' => $amount, 'detail' => json_encode(['backfilled' => true]), 'created_at' => $at,
                ]);
            }
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
            'Staff / Support' => ['shop.sales.view', 'shop.sales.manage'],
            'Data Analyst' => ['shop.sales.view', 'shop.sales.export'],
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
        DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->delete();
        Schema::table('shop_payment_attempts', fn (Blueprint $t) => $t->dropColumn(['card_brand', 'card_last4', 'amount']));
        Schema::table('shop_orders', fn (Blueprint $t) => $t->dropColumn(['refunded_amount', 'refunded_at', 'shipping_carrier', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at']));
        Schema::dropIfExists('shop_refunds');
        Schema::dropIfExists('shop_order_events');
    }
};
