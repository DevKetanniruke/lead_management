<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\LeadStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, LeadStatusSeeder::class, LeadSourceSeeder::class]);
    }

    private function user(string $role): User
    {
        return User::factory()->create()->assignRole($role);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'lead_status_id' => LeadStatus::first()->id,
        ], $overrides);
    }

    public function test_guests_are_redirected(): void
    {
        $this->get('/leads')->assertRedirect('/login');
    }

    public function test_rep_can_create_lead_and_is_auto_assigned(): void
    {
        $rep = $this->user('sales_rep');
        $other = $this->user('sales_rep');

        $this->actingAs($rep)->post('/leads', $this->payload(['assigned_to' => $other->id]))
            ->assertRedirect();

        $lead = Lead::firstOrFail();
        $this->assertSame($rep->id, $lead->assigned_to);
        $this->assertSame($rep->id, $lead->created_by);
    }

    public function test_manager_can_assign_leads(): void
    {
        $manager = $this->user('manager');
        $rep = $this->user('sales_rep');

        $this->actingAs($manager)->post('/leads', $this->payload(['assigned_to' => $rep->id]));

        $this->assertSame($rep->id, Lead::firstOrFail()->assigned_to);
    }

    public function test_rep_only_sees_and_accesses_own_leads(): void
    {
        $rep = $this->user('sales_rep');
        $mine = Lead::factory()->create(['assigned_to' => $rep->id, 'name' => 'Mine Lead']);
        $theirs = Lead::factory()->create(['name' => 'Theirs Lead']);

        $this->actingAs($rep)->get('/leads')
            ->assertOk()->assertSee('Mine Lead')->assertDontSee('Theirs Lead');

        $this->actingAs($rep)->get("/leads/{$mine->id}")->assertOk();
        $this->actingAs($rep)->get("/leads/{$theirs->id}")->assertForbidden();
        $this->actingAs($rep)->delete("/leads/{$theirs->id}")->assertForbidden();
    }

    public function test_manager_sees_all_leads(): void
    {
        Lead::factory()->create(['name' => 'Someone Else']);

        $this->actingAs($this->user('manager'))->get('/leads')->assertSee('Someone Else');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $admin = $this->user('admin');
        Lead::factory()->create(['email' => 'jane@example.com']);

        $this->actingAs($admin)->post('/leads', $this->payload())->assertSessionHasErrors('email');
    }

    public function test_update_keeps_own_email_and_sets_converted_when_won(): void
    {
        $admin = $this->user('admin');
        $lead = Lead::factory()->create(['email' => 'jane@example.com']);
        $won = LeadStatus::where('is_won', true)->first();

        $this->actingAs($admin)->put("/leads/{$lead->id}", $this->payload(['lead_status_id' => $won->id]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($lead->fresh()->converted_at);
    }

    public function test_search_and_status_filter(): void
    {
        $admin = $this->user('admin');
        Lead::factory()->create(['name' => 'Alpha Corp']);
        Lead::factory()->create(['name' => 'Beta Inc']);

        $this->actingAs($admin)->get('/leads?search=Alpha')
            ->assertSee('Alpha Corp')->assertDontSee('Beta Inc');
    }

    public function test_other_source_requires_and_stores_custom_text(): void
    {
        $admin = $this->user('admin');
        $other = \App\Models\LeadSource::where('name', 'Other')->first();

        $this->actingAs($admin)->post('/leads', $this->payload(['lead_source_id' => $other->id]))
            ->assertSessionHasErrors('lead_source_other');

        $this->actingAs($admin)->post('/leads', $this->payload([
            'lead_source_id' => $other->id,
            'lead_source_other' => 'Trade show',
        ]))->assertSessionHasNoErrors();

        $lead = Lead::firstOrFail();
        $this->assertSame('Trade show', $lead->lead_source_other);
        $this->assertSame('Other: Trade show', $lead->sourceLabel());
        $this->actingAs($admin)->get("/leads/{$lead->id}")->assertSee('Other: Trade show');
    }

    public function test_custom_source_text_is_cleared_when_source_is_not_other(): void
    {
        $admin = $this->user('admin');
        $other = \App\Models\LeadSource::where('name', 'Other')->first();
        $website = \App\Models\LeadSource::where('name', 'Website')->first();
        $lead = Lead::factory()->create(['lead_source_id' => $other->id, 'lead_source_other' => 'Trade show']);

        $this->actingAs($admin)->put("/leads/{$lead->id}", $this->payload([
            'email' => $lead->email,
            'lead_source_id' => $website->id,
            'lead_source_other' => 'should be dropped',
        ]))->assertSessionHasNoErrors();

        $this->assertNull($lead->fresh()->lead_source_other);
    }

    public function test_delete_is_soft(): void
    {
        $admin = $this->user('admin');
        $lead = Lead::factory()->create();

        $this->actingAs($admin)->delete("/leads/{$lead->id}")->assertRedirect('/leads');

        $this->assertSoftDeleted($lead);
    }
}
