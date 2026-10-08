<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\Site;
use App\Models\User;
use App\Services\HealthEvaluator;
use App\Services\IncidentNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function silentSite(): Site
    {
        return Site::factory()->create([
            'last_run_at' => now()->subHours(3),
            'is_active' => true,
            'expected_interval_minutes' => 5,
            'grace_minutes' => 10,
        ]);
    }

    public function test_settings_page_lists_channels(): void
    {
        NotificationChannel::factory()->create(['name' => 'Ops email']);

        $this->actingAs($this->admin())
            ->get('/settings')
            ->assertOk()
            ->assertSee('Ops email')
            ->assertSee('Notification channels');
    }

    public function test_an_email_channel_can_be_created(): void
    {
        $this->actingAs($this->admin())->post('/settings/channels', [
            'name' => 'Ops',
            'type' => 'email',
            'recipients' => "ops@example.com\noncall@example.com",
        ])->assertRedirect();

        $channel = NotificationChannel::query()->firstOrFail();
        $this->assertSame(['ops@example.com', 'oncall@example.com'], $channel->config['recipients']);
    }

    public function test_creating_an_email_channel_requires_recipients(): void
    {
        $this->actingAs($this->admin())->post('/settings/channels', [
            'name' => 'Ops',
            'type' => 'email',
            'recipients' => '',
        ])->assertSessionHasErrors('recipients');
    }

    public function test_opening_an_incident_sends_an_email_and_logs_it(): void
    {
        $channel = NotificationChannel::factory()->create();
        $this->silentSite();

        $this->artisan('cronshim:evaluate-health')->assertExitCode(0);

        $this->assertDatabaseHas('notification_logs', [
            'notification_channel_id' => $channel->id,
            'status' => 'sent',
        ]);
        $this->assertStringContainsString('Opened', (string) NotificationLog::query()->first()->subject);
    }

    public function test_a_slack_channel_delivers_to_the_webhook(): void
    {
        Http::fake();

        $channel = NotificationChannel::factory()->slack()->create();
        $this->silentSite();

        $this->artisan('cronshim:evaluate-health')->assertExitCode(0);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'hooks.slack.com'));
        $this->assertDatabaseHas('notification_logs', [
            'notification_channel_id' => $channel->id,
            'status' => 'sent',
        ]);
    }

    public function test_recovery_sends_a_recovered_notification(): void
    {
        $channel = NotificationChannel::factory()->create();
        $site = Site::factory()->create();
        Incident::factory()->create(['site_id' => $site->id, 'status' => IncidentStatus::Open]);

        app(HealthEvaluator::class)->resolveOpenIncidents($site);

        $this->assertDatabaseHas('notification_logs', [
            'notification_channel_id' => $channel->id,
            'status' => 'sent',
        ]);
        $this->assertStringContainsString('Recovered', (string) NotificationLog::query()->first()->subject);
    }

    public function test_notifications_are_throttled_per_incident(): void
    {
        NotificationChannel::factory()->create();
        $incident = Incident::factory()->create([
            'site_id' => Site::factory(),
            'last_notified_at' => now(),
        ]);

        app(IncidentNotifier::class)->notify($incident, 'opened');

        $this->assertDatabaseCount('notification_logs', 0);
    }

    public function test_a_channel_can_be_tested_from_the_ui(): void
    {
        $channel = NotificationChannel::factory()->create();

        $this->actingAs($this->admin())
            ->post("/settings/channels/{$channel->id}/test")
            ->assertRedirect();

        $this->assertDatabaseHas('notification_logs', [
            'notification_channel_id' => $channel->id,
            'subject' => 'Test notification',
            'status' => 'sent',
        ]);
    }

    public function test_a_channel_can_be_removed(): void
    {
        $channel = NotificationChannel::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/settings/channels/{$channel->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('notification_channels', ['id' => $channel->id]);
    }
}
