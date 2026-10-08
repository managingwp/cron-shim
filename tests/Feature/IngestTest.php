<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RunStatus;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: Site, 1: string, 2: string}
     */
    private function makeSite(array $attributes = []): array
    {
        $token = Str::random(40);
        $secret = Str::random(48);

        $site = Site::factory()->create(array_merge([
            'ingest_token_hash' => Hash::make($token),
            'signing_secret' => $secret,
        ], $attributes));

        return [$site, $token, $secret];
    }

    /**
     * @param  array<string, mixed>  $runOverrides
     * @param  array<int, array<string, mixed>>  $logs
     * @return array<string, mixed>
     */
    private function payload(Site $site, array $runOverrides = [], array $logs = []): array
    {
        return [
            'site_uuid' => $site->uuid,
            'run' => array_merge([
                'run_uuid' => (string) Str::uuid(),
                'started_at' => now()->subSeconds(3)->toIso8601String(),
                'finished_at' => now()->toIso8601String(),
                'duration_ms' => 3000,
                'status' => 'success',
                'exit_code' => 0,
                'jobs_run' => 5,
                'jobs_failed' => 0,
                'summary' => 'ok',
            ], $runOverrides),
            'logs' => $logs,
            'meta' => ['wp_version' => '6.7', 'php_version' => '8.3', 'shim_version' => '1.0.0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function siteHeaders(Site $site, string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Shim-Site' => $site->uuid,
            'Accept' => 'application/json',
        ];
    }

    public function test_a_valid_report_is_accepted_and_persisted(): void
    {
        [$site, $token] = $this->makeSite();
        $payload = $this->payload($site, [], [
            ['level' => 'info', 'message' => 'Running due cron events'],
            ['level' => 'error', 'message' => 'A job failed'],
        ]);

        $response = $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $payload);

        $response->assertStatus(202)->assertJson([
            'status' => 'accepted',
            'run_uuid' => $payload['run']['run_uuid'],
        ]);

        $this->assertDatabaseHas('cron_runs', [
            'site_id' => $site->id,
            'run_uuid' => $payload['run']['run_uuid'],
            'status' => 'success',
            'source_ip' => '127.0.0.1',
        ]);
        $this->assertDatabaseCount('cron_log_entries', 2);

        $site->refresh();
        $this->assertNotNull($site->last_run_at);
        $this->assertSame(RunStatus::Success, $site->last_status);
        $this->assertSame('127.0.0.1', $site->last_seen_ip);
    }

    public function test_missing_credentials_are_rejected(): void
    {
        $this->postJson('/api/v1/ingest', ['site_uuid' => (string) Str::uuid()])
            ->assertStatus(401);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        [$site] = $this->makeSite();

        $this->withHeaders($this->siteHeaders($site, 'not-the-token'))
            ->postJson('/api/v1/ingest', $this->payload($site))
            ->assertStatus(401);
    }

    public function test_an_unknown_site_is_rejected(): void
    {
        [$site, $token] = $this->makeSite();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Shim-Site' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ])->postJson('/api/v1/ingest', $this->payload($site))->assertStatus(401);
    }

    public function test_a_disabled_site_is_rejected(): void
    {
        [$site, $token] = $this->makeSite(['is_active' => false]);

        $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $this->payload($site))
            ->assertStatus(403);
    }

    public function test_site_uuid_must_match_the_credentials(): void
    {
        [$site, $token] = $this->makeSite();
        $payload = $this->payload($site);
        $payload['site_uuid'] = (string) Str::uuid();

        $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $payload)
            ->assertStatus(422);
    }

    public function test_duplicate_run_uuid_is_idempotent(): void
    {
        [$site, $token] = $this->makeSite();
        $payload = $this->payload($site);

        $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $payload)
            ->assertStatus(202);

        $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('cron_runs', 1);
    }

    public function test_invalid_payload_fails_validation(): void
    {
        [$site, $token] = $this->makeSite();
        $payload = $this->payload($site, ['status' => 'not-a-status']);

        $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('run.status');
    }

    public function test_oversized_payloads_are_rejected(): void
    {
        config(['cronshim.ingest.max_body_kb' => 1]);

        [$site, $token] = $this->makeSite();
        $payload = $this->payload($site, [], [
            ['level' => 'info', 'message' => str_repeat('x', 4096)],
        ]);

        $this->withHeaders($this->siteHeaders($site, $token))
            ->postJson('/api/v1/ingest', $payload)
            ->assertStatus(413);
    }

    public function test_hmac_signature_is_verified_when_required(): void
    {
        config(['cronshim.ingest.require_signature' => true]);

        [$site, $token, $secret] = $this->makeSite();
        $payload = $this->payload($site);
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_X_SHIM_SITE' => $site->uuid,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ];

        // Missing signature is rejected.
        $this->call('POST', '/api/v1/ingest', [], [], [], $server, $json)->assertStatus(401);

        // A correct signature is accepted.
        $server['HTTP_X_SHIM_SIGNATURE'] = 'sha256='.hash_hmac('sha256', $json, $secret);

        $this->call('POST', '/api/v1/ingest', [], [], [], $server, $json)->assertStatus(202);
    }
}
