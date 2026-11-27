<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the main tables readable on their own (e.g. in pgAdmin): every row
 * says what it is about without joining other tables.
 *
 * - shop_orders: what was bought (product ids, types, titles, quantity) and
 *   who bought it (name, email), right after the order id and number.
 * - shop_order_items / shop_payment_attempts / shop_refunds /
 *   shop_order_events: the order number next to the id.
 * - bookings: who booked, the route, first departure and passengers.
 * - booking_payments: the PNR next to the id.
 * - customers: email, names and status first.
 *
 * PostgreSQL can't move columns, so each table is rebuilt in the new column
 * order with every row, key, index, check and foreign key kept (one
 * transaction: it all happens or nothing does). Every column also gets a
 * description, shown by pgAdmin.
 */
return new class extends Migration
{
    private const ORDER = [
        'shop_orders' => ['id', 'order_number', 'product_ids', 'item_types', 'item_titles', 'total_quantity', 'total', 'currency', 'status', 'shipping_status', 'channel', 'customer_name', 'customer_email'],
        'shop_order_items' => ['id', 'order_number', 'shop_order_id', 'shop_product_id', 'type', 'title', 'quantity', 'unit_price', 'line_total'],
        'shop_payment_attempts' => ['id', 'order_number', 'status', 'amount', 'card_brand', 'card_last4', 'reason', 'nab_transaction_id'],
        'shop_refunds' => ['id', 'order_number', 'amount', 'currency', 'status', 'reason'],
        'shop_order_events' => ['id', 'order_number', 'type', 'outcome', 'message', 'amount', 'source'],
        'bookings' => ['id', 'pnr', 'status', 'customer_name', 'customer_email', 'route', 'departure_at', 'passenger_count', 'passenger_names', 'total_amount', 'amount_due', 'currency', 'time_limit', 'ticket_numbers'],
        'booking_payments' => ['id', 'pnr', 'status', 'amount', 'currency', 'fop_code'],
        'customers' => ['id', 'zenith_login', 'first_name', 'last_name', 'status', 'civility_code'],
    ];

    public function up(): void
    {
        $this->addColumns();
        $this->backfill();
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::ORDER as $table => $first) {
                $this->reorder($table, $first);
            }
            $this->describe();
        }
        $this->permissions();
    }

    private function addColumns(): void
    {
        Schema::table('shop_orders', function (Blueprint $t) {
            $t->text('product_ids')->nullable();   // "12, 1"
            $t->text('item_types')->nullable();    // "gift_card, merch"
            $t->text('item_titles')->nullable();   // "1 × AUD 500 Gift Card, 1 × Signature Hoodie"
            $t->integer('total_quantity')->default(0);
            $t->text('customer_name')->nullable();
            $t->text('customer_email')->nullable();
        });
        foreach (['shop_order_items', 'shop_payment_attempts', 'shop_refunds', 'shop_order_events'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->text('order_number')->nullable()->index("{$table}_order_number"));
        }
        Schema::table('bookings', function (Blueprint $t) {
            $t->text('customer_name')->nullable();
            $t->text('customer_email')->nullable();
            $t->text('route')->nullable();          // "BNE → INU → BNE"
            $t->timestampTz('departure_at', 6)->nullable();
            $t->integer('passenger_count')->default(0);
            $t->text('passenger_names')->nullable();
        });
        Schema::table('booking_payments', fn (Blueprint $t) => $t->text('pnr')->nullable()->index('booking_payments_pnr'));
    }

    private function backfill(): void
    {
        foreach (DB::table('shop_orders')->get(['id', 'order_number', 'customer_id']) as $o) {
            $items = DB::table('shop_order_items')->where('shop_order_id', $o->id)->orderBy('id')->get();
            $customer = DB::table('customers')->where('id', $o->customer_id)->first(['first_name', 'last_name', 'zenith_login']);
            DB::table('shop_orders')->where('id', $o->id)->update([
                'product_ids' => $items->pluck('shop_product_id')->filter()->unique()->implode(', '),
                'item_types' => $items->pluck('type')->unique()->implode(', '),
                'item_titles' => $items->map(fn ($i) => "{$i->quantity} × {$i->title}")->implode(', '),
                'total_quantity' => (int) $items->sum('quantity'),
                'customer_name' => trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: null,
                'customer_email' => $customer->zenith_login ?? null,
            ]);
            foreach (['shop_order_items', 'shop_payment_attempts', 'shop_refunds', 'shop_order_events'] as $table) {
                DB::table($table)->where('shop_order_id', $o->id)->update(['order_number' => $o->order_number]);
            }
        }
        foreach (DB::table('bookings')->get(['id', 'pnr', 'customer_id', 'summary']) as $b) {
            DB::table('bookings')->where('id', $b->id)->update(self::bookingColumns(json_decode($b->summary, true) ?: [], DB::table('customers')->where('id', $b->customer_id)->first()));
            DB::table('booking_payments')->where('booking_id', $b->id)->update(['pnr' => $b->pnr]);
        }
    }

    /** The readable booking columns, from its summary and customer. */
    public static function bookingColumns(array $summary, ?object $customer): array
    {
        $flights = $summary['flights'] ?? [];
        $route = [];
        foreach ($flights as $f) {
            if (end($route) !== $f['origin']) {
                $route[] = $f['origin'];
            }
            $route[] = $f['destination'];
        }
        $departure = $flights[0]['departure'] ?? null;

        return [
            'customer_name' => $customer ? (trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: null) : null,
            'customer_email' => $customer->zenith_login ?? null,
            'route' => $route ? implode(' → ', $route) : null,
            'departure_at' => $departure ? str_replace('T', ' ', substr($departure, 0, 19)) : null,
            'passenger_count' => count($summary['passengers'] ?? []),
            'passenger_names' => collect($summary['passengers'] ?? [])->pluck('name')->filter()->implode(', ') ?: null,
        ];
    }

    /**
     * Rebuilds $table with $first columns first (the rest keep their order),
     * keeping data, defaults, identity, keys, checks, indexes, foreign keys
     * (both ways) and comments.
     */
    private function reorder(string $table, array $first): void
    {
        $columns = DB::select("
            select a.attname as name, format_type(a.atttypid, a.atttypmod) as type, a.attnotnull as notnull,
                   pg_get_expr(d.adbin, d.adrelid) as default, a.attidentity as identity, col_description(a.attrelid, a.attnum) as comment
              from pg_attribute a left join pg_attrdef d on d.adrelid = a.attrelid and d.adnum = a.attnum
             where a.attrelid = ?::regclass and a.attnum > 0 and not a.attisdropped order by a.attnum", [$table]);
        $byName = collect($columns)->keyBy('name');
        $ordered = array_values(array_unique([...array_filter($first, fn ($c) => $byName->has($c)), ...$byName->keys()->all()]));

        $own = DB::select("select conname, pg_get_constraintdef(oid) as def from pg_constraint where conrelid = ?::regclass order by contype = 'p' desc, conname", [$table]);
        $incoming = DB::select("select conrelid::regclass::text as tbl, conname, pg_get_constraintdef(oid) as def from pg_constraint where confrelid = ?::regclass and contype = 'f' and conrelid <> confrelid", [$table]);
        $indexes = DB::select("select indexname, indexdef from pg_indexes where schemaname = current_schema() and tablename = ? and indexname not in (select conname from pg_constraint where conrelid = ?::regclass)", [$table, $table]);
        $sequences = DB::select("select a.attname as col, pg_get_serial_sequence(?, a.attname) as seq from pg_attribute a where a.attrelid = ?::regclass and a.attnum > 0 and not a.attisdropped and pg_get_serial_sequence(?, a.attname) is not null", [$table, $table, $table]);

        $new = "{$table}__reordered";
        $defs = [];
        foreach ($ordered as $name) {
            $c = $byName[$name];
            $def = "\"{$name}\" {$c->type}";
            if ($c->identity === 'd' || $c->identity === 'a') {
                $def .= ' generated '.($c->identity === 'a' ? 'always' : 'by default').' as identity';
            } elseif ($c->default !== null) {
                $def .= " default {$c->default}";
            }
            $defs[] = $def.($c->notnull ? ' not null' : '');
        }
        DB::statement("create table \"{$new}\" (".implode(', ', $defs).')');
        $list = implode(', ', array_map(fn ($n) => "\"{$n}\"", $ordered));
        DB::statement("insert into \"{$new}\" ({$list}) overriding system value select {$list} from \"{$table}\"");

        foreach ($sequences as $s) {
            if (! collect($columns)->firstWhere('name', $s->col)->identity) {
                DB::statement("alter sequence {$s->seq} owned by none");
            }
        }
        foreach ($incoming as $fk) {
            DB::statement("alter table {$fk->tbl} drop constraint \"{$fk->conname}\"");
        }
        DB::statement("drop table \"{$table}\"");
        DB::statement("alter table \"{$new}\" rename to \"{$table}\"");

        foreach ($own as $con) {
            DB::statement("alter table \"{$table}\" add constraint \"{$con->conname}\" {$con->def}");
        }
        foreach ($indexes as $index) {
            DB::statement($index->indexdef);
        }
        foreach ($incoming as $fk) {
            DB::statement("alter table {$fk->tbl} add constraint \"{$fk->conname}\" {$fk->def}");
        }
        foreach ($sequences as $s) {
            $identity = collect($columns)->firstWhere('name', $s->col)->identity;
            if (! $identity) {
                DB::statement("alter sequence {$s->seq} owned by \"{$table}\".\"{$s->col}\"");
            }
            $seq = $identity ? DB::selectOne('select pg_get_serial_sequence(?, ?) as s', [$table, $s->col])->s : $s->seq;
            DB::statement("select setval('{$seq}', coalesce((select max(\"{$s->col}\") from \"{$table}\"), 0) + 1, false)");
        }
        foreach ($columns as $c) {
            if ($c->comment !== null) {
                DB::statement("comment on column \"{$table}\".\"{$c->name}\" is ".DB::getPdo()->quote($c->comment));
            }
        }
    }

    /** Descriptions shown in pgAdmin for the main tables and their columns. */
    private function describe(): void
    {
        $tables = [
            'customers' => 'Public accounts (website and app), mirrored from Zenith. No passwords are stored.',
            'shop_orders' => 'Shop orders (gift cards and merchandise) from the website and the app, paid through NAB.',
            'shop_order_items' => 'Lines of each shop order (prices as charged).',
            'shop_payment_attempts' => 'Every attempt to pay a shop order on the NAB payment page, with its outcome.',
            'shop_refunds' => 'Refunds of shop orders through NAB.',
            'shop_order_events' => 'Timeline of every shop order: each step, its outcome tag and who did it.',
            'bookings' => 'Flight bookings made through Zenith (one row per PNR).',
            'booking_payments' => 'Every attempt to pay a flight booking on the airline payment page.',
            'saved_travellers' => 'Passengers customers saved to reuse in later bookings.',
            'refresh_tokens' => 'Device sessions (hashed refresh tokens; one family per sign-in).',
            'auth_events' => 'Sign-ins, sign-ups, password changes and other security events.',
            'server_logs' => 'Every API request, Zenith and NAB call (secrets removed). Kept 90 days.',
            'app_logs' => 'What the mobile app reported (screens, actions, errors). Kept 90 days.',
            'gift_card_orders' => 'Gift card vouchers: codes, balances and recipients (website inquiries and paid shop orders).',
        ];
        foreach ($tables as $table => $comment) {
            if (Schema::hasTable($table)) {
                DB::statement("comment on table \"{$table}\" is ".DB::getPdo()->quote($comment));
            }
        }
        $columns = [
            'shop_orders' => [
                'order_number' => 'Order number shown to the customer and sent to NAB as the payment reference',
                'product_ids' => 'shop_products ids bought in this order',
                'item_types' => 'gift_card and/or merch',
                'item_titles' => 'What was bought, with quantities',
                'total_quantity' => 'Number of items (all lines)',
                'total' => 'Amount charged (items + delivery)',
                'status' => 'pending_payment, paid, payment_review, partially_refunded, refunded or cancelled',
                'shipping_status' => 'Merchandise only: processing, shipped or delivered',
                'channel' => 'Where it was placed: website or app',
                'refunded_amount' => 'Refunded to the card so far',
                'nab_transaction_id' => 'NAB Gateway transaction that paid the order',
                'expires_at' => 'Unpaid orders are cancelled after this (30 minutes)',
            ],
            'bookings' => [
                'pnr' => 'Zenith booking reference',
                'status' => 'pending_payment (on hold), ticketed, expired or cancelled',
                'route' => 'Airports in travel order',
                'departure_at' => 'First flight departure (local airport time)',
                'time_limit' => 'Pay by this time (UTC) or the seats are released',
                'amount_due' => 'Still to pay (0 once ticketed)',
            ],
            'customers' => [
                'zenith_login' => 'Email the customer signs in with',
                'status' => 'active, disabled (by staff) or removal_requested',
                'zenith_customer_ref' => 'Customer reference in Zenith',
            ],
            'shop_payment_attempts' => ['status' => 'started, paid, declined, review, failed or cancelled'],
            'shop_order_events' => ['outcome' => 'success, declined, failed, review, pending, cancelled, refunded, info or warning'],
        ];
        foreach ($columns as $table => $cols) {
            foreach ($cols as $col => $comment) {
                if (Schema::hasColumn($table, $col)) {
                    DB::statement("comment on column \"{$table}\".\"{$col}\" is ".DB::getPdo()->quote($comment));
                }
            }
        }
    }

    /** The website database had none of these; the admin menus need them. */
    private function permissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $now = now();
        foreach (['bookings.view', 'bookings.manage', 'audit.view'] as $name) {
            if (! DB::table('permissions')->where(['name' => $name, 'guard_name' => 'web'])->exists()) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $grants = [
            'Super Admin' => ['bookings.view', 'bookings.manage', 'audit.view'],
            'Admin' => ['bookings.view', 'bookings.manage', 'audit.view'],
            'Flight Operations Manager' => ['bookings.view', 'bookings.manage'],
            'Staff / Support' => ['bookings.view'],
            'Data Analyst' => ['bookings.view', 'audit.view'],
        ];
        foreach ($grants as $role => $permissions) {
            $roleId = DB::table('roles')->where(['name' => $role, 'guard_name' => 'web'])->value('id');
            foreach ($roleId ? $permissions : [] as $name) {
                $permissionId = DB::table('permissions')->where(['name' => $name, 'guard_name' => 'web'])->value('id');
                if (! DB::table('role_has_permissions')->where(['permission_id' => $permissionId, 'role_id' => $roleId])->exists()) {
                    DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('booking_payments', fn (Blueprint $t) => $t->dropColumn('pnr'));
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn(['customer_name', 'customer_email', 'route', 'departure_at', 'passenger_count', 'passenger_names']));
        foreach (['shop_order_items', 'shop_payment_attempts', 'shop_refunds', 'shop_order_events'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('order_number'));
        }
        Schema::table('shop_orders', fn (Blueprint $t) => $t->dropColumn(['product_ids', 'item_types', 'item_titles', 'total_quantity', 'customer_name', 'customer_email']));
    }
};
