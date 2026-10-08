<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LogLevel;
use App\Enums\RunStatus;
use App\Models\CronLogEntry;
use App\Models\CronRun;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunBrowsingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_runs_index_lists_runs_from_all_sites(): void
    {
        $site = Site::factory()->create(['name' => 'Acme Blog']);
        CronRun::factory()->create(['site_id' => $site->id]);

        $this->actingAs($this->admin())
            ->get('/runs')
            ->assertOk()
            ->assertSee('Acme Blog');
    }

    public function test_runs_can_be_filtered_by_site(): void
    {
        $alpha = Site::factory()->create(['name' => 'Alpha Blog']);
        $beta = Site::factory()->create(['name' => 'Beta Shop']);
        CronRun::factory()->count(2)->create(['site_id' => $alpha->id]);
        CronRun::factory()->create(['site_id' => $beta->id]);

        $this->actingAs($this->admin())
            ->get('/runs?site='.$alpha->id)
            ->assertOk()
            ->assertSeeText('2 run(s) across all sites.')
            ->assertSee('Alpha Blog');
    }

    public function test_runs_can_be_filtered_by_status(): void
    {
        $ok = Site::factory()->create(['name' => 'Green Site']);
        $bad = Site::factory()->create(['name' => 'Red Site']);
        CronRun::factory()->count(2)->create(['site_id' => $ok->id, 'status' => RunStatus::Success]);
        CronRun::factory()->failed()->create(['site_id' => $bad->id]);

        $this->actingAs($this->admin())
            ->get('/runs?status=failed')
            ->assertOk()
            ->assertSeeText('1 run(s) across all sites.')
            ->assertSee('Red Site');
    }

    public function test_a_run_detail_shows_its_log_entries(): void
    {
        $site = Site::factory()->create();
        $run = CronRun::factory()->create(['site_id' => $site->id]);
        CronLogEntry::factory()->create([
            'cron_run_id' => $run->id,
            'site_id' => $site->id,
            'level' => LogLevel::Error,
            'message' => 'Something exploded while running events',
        ]);

        $this->actingAs($this->admin())
            ->get('/runs/'.$run->id)
            ->assertOk()
            ->assertSee('Something exploded while running events');
    }

    public function test_runs_can_be_exported_as_csv(): void
    {
        $site = Site::factory()->create(['name' => 'Acme Blog']);
        CronRun::factory()->create(['site_id' => $site->id]);

        $response = $this->actingAs($this->admin())->get('/runs?export=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_logs_can_be_filtered_by_level_and_searched(): void
    {
        $site = Site::factory()->create();
        $run = CronRun::factory()->create(['site_id' => $site->id]);
        CronLogEntry::factory()->create(['cron_run_id' => $run->id, 'site_id' => $site->id, 'level' => LogLevel::Info, 'message' => 'all good here']);
        CronLogEntry::factory()->create(['cron_run_id' => $run->id, 'site_id' => $site->id, 'level' => LogLevel::Error, 'message' => 'needle failure occurred']);

        $this->actingAs($this->admin())
            ->get('/logs?level=error')
            ->assertOk()
            ->assertSee('needle failure occurred')
            ->assertDontSee('all good here');

        $this->actingAs($this->admin())
            ->get('/logs?q=needle')
            ->assertOk()
            ->assertSee('needle failure occurred')
            ->assertDontSee('all good here');
    }

    public function test_runs_index_does_not_lazy_load_relations(): void
    {
        CronRun::factory()->count(3)->create();

        Model::preventLazyLoading();

        try {
            $this->actingAs($this->admin())->get('/runs')->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }
}
