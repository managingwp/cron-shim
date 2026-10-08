<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

class IngestService
{
    /**
     * Persist a run report idempotently.
     *
     * @param  array<string, mixed>  $payload
     * @return array{run: CronRun, duplicate: bool}
     */
    public function record(Site $site, array $payload, ?string $sourceIp): array
    {
        /** @var array<string, mixed> $run */
        $run = $payload['run'];
        $runUuid = (string) $run['run_uuid'];

        $existing = $site->runs()->where('run_uuid', $runUuid)->first();

        if ($existing instanceof CronRun) {
            return ['run' => $existing, 'duplicate' => true];
        }

        return DB::transaction(function () use ($site, $payload, $run, $runUuid, $sourceIp): array {
            $cronRun = $site->runs()->create([
                'run_uuid' => $runUuid,
                'started_at' => $run['started_at'] ?? null,
                'finished_at' => $run['finished_at'] ?? null,
                'duration_ms' => $run['duration_ms'] ?? null,
                'status' => $run['status'],
                'exit_code' => $run['exit_code'] ?? null,
                'jobs_run' => $run['jobs_run'] ?? 0,
                'jobs_failed' => $run['jobs_failed'] ?? 0,
                'summary' => $run['summary'] ?? null,
                'source_ip' => $sourceIp,
                'meta' => $payload['meta'] ?? null,
            ]);

            $this->storeLogs($cronRun, $payload['logs'] ?? []);

            $site->forceFill([
                'last_run_at' => $run['finished_at'] ?? $run['started_at'] ?? now(),
                'last_status' => $run['status'],
                'last_seen_ip' => $sourceIp,
            ])->save();

            return ['run' => $cronRun, 'duplicate' => false];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $logs
     */
    private function storeLogs(CronRun $run, array $logs): void
    {
        if ($logs === []) {
            return;
        }

        $now = now();

        $rows = array_map(static fn (array $log): array => [
            'cron_run_id' => $run->id,
            'site_id' => $run->site_id,
            'level' => $log['level'],
            'message' => $log['message'],
            'context' => isset($log['context']) ? json_encode($log['context']) : null,
            'logged_at' => $log['logged_at'] ?? $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $logs);

        CronLogEntry::query()->insert($rows);
    }
}
