<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\NotificationLog;
use Illuminate\Console\Command;

class PruneOldData extends Command
{
    protected $signature = 'cronshim:prune';

    protected $description = 'Delete runs, logs, and notification records older than the retention window';

    public function handle(): int
    {
        $runs = CronRun::query()
            ->where('started_at', '<', now()->subDays((int) config('cronshim.retention.runs_days')))
            ->delete();

        $logs = CronLogEntry::query()
            ->where('logged_at', '<', now()->subDays((int) config('cronshim.retention.logs_days')))
            ->delete();

        $notifications = NotificationLog::query()
            ->where('created_at', '<', now()->subDays((int) config('cronshim.retention.notifications_days')))
            ->delete();

        $this->info("Pruned {$runs} run(s), {$logs} log entry(ies), {$notifications} notification(s).");

        return self::SUCCESS;
    }
}
