<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'type' => IncidentType::MissedRun,
            'status' => IncidentStatus::Open,
            'opened_at' => now(),
            'resolved_at' => null,
            'details' => [],
            'last_notified_at' => null,
            'notify_count' => 0,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function ofType(IncidentType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }
}
