<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company().' WordPress',
            'url' => fake()->url(),
            'environment' => fake()->randomElement(['production', 'staging', 'development']),
            'timezone' => 'UTC',
            'expected_interval_minutes' => 5,
            'grace_minutes' => 10,
            'is_active' => true,
            'ingest_token_hash' => Hash::make(Str::random(40)),
            'signing_secret' => Str::random(48),
            'last_run_at' => now()->subMinutes(2),
            'last_status' => RunStatus::Success,
            'last_seen_ip' => fake()->ipv4(),
            'notes' => null,
        ];
    }

    public function silent(): static
    {
        return $this->state(fn (): array => [
            'last_run_at' => now()->subDays(2),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }

    public function failing(): static
    {
        return $this->state(fn (): array => [
            'last_status' => RunStatus::Failed,
            'last_run_at' => now()->subMinutes(2),
        ]);
    }
}
