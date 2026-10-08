<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_prunes_old_records_and_keeps_recent_ones(): void
    {
        $oldRun = CronRun::factory()->create(['started_at' => now()->subDays(200)]);
        $newRun = CronRun::factory()->create(['started_at' => now()->subDay()]);

        $oldLog = CronLogEntry::factory()->create([
            'cron_run_id' => $newRun->id,
            'site_id' => $newRun->site_id,
            'logged_at' => now()->subDays(60),
        ]);
        $newLog = CronLogEntry::factory()->create([
            'cron_run_id' => $newRun->id,
            'site_id' => $newRun->site_id,
            'logged_at' => now()->subDay(),
        ]);

        $oldNotification = NotificationLog::factory()->create();
        $oldNotification->forceFill(['created_at' => now()->subDays(200)])->save();

        $this->artisan('cronshim:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('cron_runs', ['id' => $oldRun->id]);
        $this->assertDatabaseHas('cron_runs', ['id' => $newRun->id]);
        $this->assertDatabaseMissing('cron_log_entries', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('cron_log_entries', ['id' => $newLog->id]);
        $this->assertDatabaseMissing('notification_logs', ['id' => $oldNotification->id]);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }
}
