<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\LeadStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, LeadStatusSeeder::class, LeadSourceSeeder::class]);
    }

    public function test_dashboard_renders_with_sidebar_and_stats(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $lead = Lead::factory()->create(['name' => 'Dash Lead']);
        Activity::factory()->create(['lead_id' => $lead->id, 'type' => 'task', 'due_at' => now()->subDay()]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('Dash Lead')
            ->assertSee('Administration')   // admin-only sidebar section
            ->assertSee('1 overdue');
    }

    public function test_rep_dashboard_is_scoped_and_hides_admin_nav(): void
    {
        $rep = User::factory()->create()->assignRole('sales_rep');
        Lead::factory()->create(['name' => 'Other Lead']);
        Lead::factory()->create(['name' => 'Own Lead', 'assigned_to' => $rep->id]);

        $this->actingAs($rep)->get('/dashboard')
            ->assertOk()
            ->assertSee('Own Lead')
            ->assertDontSee('Other Lead')
            ->assertDontSee('Administration');
    }

    public function test_stats_endpoint_respects_period_and_scope(): void
    {
        $rep = User::factory()->create()->assignRole('sales_rep');
        Lead::factory()->create(['assigned_to' => $rep->id, 'created_at' => now()->subDays(2)]);
        Lead::factory()->create(['assigned_to' => $rep->id, 'created_at' => now()->subDays(20)]);
        Lead::factory()->create(['created_at' => now()->subDay()]); // someone else's

        $week = $this->actingAs($rep)->getJson('/dashboard/stats?period=7d')->assertOk();
        $this->assertSame('1', $week->json('cards.new.value'));
        $this->assertSame('2', $week->json('cards.total.value'));

        $month = $this->actingAs($rep)->getJson('/dashboard/stats?period=30d')->assertOk();
        $this->assertSame('2', $month->json('cards.new.value'));
    }

    public function test_stats_trend_compares_to_previous_period_and_falls_back_on_bad_period(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        Lead::factory()->count(2)->create(['created_at' => now()->subDays(3)]);
        Lead::factory()->count(1)->create(['created_at' => now()->subDays(10)]);

        $res = $this->actingAs($admin)->getJson('/dashboard/stats?period=7d');
        $this->assertSame(100, $res->json('cards.new.trend')); // 2 vs 1

        $this->actingAs($admin)->getJson('/dashboard/stats?period=bogus')
            ->assertOk()->assertJsonPath('period', '30d');
    }

    public function test_stats_requires_auth(): void
    {
        $this->getJson('/dashboard/stats')->assertUnauthorized();
    }
}
