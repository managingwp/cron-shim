<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\RunStatus;
use App\Models\CronRun;
use App\Models\Incident;
use App\Models\Site;

class HealthEvaluator
{
    /**
     * Open a missed-run incident when an active site has fallen silent.
     */
    public function evaluateSite(Site $site): void
    {
        if (! $site->is_active || ! $site->isSilent()) {
            return;
        }

        $this->openIncident($site, IncidentType::MissedRun, [
            'last_run_at' => $site->last_run_at?->toIso8601String(),
            'expected_interval_minutes' => $site->expected_interval_minutes,
            'grace_minutes' => $site->grace_minutes,
        ]);
    }

    /**
     * React to a freshly reported run: raise or clear incidents.
     */
    public function recordRun(Site $site, CronRun $run): void
    {
        if ($run->status === RunStatus::Failed) {
            $this->openIncident($site, IncidentType::RunFailed, [
                'run_uuid' => $run->run_uuid,
                'exit_code' => $run->exit_code,
                'summary' => $run->summary,
            ]);

            $consecutive = $this->consecutiveFailures($site);
            $threshold = (int) config('cronshim.health.failure_threshold');

            if ($consecutive >= $threshold) {
                $this->openIncident($site, IncidentType::RepeatedFailure, [
                    'consecutive_failures' => $consecutive,
                    'threshold' => $threshold,
                ]);
            }

            return;
        }

        $this->resolveOpenIncidents($site);
    }

    /**
     * Open (or refresh) the single open incident of a given type for a site.
     *
     * @param  array<string, mixed>  $details
     */
    public function openIncident(Site $site, IncidentType $type, array $details = []): Incident
    {
        $existing = $site->incidents()
            ->where('status', IncidentStatus::Open->value)
            ->where('type', $type->value)
            ->first();

        if ($existing instanceof Incident) {
            $existing->update(['details' => $details]);

            return $existing;
        }

        return $site->incidents()->create([
            'type' => $type,
            'status' => IncidentStatus::Open,
            'opened_at' => now(),
            'details' => $details,
        ]);
    }

    /**
     * Resolve every open incident for a site (used on recovery).
     */
    public function resolveOpenIncidents(Site $site): int
    {
        $open = $site->incidents()
            ->where('status', IncidentStatus::Open->value)
            ->get();

        foreach ($open as $incident) {
            $incident->update([
                'status' => IncidentStatus::Resolved,
                'resolved_at' => now(),
            ]);
        }

        return $open->count();
    }

    private function consecutiveFailures(Site $site): int
    {
        $statuses = $site->runs()->latest('started_at')->limit(25)->pluck('status');

        $count = 0;

        foreach ($statuses as $status) {
            $value = $status instanceof RunStatus ? $status->value : (string) $status;

            if ($value !== RunStatus::Failed->value) {
                break;
            }

            $count++;
        }

        return $count;
    }
}
