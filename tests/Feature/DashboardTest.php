<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Incident;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Log out');
    }

    public function test_the_dashboard_renders_one_card_per_site(): void
    {
        Site::factory()->count(3)->create();

        $response = $this->actingAs(User::factory()->create())->get('/dashboard');

        $response->assertOk();
        $this->assertSame(3, substr_count($response->getContent(), 'data-testid="site-card"'));
    }

    public function test_the_dashboard_aggregates_health_counts(): void
    {
        Site::factory()->create();
        Site::factory()->failing()->create();
        Site::factory()->silent()->create();
        Site::factory()->inactive()->create();

        Livewire::test(Dashboard::class)
            ->assertViewHas('healthCounts', fn (array $counts): bool => $counts['healthy'] === 1
                && $counts['degraded'] === 1
                && $counts['silent'] === 1
                && $counts['disabled'] === 1);
    }

    public function test_the_dashboard_counts_open_incidents(): void
    {
        Incident::factory()->count(2)->create();
        Incident::factory()->resolved()->create();

        Livewire::test(Dashboard::class)
            ->assertViewHas('openIncidentCount', 2);
    }
}
