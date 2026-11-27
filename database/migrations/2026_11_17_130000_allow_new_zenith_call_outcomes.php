<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The outcomes a call to the event addresses can now have: every post is
 * kept, so "unverified" (saved, key not accepted) and "recovered" (turned
 * away before every post was kept, saved afterwards) join the earlier ones,
 * which stay for the history.
 */
return new class extends Migration
{
    private const OUTCOMES = ['stored', 'duplicate', 'unverified', 'unknown_event', 'recovered', 'error',
        'invalid_secret', 'missing_secret', 'not_configured', 'ip_not_allowed', 'too_large'];

    public function up(): void
    {
        $this->constrain(self::OUTCOMES);
    }

    public function down(): void
    {
        $this->constrain(['stored', 'duplicate', 'invalid_secret', 'missing_secret', 'not_configured', 'unknown_event', 'ip_not_allowed', 'too_large', 'error']);
    }

    /** @param  list<string>  $outcomes */
    private function constrain(array $outcomes): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('alter table zenith_event_calls drop constraint if exists zenith_event_calls_outcome_check');
        DB::statement('alter table zenith_event_calls add constraint zenith_event_calls_outcome_check check (outcome in ('
            .implode(', ', array_map(fn (string $o) => "'{$o}'", $outcomes)).'))');
    }
};
