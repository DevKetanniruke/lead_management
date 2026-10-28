<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\LeadStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, LeadStatusSeeder::class, LeadSourceSeeder::class]);
        $this->admin = User::where('email', 'admin@example.com')->first();
        Lead::factory(60)->create(['assigned_to' => $this->admin->id]);
    }

    public function test_default_page_size_is_ten(): void
    {
        $res = $this->actingAs($this->admin)->get('/leads')->assertOk();

        $this->assertCount(10, $res->viewData('leads')->items());
        $res->assertSee('data-per-page', false)->assertSee('Showing')->assertSee('aria-label="Page 2"', false);
    }

    #[DataProvider('sizes')]
    public function test_allowed_page_sizes(int $size, int $expectedRows): void
    {
        $res = $this->actingAs($this->admin)->get("/leads?per_page=$size")->assertOk();

        $this->assertCount($expectedRows, $res->viewData('leads')->items());
    }

    public static function sizes(): array
    {
        return [[10, 10], [25, 25], [50, 50], [100, 60], [500, 60]];
    }

    public function test_invalid_page_size_falls_back_to_default(): void
    {
        foreach (['7', '1000', 'abc', '-5'] as $bad) {
            $res = $this->actingAs($this->admin)->get("/leads?per_page=$bad");
            $this->assertCount(10, $res->viewData('leads')->items());
        }
    }

    public function test_page_links_keep_page_size_and_filters(): void
    {
        $this->actingAs($this->admin)->get('/leads?per_page=25&sort=name&dir=asc')
            ->assertSee('per_page=25', false)
            ->assertSee('sort=name', false);
    }

    public function test_follow_up_groups_page_independently(): void
    {
        $lead = Lead::factory()->create(['assigned_to' => $this->admin->id]);
        \App\Models\Activity::factory(12)->create(['lead_id' => $lead->id, 'type' => 'task', 'due_at' => now()->subDays(3)]);
        \App\Models\Activity::factory(3)->create(['lead_id' => $lead->id, 'type' => 'task', 'due_at' => now()->addDays(3)]);

        $res = $this->actingAs($this->admin)->get('/follow-ups')->assertOk();
        $this->assertCount(10, $res->viewData('overdue')->items());
        $this->assertSame(12, $res->viewData('overdue')->total());
        $this->assertCount(3, $res->viewData('upcoming')->items());
        $res->assertSee('overdue_page=2', false);

        $page2 = $this->actingAs($this->admin)->get('/follow-ups?overdue_page=2')->assertOk();
        $this->assertCount(2, $page2->viewData('overdue')->items());
        $this->assertCount(3, $page2->viewData('upcoming')->items()); // other groups untouched

        $big = $this->actingAs($this->admin)->get('/follow-ups?per_page=25');
        $this->assertCount(12, $big->viewData('overdue')->items());
    }

    public function test_pipeline_limits_cards_per_column_and_loads_more(): void
    {
        $status = \App\Models\LeadStatus::where('name', 'New')->first();
        Lead::query()->delete();
        Lead::factory(25)->create(['assigned_to' => $this->admin->id, 'lead_status_id' => $status->id]);

        $res = $this->actingAs($this->admin)->get('/pipeline')->assertOk();
        $this->assertCount(10, $res->viewData('leads')[$status->id]);
        $this->assertEquals(25, $res->viewData('totals')[$status->id]);

        $more = $this->actingAs($this->admin)
            ->getJson("/pipeline/columns/{$status->id}?offset=10&per_page=10")->assertOk();
        $this->assertSame(10, $more->json('count'));
        $this->assertStringContainsString('data-lead=', $more->json('html'));

        $last = $this->actingAs($this->admin)
            ->getJson("/pipeline/columns/{$status->id}?offset=20&per_page=10");
        $this->assertSame(5, $last->json('count'));

        $all = $this->actingAs($this->admin)->get('/pipeline?per_page=50');
        $this->assertCount(25, $all->viewData('leads')[$status->id]);
    }

    public function test_pipeline_column_feed_is_scoped_to_visible_leads(): void
    {
        $rep = User::factory()->create()->assignRole('sales_rep');
        $status = \App\Models\LeadStatus::first();
        Lead::query()->delete();
        Lead::factory(3)->create(['assigned_to' => $this->admin->id, 'lead_status_id' => $status->id]);
        Lead::factory(2)->create(['assigned_to' => $rep->id, 'lead_status_id' => $status->id]);

        $this->actingAs($rep)->getJson("/pipeline/columns/{$status->id}")
            ->assertOk()->assertJsonPath('count', 2);
    }

    public function test_users_table_is_paginated_too(): void
    {
        User::factory(30)->create();

        $res = $this->actingAs($this->admin)->get('/users?per_page=25')->assertOk();

        $this->assertCount(25, $res->viewData('users')->items());
        $res->assertSee('id="users-region"', false);
    }
}
