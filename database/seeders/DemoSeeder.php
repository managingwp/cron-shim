<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\IncidentType;
use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\Incident;
use App\Models\Site;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed a small demo fleet with runs, logs, and one incident.
     */
    public function run(): void
    {
        $healthy = Site::factory()->create(['name' => 'Acme Blog', 'url' => 'https://blog.example.com']);
        $failing = Site::factory()->failing()->create(['name' => 'Acme Shop', 'url' => 'https://shop.example.com']);
        $silent = Site::factory()->silent()->create(['name' => 'Acme Docs', 'url' => 'https://docs.example.com']);

        foreach ([$healthy, $failing, $silent] as $site) {
            CronRun::factory()
                ->count(4)
                ->create(['site_id' => $site->id])
                ->each(function (CronRun $run): void {
                    CronLogEntry::factory()->count(3)->create([
                        'cron_run_id' => $run->id,
                        'site_id' => $run->site_id,
                    ]);
                });
        }

        Incident::factory()->create([
            'site_id' => $silent->id,
            'type' => IncidentType::MissedRun,
            'opened_at' => now()->subHours(2),
        ]);
    }
}
