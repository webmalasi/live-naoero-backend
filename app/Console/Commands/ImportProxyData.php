<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Copies the mobile app's data from the previous API database (the Next.js
 * proxy's PostgreSQL "naoero_proxy") into this application's tables: the
 * customers and their profile tables, sessions, auth events, photos, logs,
 * bookings, payments and saved travellers. Ids are kept, so signed-in apps
 * stay signed in (with the same AUTH_JWT_SECRET).
 */
class ImportProxyData extends Command
{
    protected $signature = 'naoero:import-proxy
        {--from= : PostgreSQL URL of the old database, e.g. pgsql://user:pass@host:5432/naoero_proxy}
        {--fresh : Empty the app tables here first}';

    protected $description = 'Copy the mobile app data from the old Next.js API database';

    /** In foreign-key order. */
    private const TABLES = [
        'customers', 'customer_emails', 'customer_phones', 'customer_addresses', 'customer_documents',
        'refresh_tokens', 'auth_events', 'customer_avatars', 'server_logs', 'app_logs',
        'booking_drafts', 'bookings', 'booking_payments', 'saved_travellers',
    ];

    private const IDENTITY_TABLES = ['customer_emails', 'customer_phones', 'customer_addresses', 'customer_documents', 'auth_events', 'server_logs', 'app_logs'];

    public function handle(): int
    {
        $url = (string) $this->option('from');
        if ($url === '') {
            $this->error('Pass --from=pgsql://user:password@host:port/naoero_proxy');

            return self::FAILURE;
        }
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('The import runs against PostgreSQL only.');

            return self::FAILURE;
        }
        config(['database.connections.proxy_import' => [
            ...config('database.connections.pgsql'),
            'url' => $url,
        ]]);
        $source = DB::connection('proxy_import');
        $target = DB::connection();

        if ($this->option('fresh')) {
            $target->statement('truncate '.implode(', ', array_reverse(self::TABLES)).' restart identity cascade');
            $this->info('App tables emptied.');
        } elseif ($target->table('customers')->exists()) {
            $this->error('This database already has customers. Use --fresh to replace them.');

            return self::FAILURE;
        }

        $target->transaction(function () use ($source, $target) {
            foreach (self::TABLES as $table) {
                $count = $this->copy($source, $target, $table);
                $this->line(sprintf('%-20s %8d rows', $table, $count));
            }
            // Rotated refresh tokens point at their successor: linked once all exist.
            foreach ($source->table('refresh_tokens')->whereNotNull('replaced_by')->cursor() as $row) {
                $target->table('refresh_tokens')->where('id', $row->id)->update(['replaced_by' => $row->replaced_by]);
            }
            foreach (self::IDENTITY_TABLES as $table) {
                $target->statement("select setval(pg_get_serial_sequence('{$table}', 'id'), coalesce((select max(id) from {$table}), 0) + 1, false)");
            }
        });
        $this->info('Import complete.');

        return self::SUCCESS;
    }

    private function copy(Connection $source, Connection $target, string $table): int
    {
        if (! $source->getSchemaBuilder()->hasTable($table)) {
            $this->warn("{$table}: not in the source database, skipped");

            return 0;
        }
        $columns = array_values(array_intersect(
            $target->getSchemaBuilder()->getColumnListing($table),
            $source->getSchemaBuilder()->getColumnListing($table),
        ));

        if ($table === 'customer_avatars') {
            return $this->copyAvatars($source, $target, $columns);
        }

        $select = array_map(fn ($c) => match ([$table, $c]) {
            // text[] there, jsonb here.
            ['bookings', 'ticket_numbers'] => DB::raw('to_jsonb(ticket_numbers) as ticket_numbers'),
            ['refresh_tokens', 'replaced_by'] => DB::raw('null::uuid as replaced_by'),
            default => $c,
        }, $columns);

        $count = 0;
        $batch = [];
        $order = in_array('id', $columns, true) ? 'id' : $columns[0];
        foreach ($source->table($table)->select($select)->orderBy($order)->cursor() as $row) {
            $batch[] = (array) $row;
            if (count($batch) === 500) {
                $target->table($table)->insert($batch);
                $count += count($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $target->table($table)->insert($batch);
            $count += count($batch);
        }

        return $count;
    }

    private function copyAvatars(Connection $source, Connection $target, array $columns): int
    {
        $insert = $target->getPdo()->prepare(
            'insert into customer_avatars (customer_id, content_type, image, byte_size, sha256, updated_at) values (?, ?, ?, ?, ?, ?)'
        );
        $count = 0;
        foreach ($source->table('customer_avatars')->cursor() as $row) {
            $image = is_resource($row->image) ? stream_get_contents($row->image) : $row->image;
            $insert->bindValue(1, $row->customer_id);
            $insert->bindValue(2, $row->content_type);
            $insert->bindValue(3, $image, PDO::PARAM_LOB);
            $insert->bindValue(4, (int) $row->byte_size, PDO::PARAM_INT);
            $insert->bindValue(5, $row->sha256);
            $insert->bindValue(6, $row->updated_at);
            $insert->execute();
            $count++;
        }

        return $count;
    }
}
