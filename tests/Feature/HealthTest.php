<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Site, 1: string}
     */
    private function makeSite(array $attributes = []): array
    {
        $token = Str::random(40);

        $site = Site::factory()->create(array_merge([
            'ingest_token_hash' => Hash::make($token),
            'signing_secret' => Str::random(48),
            'expected_interval_minutes' => 5,
            'grace_minutes' => 10,
            'is_active' => true,
        ], $attributes));

        return [$site, $token];
    }

    /**
     * @return array<string, mixed>
     */
    private function report(Site $site, string $status): array
    {
        return [
            'site_uuid' => $site->uuid,
            'run' => [
                'run_uuid' => (string) Str::uuid(),
                'started_at' => now()->subSeconds(2)->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'duration_ms' => 2000,
                'status' => $status,
                'exit_code' => $status === 'failed' ? 1 : 0,
            ],
        ];
    }

    private function ingest(Site $site, string $token, string $status): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Shim-Site' => $site->uuid,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/ingest', $this->report($site, $status))->assertStatus(202);
    }

    public function test_the_command_opens_a_missed_run_incident_for_a_silent_site(): void
    {
        [$site] = $this->makeSite(['last_run_at' => now()->subHours(3)]);

        $this->artisan('cronshim:evaluate-health')->assertExitCode(0);

        $this->assertDatabaseHas('incidents', [
            'site_id' => $site->id,
            'type' => IncidentType::MissedRun->value,
            'status' => IncidentStatus::Open->value,
        ]);
    }

    public function test_the_command_does_not_flag_a_healthy_site(): void
    {
        [$site] = $this->makeSite(['last_run_at' => now()->subMinute()]);

        $this->artisan('cronshim:evaluate-health')->assertExitCode(0);

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_missed_run_incidents_are_deduplicated(): void
    {
        [$site] = $this->makeSite(['last_run_at' => now()->subHours(3)]);

        $this->artisan('cronshim:evaluate-health')->assertExitCode(0);
        $this->artisan('cronshim:evaluate-health')->assertExitCode(0);

        $this->assertSame(1, $site->incidents()->where('type', IncidentType::MissedRun->value)->count());
    }

    public function test_a_failed_run_opens_an_incident(): void
    {
        [$site, $token] = $this->makeSite();

        $this->ingest($site, $token, 'failed');

        $this->assertDatabaseHas('incidents', [
            'site_id' => $site->id,
            'type' => IncidentType::RunFailed->value,
            'status' => IncidentStatus::Open->value,
        ]);
    }

    public function test_repeated_failures_escalate_after_the_threshold(): void
    {
        config(['cronshim.health.failure_threshold' => 3]);

        [$site, $token] = $this->makeSite();

        $this->ingest($site, $token, 'failed');
        $this->ingest($site, $token, 'failed');
        $this->ingest($site, $token, 'failed');

        $this->assertDatabaseHas('incidents', [
            'site_id' => $site->id,
            'type' => IncidentType::RepeatedFailure->value,
            'status' => IncidentStatus::Open->value,
        ]);
    }

    public function test_a_successful_run_resolves_open_incidents(): void
    {
        [$site, $token] = $this->makeSite();
        Incident::factory()->create(['site_id' => $site->id, 'status' => IncidentStatus::Open]);
        Incident::factory()->create(['site_id' => $site->id, 'status' => IncidentStatus::Open]);

        $this->ingest($site, $token, 'success');

        $this->assertSame(0, $site->incidents()->where('status', IncidentStatus::Open->value)->count());
        $this->assertSame(2, $site->incidents()->where('status', IncidentStatus::Resolved->value)->count());
    }

    public function test_incidents_can_be_listed_and_resolved_from_the_ui(): void
    {
        $user = User::factory()->create();
        $site = Site::factory()->create(['name' => 'Acme Blog']);
        $incident = Incident::factory()->create([
            'site_id' => $site->id,
            'type' => IncidentType::MissedRun,
            'status' => IncidentStatus::Open,
        ]);

        $this->actingAs($user)->get('/incidents')->assertOk()->assertSee('Acme Blog')->assertSee('Missed run');

        $this->actingAs($user)
            ->post("/incidents/{$incident->id}/acknowledge")
            ->assertRedirect();
        $this->assertNotNull($incident->refresh()->acknowledged_at);

        $this->actingAs($user)
            ->post("/incidents/{$incident->id}/resolve")
            ->assertRedirect();
        $this->assertSame(IncidentStatus::Resolved, $incident->refresh()->status);
    }
}
