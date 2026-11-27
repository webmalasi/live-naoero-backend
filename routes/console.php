<?php

use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use App\Services\Shop\Inventory;
use App\Services\Shop\Purchasing;
use App\Services\Shop\ShopCheckout;
use App\Services\Support\Js;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:export-sql', function () {
    $this->info('Starting full database export...');
    $outputPath = 'E:/wamp64/www/naoero-airlines/docs/naoero_airlines_postgresql.sql';

    $tables = [
        'countries',
        'states',
        'cities',
        'dropdown_options',
        'posts',
        'destinations',
        'fleets',
        'special_offers',
        'flight_schedules',
        'team_members',
        'testimonials',
        'gallery_items',
        'faqs',
        'agency_contacts',
        'careers',
        'shop_banners',
        'shop_products',
        'site_settings',
        'notices',
        'users',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
        'inquiries',
        'gift_card_orders',
        'job_applications',
        'newsletter_subscribers',
        'activity_logs',
        'migrations',
    ];

    $sql = "-- ====================================================================\n";
    $sql .= "-- Naoero Airlines Database Full Export (PostgreSQL / SQL Dump)\n";
    $sql .= '-- Generated: '.date('Y-m-d H:i:s T')."\n";
    $sql .= "-- Database: naoero_airlines\n";
    $sql .= '-- Total Tables: '.count($tables)."\n";
    $sql .= "-- ====================================================================\n\n";
    $sql .= "SET statement_timeout = 0;\n";
    $sql .= "SET lock_timeout = 0;\n";
    $sql .= "SET client_encoding = 'UTF8';\n";
    $sql .= "SET standard_conforming_strings = on;\n";
    $sql .= "SET check_function_bodies = false;\n";
    $sql .= "SET xmloption = content;\n";
    $sql .= "SET client_min_messages = warning;\n";
    $sql .= "SET row_security = off;\n\n";

    $pdo = DB::connection()->getPdo();

    foreach ($tables as $table) {
        if (! Schema::hasTable($table)) {
            continue;
        }

        $this->line("Exporting table: {$table}");
        $sql .= "-- -------------------------------------------------------------\n";
        $sql .= "-- Table structure & data for: {$table}\n";
        $sql .= "-- -------------------------------------------------------------\n\n";

        // Fetch columns
        $colsStmt = $pdo->prepare("
            SELECT column_name, data_type, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_schema = 'public' AND table_name = :table
            ORDER BY ordinal_position
        ");
        $colsStmt->execute(['table' => $table]);
        $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

        $sql .= "DROP TABLE IF EXISTS \"{$table}\" CASCADE;\n";
        $sql .= "CREATE TABLE \"{$table}\" (\n";
        $colDefs = [];
        foreach ($columns as $col) {
            $def = '    "'.$col['column_name'].'" '.$col['data_type'];
            if ($col['data_type'] === 'character varying') {
                $def = '    "'.$col['column_name'].'" VARCHAR(255)';
            } elseif ($col['data_type'] === 'timestamp without time zone') {
                $def = '    "'.$col['column_name'].'" TIMESTAMP';
            }
            if ($col['is_nullable'] === 'NO') {
                $def .= ' NOT NULL';
            }
            if ($col['column_default'] !== null) {
                $def .= ' DEFAULT '.$col['column_default'];
            }
            $colDefs[] = $def;
        }
        $sql .= implode(",\n", $colDefs);
        $sql .= "\n);\n\n";

        // Fetch all rows
        $rows = DB::table($table)->get();
        if ($rows->count() > 0) {
            $sql .= "-- Dumping data for table: {$table} (".$rows->count()." rows)\n";
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $colNames = array_map(fn ($k) => "\"{$k}\"", array_keys($rowArray));
                $values = array_map(function ($v) use ($pdo) {
                    if ($v === null) {
                        return 'NULL';
                    }
                    if (is_bool($v)) {
                        return $v ? 'TRUE' : 'FALSE';
                    }
                    if (is_numeric($v) && ! str_starts_with((string) $v, '0')) {
                        return (string) $v;
                    }

                    return $pdo->quote((string) $v);
                }, array_values($rowArray));

                $sql .= "INSERT INTO \"{$table}\" (".implode(', ', $colNames).') VALUES ('.implode(', ', $values).");\n";
            }
            $sql .= "\n";
        }
    }

    file_put_contents($outputPath, $sql);
    $sizeKb = round(filesize($outputPath) / 1024, 2);
    $this->info("Full database exported successfully to: {$outputPath} ({$sizeKb} KB)");
})->purpose('Export full PostgreSQL database schema and data to SQL file');

/*
| Mobile app API housekeeping: logs older than LOG_RETENTION_DAYS and
| booking drafts that expired over a day ago are deleted every night.
*/
Artisan::command('naoero:prune', function () {
    $days = max(1, (int) config('naoero.log_retention_days'));
    $cutoff = Js::dbTime(now()->subDays($days));
    $server = DB::table('server_logs')->where('logged_at', '<', $cutoff)->delete();
    $app = DB::table('app_logs')->where('received_at', '<', $cutoff)->delete();
    $drafts = DB::table('booking_drafts')->where('expires_at', '<', Js::dbTime(now()->subDay()))->delete();
    $this->info("Deleted {$server} server log, {$app} app log and {$drafts} draft rows.");
})->purpose('Delete old app API logs and expired booking drafts');

Schedule::command('naoero:prune')->dailyAt('03:15')->withoutOverlapping();

/*
| Shop: confirms payments whose result never reached us (searching NAB by
| the order number) and cancels unpaid orders past their 30 minutes,
| putting their merchandise back on sale.
*/
Artisan::command('naoero:shop-reconcile', function (ShopCheckout $checkout) {
    $pending = DB::table('shop_orders')->where('status', 'pending_payment')
        ->whereExists(fn ($q) => $q->from('shop_payment_attempts')->whereColumn('shop_payment_attempts.shop_order_id', 'shop_orders.id'))
        ->where('created_at', '>', Js::dbTime(now()->subDay()))->limit(50)->get();
    foreach ($pending as $order) {
        $checkout->reconcile($order);
    }
    $expired = $checkout->expireStale();
    app(ServerLog::class)->flush();
    $this->info("Checked {$pending->count()} unpaid orders, cancelled {$expired} expired.");
})->purpose('Confirm missed shop payments and expire unpaid shop orders');

Schedule::command('naoero:shop-reconcile')->everyFiveMinutes()->withoutOverlapping();

/*
| Shop inventory: a morning email to SHOP_INVENTORY_ALERT_EMAILS listing the
| merchandise that is low or sold out and what to reorder. Nothing is sent
| when everything is well stocked or no address is set.
*/
Artisan::command('naoero:shop-low-stock', function () {
    $rows = DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
        ->where('v.is_active', true)->where('p.is_active', true)->where('v.track_inventory', true)
        ->orderBy('p.title')->orderBy('v.sort_order')->select('v.*', 'p.title as product_title')->get()
        ->filter(fn ($v) => in_array(Inventory::status($v), ['low_stock', 'out_of_stock'], true));
    $to = config('naoero.shop.inventory_alert_emails', []);
    if ($rows->isEmpty() || $to === []) {
        $this->info("{$rows->count()} item(s) low or sold out; ".($to === [] ? 'no SHOP_INVENTORY_ALERT_EMAILS set.' : 'nothing to send.'));

        return;
    }
    $suggested = collect(Purchasing::suggestions())->keyBy(fn ($s) => $s['variant']->id);
    $lines = $rows->map(fn ($v) => sprintf('- %s [%s]: %d available (%s)%s',
        Inventory::label($v->product_title, $v->title), $v->sku, Inventory::available($v),
        Inventory::status($v) === 'out_of_stock' ? 'SOLD OUT' : 'low',
        isset($suggested[$v->id]) ? ', reorder '.$suggested[$v->id]['suggested'] : ''));
    $body = "Naoero Shop stock needing attention:\n\n".$lines->implode("\n")."\n\nInventory: ".route('admin.inventory.index')
        ."\nReorder: ".route('admin.inventory.purchase-orders.create', ['from' => 'suggestions'])."\n";
    Mail::raw($body, fn ($m) => $m->to($to)->subject("Naoero Shop: {$rows->count()} item(s) low or sold out"));
    $this->info("Sent the low-stock list ({$rows->count()} items) to ".implode(', ', $to).'.');
})->purpose('Email the shop items that are low or sold out');

Schedule::command('naoero:shop-low-stock')->dailyAt('08:00')->withoutOverlapping();

/*
| Flight events from Zenith: processes again events still waiting after 10
| minutes (a lost queue job). With --failed, events that failed in the last
| 3 days (e.g. Zenith unreachable) are retried too, up to 20 attempts; with
| an id, that one event.
*/
Artisan::command('naoero:zenith-events {id?} {--failed}', function (FlightEvents $events) {
    $ids = $this->argument('id')
        ? [$this->argument('id')]
        : DB::table('zenith_events')
            ->where(fn ($q) => $q->where('status', 'received')->where('received_at', '<', Js::dbTime(now()->subMinutes(10)))
                ->when($this->option('failed'), fn ($q) => $q->orWhere(fn ($f) => $f->where('status', 'failed')->whereNotNull('pnr')
                    ->where('attempts', '<', 20)->where('received_at', '>', Js::dbTime(now()->subDays(3))))))
            ->orderBy('received_at')->limit(200)->pluck('id')->all();
    foreach ($ids as $id) {
        DB::table('zenith_events')->where('id', $id)->where('status', 'failed')->update(['status' => 'received']);
        try {
            $events->process($id);
        } catch (Throwable $e) {
            $events->failed($id, $e);
        }
    }
    app(ServerLog::class)->flush();
    $this->info('Processed '.count($ids).' Zenith event(s).');
})->purpose('Process Zenith flight events that are still waiting or failed');

Schedule::command('naoero:zenith-events --failed')->everyTenMinutes()->withoutOverlapping();

// What arrived from Zenith and what became of it. Run it inside the app
// container (docker exec naoero-laravel-app php artisan ...): the host has
// no route to the database.
Artisan::command('naoero:zenith-events-status {--hours=48 : How far back} {--pnr= : Only this booking}', function () {
    $since = Js::dbTime(now()->subHours(max(1, (int) $this->option('hours'))));
    $rows = DB::table('zenith_events as e')->where('e.received_at', '>=', $since)
        ->when($this->option('pnr'), fn ($q, $pnr) => $q->where('e.pnr', strtoupper($pnr)))
        ->orderBy('e.received_at')
        ->get(['e.id', 'e.received_at', 'e.event', 'e.address', 'e.is_test', 'e.key_status', 'e.status', 'e.pnr', 'e.processed_at', 'e.detail',
            DB::raw('(select count(*) from customer_notifications n where n.zenith_event_id = e.id) as notices'),
            DB::raw('(select count(*) from zenith_event_calls c where c.zenith_event_id = e.id) as calls')]);
    $this->table(['Received (UTC)', 'Event', 'Address', 'Env', 'Status', 'Booking', 'Calls', 'Notices', 'Processed', 'Detail'], $rows->map(fn ($e) => [
        substr((string) $e->received_at, 0, 19), $e->event, $e->address ?? '—', $e->is_test ? 'pre-prod' : 'prod',
        $e->status.($e->key_status !== 'valid' ? " (key {$e->key_status})" : ''), $e->pnr ?? '—', $e->calls, $e->notices,
        $e->processed_at ? substr((string) $e->processed_at, 11, 8) : '—', mb_strimwidth((string) $e->detail, 0, 90, '…'),
    ])->all());
    $failed = DB::table('failed_jobs')->where('payload', 'like', '%ProcessZenithEvent%')->count();
    $waiting = DB::table('jobs')->where('payload', 'like', '%ProcessZenithEvent%')->count();
    $this->line(count($rows)." event(s). Queue: {$waiting} waiting, {$failed} failed ProcessZenithEvent job(s).");
})->purpose('List the Zenith flight events received recently and what became of each');
