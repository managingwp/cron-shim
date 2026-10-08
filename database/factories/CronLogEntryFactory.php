<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LogLevel;
use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CronLogEntry>
 */
class CronLogEntryFactory extends Factory
{
    protected $model = CronLogEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cron_run_id' => CronRun::factory(),
            'site_id' => Site::factory(),
            'level' => LogLevel::Info,
            'message' => fake()->sentence(),
            'context' => [],
            'logged_at' => now(),
        ];
    }

    public function level(LogLevel $level): static
    {
        return $this->state(fn (): array => [
            'level' => $level,
        ]);
    }
}
