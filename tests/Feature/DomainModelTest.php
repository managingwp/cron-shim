<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\Incident;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\Site;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_sites_generate_a_uuid_and_store_secrets_encrypted(): void
    {
        $site = Site::factory()->create(['uuid' => null, 'signing_secret' => 'plain-secret']);

        $this->assertNotNull($site->uuid);
        $this->assertNotSame('plain-secret', $site->getRawOriginal('signing_secret'));
        $this->assertSame('plain-secret', $site->signing_secret);
        $this->assertArrayNotHasKey('signing_secret', $site->toArray());
    }

    public function test_relationships_are_wired_up(): void
    {
        $site = Site::factory()->create();
        $run = CronRun::factory()->create(['site_id' => $site->id]);
        $entry = CronLogEntry::factory()->create(['cron_run_id' => $run->id, 'site_id' => $site->id]);
        $incident = Incident::factory()->create(['site_id' => $site->id]);
        $channel = NotificationChannel::factory()->create();
        NotificationLog::factory()->create([
            'incident_id' => $incident->id,
            'notification_channel_id' => $channel->id,
        ]);

        $this->assertTrue($run->site->is($site));
        $this->assertTrue($entry->run->is($run));
        $this->assertTrue($entry->site->is($site));
        $this->assertTrue($site->runs()->whereKey($run->id)->exists());
        $this->assertTrue($site->incidents()->whereKey($incident->id)->exists());
        $this->assertTrue($channel->toArray()['config'] !== null);
    }

    public function test_site_health_reflects_state(): void
    {
        $this->assertSame('healthy', Site::factory()->create()->health());
        $this->assertSame('degraded', Site::factory()->failing()->create()->health());
        $this->assertSame('silent', Site::factory()->silent()->create()->health());
        $this->assertSame('disabled', Site::factory()->inactive()->silent()->create()->health());
    }

    public function test_enums_cast_on_models(): void
    {
        $run = CronRun::factory()->failed()->create();

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertTrue($run->status->isFailure());
    }

    public function test_demo_seeder_populates_a_fleet(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(3, Site::query()->count());
        $this->assertGreaterThanOrEqual(12, CronRun::query()->count());
        $this->assertGreaterThanOrEqual(1, Incident::query()->open()->count());
    }
}
