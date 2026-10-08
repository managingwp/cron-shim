<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Site;
use CronShim\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShimClientTest extends TestCase
{
    use RefreshDatabase;

    private string $spool;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('shim/cron-shim.php');

        $this->spool = sys_get_temp_dir().'/shim-test-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->spool.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->spool);

        parent::tearDown();
    }

    public function test_payload_has_the_expected_structure(): void
    {
        $client = new Client([
            'site_uuid' => (string) Str::uuid(),
            'cron_command' => 'printf "first line\nsecond line"',
            'spool_dir' => $this->spool,
        ]);

        $payload = $client->buildPayload($client->executeCron());

        $this->assertSame('success', $payload['run']['status']);
        $this->assertSame(0, $payload['run']['exit_code']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $payload['run']['run_uuid'],
        );
        $this->assertCount(2, $payload['logs']);
        $this->assertSame('info', $payload['logs'][0]['level']);
        $this->assertSame($client->config()['site_uuid'], $payload['site_uuid']);
    }

    public function test_a_failed_cron_command_is_reported_as_failed(): void
    {
        $client = new Client([
            'site_uuid' => (string) Str::uuid(),
            'cron_command' => 'echo boom; exit 3',
            'spool_dir' => $this->spool,
        ]);

        $payload = $client->buildPayload($client->executeCron());

        $this->assertSame('failed', $payload['run']['status']);
        $this->assertSame(3, $payload['run']['exit_code']);
        $this->assertSame('error', $payload['logs'][0]['level']);
    }

    public function test_failed_delivery_spools_and_flush_replays(): void
    {
        $client = new Client([
            'hub_url' => 'http://127.0.0.1:1',
            'site_uuid' => (string) Str::uuid(),
            'token' => 't',
            'secret' => 's',
            'cron_command' => 'true',
            'spool_dir' => $this->spool,
        ]);

        $payload = $client->buildPayload($client->executeCron());

        $client->transport = fn (): int => 0;
        $this->assertFalse($client->deliver($payload));
        $client->spool($payload);
        $this->assertCount(1, glob($this->spool.'/*.json') ?: []);

        $client->transport = fn (): int => 202;
        $client->flushSpool();
        $this->assertCount(0, glob($this->spool.'/*.json') ?: []);
    }

    public function test_cli_exits_zero_and_spools_when_the_hub_is_unreachable(): void
    {
        [$exit] = $this->runCli([
            'SHIM_HUB_URL' => 'http://127.0.0.1:1',
            'SHIM_SITE_UUID' => (string) Str::uuid(),
            'SHIM_TOKEN' => 't',
            'SHIM_CRON_COMMAND' => 'true',
            'SHIM_SPOOL_DIR' => $this->spool,
            'SHIM_TIMEOUT' => '1',
        ]);

        $this->assertSame(0, $exit);
        $this->assertNotEmpty(glob($this->spool.'/*.json') ?: []);
    }

    public function test_dry_run_payload_is_accepted_by_the_api(): void
    {
        $token = Str::random(40);
        $site = Site::factory()->create([
            'ingest_token_hash' => Hash::make($token),
            'signing_secret' => Str::random(48),
        ]);

        [$exit, $stdout] = $this->runCli([
            'SHIM_SITE_UUID' => $site->uuid,
            'SHIM_CRON_COMMAND' => 'printf "hello from cron"',
            'SHIM_SPOOL_DIR' => $this->spool,
        ], ['--dry-run']);

        $this->assertSame(0, $exit);

        $payload = json_decode(trim($stdout), true);
        $this->assertIsArray($payload);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Shim-Site' => $site->uuid,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/ingest', $payload)->assertStatus(202);

        $this->assertDatabaseHas('cron_runs', [
            'site_id' => $site->id,
            'run_uuid' => $payload['run']['run_uuid'],
        ]);
    }

    /**
     * @param  array<string, string>  $env
     * @param  array<int, string>  $args
     * @return array{0: int, 1: string}
     */
    private function runCli(array $env, array $args = []): array
    {
        $command = array_merge([PHP_BINARY, base_path('shim/cron-shim.php')], $args);
        $environment = array_merge(getenv(), $env);

        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, base_path(), $environment);

        $this->assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit = proc_close($process);

        return [$exit, (string) $stdout];
    }
}
