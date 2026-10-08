<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\CronRun;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CronRun>
 */
class CronRunFactory extends Factory
{
    protected $model = CronRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $started = now()->subSeconds(fake()->numberBetween(1, 300));
        $duration = fake()->numberBetween(200, 8000);

        return [
            'site_id' => Site::factory(),
            'run_uuid' => (string) Str::uuid(),
            'started_at' => $started,
            'finished_at' => $started->copy()->addMilliseconds($duration),
            'duration_ms' => $duration,
            'status' => RunStatus::Success,
            'exit_code' => 0,
            'jobs_run' => fake()->numberBetween(0, 20),
            'jobs_failed' => 0,
            'summary' => 'Due cron events processed',
            'source_ip' => fake()->ipv4(),
            'meta' => ['wp_version' => '6.7', 'php_version' => '8.3', 'shim_version' => '1.0.0'],
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Failed,
            'exit_code' => 1,
            'jobs_failed' => fake()->numberBetween(1, 5),
            'summary' => 'Cron run failed',
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn (): array => [
            'status' => RunStatus::Warning,
            'jobs_failed' => fake()->numberBetween(1, 3),
        ]);
    }
}
