<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Site $site, string $token): array
    {
        return [
            'site_uuid' => $site->uuid,
            'run' => [
                'run_uuid' => (string) Str::uuid(),
                'status' => 'success',
                'exit_code' => 0,
            ],
        ];
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/sites')->assertRedirect(route('login'));
    }

    public function test_the_catalog_lists_sites(): void
    {
        $site = Site::factory()->create(['name' => 'Acme Blog']);

        $this->actingAs($this->admin())
            ->get('/sites')
            ->assertOk()
            ->assertSee('Acme Blog')
            ->assertSee('Healthy');
    }

    public function test_sites_can_be_searched(): void
    {
        Site::factory()->create(['name' => 'Alpha Blog']);
        Site::factory()->create(['name' => 'Beta Shop']);

        $this->actingAs($this->admin())
            ->get('/sites?q=Alpha')
            ->assertOk()
            ->assertSee('Alpha Blog')
            ->assertDontSee('Beta Shop');
    }

    public function test_the_create_form_renders(): void
    {
        $this->actingAs($this->admin())->get('/sites/create')->assertOk()->assertSee('Connect a site');
    }

    public function test_a_site_can_be_created_with_credentials_shown_once(): void
    {
        $response = $this->actingAs($this->admin())->post('/sites', [
            'name' => 'Acme Blog',
            'url' => 'https://blog.example.com',
            'environment' => 'production',
            'timezone' => 'UTC',
            'expected_interval_minutes' => 5,
            'grace_minutes' => 10,
            'is_active' => '1',
        ]);

        $site = Site::query()->firstOrFail();

        $response->assertRedirect(route('sites.show', $site));
        $response->assertSessionHas('credentials');

        $credentials = session('credentials');
        $this->assertTrue(Hash::check($credentials['token'], $site->ingest_token_hash));
        $this->assertSame($credentials['secret'], $site->signing_secret);
    }

    public function test_creating_a_site_validates_input(): void
    {
        $this->actingAs($this->admin())
            ->post('/sites', ['name' => ''])
            ->assertSessionHasErrors(['name', 'environment', 'timezone', 'expected_interval_minutes', 'grace_minutes']);
    }

    public function test_a_site_can_be_updated(): void
    {
        $site = Site::factory()->create(['name' => 'Old name']);

        $this->actingAs($this->admin())->put("/sites/{$site->id}", [
            'name' => 'New name',
            'environment' => 'staging',
            'timezone' => 'Europe/London',
            'expected_interval_minutes' => 15,
            'grace_minutes' => 5,
            'is_active' => '1',
        ])->assertRedirect(route('sites.show', $site));

        $site->refresh();
        $this->assertSame('New name', $site->name);
        $this->assertSame(15, $site->expected_interval_minutes);
    }

    public function test_credentials_can_be_rotated(): void
    {
        $site = Site::factory()->create([
            'ingest_token_hash' => Hash::make('old-token'),
            'signing_secret' => 'old-secret',
        ]);

        $this->actingAs($this->admin())
            ->post("/sites/{$site->id}/rotate")
            ->assertRedirect(route('sites.show', $site));

        $site->refresh();
        $credentials = session('credentials');

        $this->assertTrue(Hash::check($credentials['token'], $site->ingest_token_hash));
        $this->assertFalse(Hash::check('old-token', $site->ingest_token_hash));
        $this->assertNotSame('old-secret', $site->signing_secret);
    }

    public function test_disabling_a_site_blocks_ingest(): void
    {
        $token = 'site-token';
        $site = Site::factory()->create([
            'ingest_token_hash' => Hash::make($token),
            'signing_secret' => 'secret',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())->post("/sites/{$site->id}/toggle");
        $this->assertFalse($site->refresh()->is_active);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Shim-Site' => $site->uuid,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/ingest', $this->validPayload($site, $token))->assertStatus(403);
    }

    public function test_a_site_can_be_deleted(): void
    {
        $site = Site::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/sites/{$site->id}")
            ->assertRedirect(route('sites.index'));

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }
}
