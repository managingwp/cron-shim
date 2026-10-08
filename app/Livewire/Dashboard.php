<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\RunStatus;
use App\Models\CronRun;
use App\Models\Incident;
use App\Models\Site;
use Illuminate\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    public function render(): View
    {
        $sites = Site::query()->orderBy('name')->get();

        $healthCounts = ['healthy' => 0, 'degraded' => 0, 'silent' => 0, 'disabled' => 0];

        foreach ($sites as $site) {
            $key = $site->health();
            $healthCounts[$key] = ($healthCounts[$key] ?? 0) + 1;
        }

        $since = now()->subDay();

        return view('livewire.dashboard', [
            'sites' => $sites,
            'healthCounts' => $healthCounts,
            'openIncidents' => Incident::query()
                ->with('site:id,name')
                ->open()
                ->latest('opened_at')
                ->limit(10)
                ->get(),
            'openIncidentCount' => Incident::query()->open()->count(),
            'runsToday' => CronRun::query()->where('started_at', '>=', $since)->count(),
            'failuresToday' => CronRun::query()
                ->where('started_at', '>=', $since)
                ->where('status', RunStatus::Failed->value)
                ->count(),
            'slowestRuns' => CronRun::query()
                ->with('site:id,name')
                ->where('started_at', '>=', $since)
                ->orderByDesc('duration_ms')
                ->limit(5)
                ->get(),
            'refreshedAt' => now(),
        ]);
    }
}
