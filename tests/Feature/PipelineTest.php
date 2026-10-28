<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\LeadStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, LeadStatusSeeder::class, LeadSourceSeeder::class]);
    }

    private function rep(): User
    {
        return User::factory()->create()->assignRole('sales_rep');
    }

    public function test_can_log_a_note(): void
    {
        $rep = $this->rep();
        $lead = Lead::factory()->create(['assigned_to' => $rep->id]);

        $this->actingAs($rep)->post("/leads/{$lead->id}/activities", ['type' => 'note', 'body' => 'Called back'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activities', ['lead_id' => $lead->id, 'user_id' => $rep->id, 'type' => 'note']);
    }

    public function test_task_requires_due_date(): void
    {
        $rep = $this->rep();
        $lead = Lead::factory()->create(['assigned_to' => $rep->id]);

        $this->actingAs($rep)->post("/leads/{$lead->id}/activities", ['type' => 'task', 'body' => 'Send quote'])
            ->assertSessionHasErrors('due_at');
    }

    public function test_cannot_log_activity_on_someone_elses_lead(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->rep())->post("/leads/{$lead->id}/activities", ['type' => 'note', 'body' => 'x'])
            ->assertForbidden();
    }

    public function test_follow_ups_are_grouped_and_scoped(): void
    {
        $rep = $this->rep();
        $mine = Lead::factory()->create(['assigned_to' => $rep->id, 'name' => 'My Lead']);
        $theirs = Lead::factory()->create(['name' => 'Their Lead']);

        Activity::factory()->create(['lead_id' => $mine->id, 'type' => 'task', 'body' => 'overdue-task', 'due_at' => now()->subDays(2)]);
        Activity::factory()->create(['lead_id' => $mine->id, 'type' => 'task', 'body' => 'done-task', 'due_at' => now()->subDays(2), 'completed_at' => now()]);
        Activity::factory()->create(['lead_id' => $theirs->id, 'type' => 'task', 'body' => 'foreign-task', 'due_at' => now()]);

        $this->actingAs($rep)->get('/follow-ups')
            ->assertOk()->assertSee('overdue-task')->assertDontSee('done-task')->assertDontSee('foreign-task');
    }

    public function test_completing_a_task_toggles(): void
    {
        $rep = $this->rep();
        $lead = Lead::factory()->create(['assigned_to' => $rep->id]);
        $task = Activity::factory()->create(['lead_id' => $lead->id, 'type' => 'task', 'due_at' => now()]);

        $this->actingAs($rep)->patch("/activities/{$task->id}/complete");
        $this->assertNotNull($task->fresh()->completed_at);

        $this->actingAs($rep)->patch("/activities/{$task->id}/complete");
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_pipeline_move_updates_status_and_records_history(): void
    {
        $rep = $this->rep();
        $new = LeadStatus::where('name', 'New')->first();
        $won = LeadStatus::where('is_won', true)->first();
        $lead = Lead::factory()->create(['assigned_to' => $rep->id, 'lead_status_id' => $new->id]);

        $this->actingAs($rep)->patchJson("/leads/{$lead->id}/status", ['lead_status_id' => $won->id])
            ->assertOk();

        $lead->refresh();
        $this->assertSame($won->id, $lead->lead_status_id);
        $this->assertNotNull($lead->converted_at);
        $this->assertDatabaseHas('lead_status_histories', [
            'lead_id' => $lead->id, 'user_id' => $rep->id, 'from_status_id' => $new->id, 'to_status_id' => $won->id,
        ]);
    }

    public function test_cannot_move_someone_elses_lead(): void
    {
        $lead = Lead::factory()->create();

        $this->actingAs($this->rep())
            ->patchJson("/leads/{$lead->id}/status", ['lead_status_id' => LeadStatus::first()->id])
            ->assertForbidden();
    }

    public function test_pipeline_and_lead_pages_render(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $lead = Lead::factory()->create();
        $lead->statusHistory()->create(['to_status_id' => $lead->lead_status_id]);

        $this->actingAs($admin)->get('/pipeline')->assertOk()->assertSee($lead->name);
        $this->actingAs($admin)->get("/leads/{$lead->id}")->assertOk()->assertSee('Status history');
    }

    public function test_creating_and_editing_lead_records_status_history(): void
    {
        $admin = User::where('email', 'admin@example.com')->first();
        $statuses = LeadStatus::orderBy('position')->get();

        $this->actingAs($admin)->post('/leads', ['name' => 'Hist', 'lead_status_id' => $statuses[0]->id]);
        $lead = Lead::where('name', 'Hist')->firstOrFail();
        $this->assertSame(1, $lead->statusHistory()->count());

        $this->actingAs($admin)->put("/leads/{$lead->id}", ['name' => 'Hist', 'lead_status_id' => $statuses[1]->id]);
        $this->assertSame(2, $lead->statusHistory()->count());

        // saving with the same status adds no entry
        $this->actingAs($admin)->put("/leads/{$lead->id}", ['name' => 'Hist', 'lead_status_id' => $statuses[1]->id]);
        $this->assertSame(2, $lead->statusHistory()->count());
    }
}
