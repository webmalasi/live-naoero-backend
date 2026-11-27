<?php

namespace App\Console\Commands;

use App\Services\Logging\ServerLog;
use App\Services\Notifications\FlightEvents;
use Illuminate\Console\Command;

/**
 * Saves as events the Zenith posts that were turned away before every post
 * was kept (their bodies are in zenith_event_calls), links each retry to its
 * event, and processes the ones with the right key. --ip keeps it to
 * Zenith's calls. Safe to run again.
 */
class RecoverZenithEvents extends Command
{
    protected $signature = 'naoero:zenith-events-recover
        {--ip=* : Only calls from these IPs (the Zenith servers), not our own tests}
        {--dry-run : Count only, change nothing}';

    protected $description = 'Turn the Zenith event posts that were turned away into events, and process them';

    public function handle(FlightEvents $events, ServerLog $log): int
    {
        $report = $events->recoverCalls((bool) $this->option('dry-run'), array_values(array_filter((array) $this->option('ip'))));
        $log->flush();

        $this->table(['', ''], [
            ['Calls turned away, now saved', $report['recovered']],
            ['Events made from them', $report['events']],
            ['Queued for processing (right key)', $report['queued']],
            ['Calls left out (empty or cut-off body)', $report['skipped']],
            ['Calls whose event or environment was corrected', $report['recounted']],
            ['Stored events read again', $report['reparsed']],
        ]);
        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing was changed.');
        }

        return self::SUCCESS;
    }
}
