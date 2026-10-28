<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\Invoicing\InvoiceService;
use App\Services\Invoicing\PaymentService;
use App\Support\Settings;
use Database\Seeders\LeadSourceSeeder;
use Database\Seeders\LeadStatusSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, LeadStatusSeeder::class, LeadSourceSeeder::class]);
    }

    public function test_lead_conversion_links_a_client_once_and_preserves_the_lead(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $lead = Lead::factory()->create([
            'name' => 'Asha Rao',
            'email' => 'asha@example.test',
            'lead_status_id' => LeadStatus::where('name', 'Qualified')->value('id'),
        ]);

        $payload = [
            'mode' => 'new',
            'name' => 'Asha Rao',
            'company_name' => 'Rao Labs',
            'email' => 'asha@example.test',
            'phone' => '9876543210',
            'account_manager_id' => $admin->id,
            'converted_at' => today()->toDateString(),
            'service' => 'Other',
            'custom_service' => 'CRM subscription',
            'deal_value' => '15000.00',
        ];

        $this->actingAs($admin)->post(route('conversions.store', $lead), $payload)
            ->assertRedirect();

        $client = Client::where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame($client->id, $lead->fresh()->client_id);
        $this->assertTrue($lead->fresh()->status->is_won);
        $this->assertDatabaseHas('client_deals', [
            'client_id' => $client->id,
            'lead_id' => $lead->id,
            'title' => 'CRM subscription',
            'value' => '15000.00',
        ]);

        $this->actingAs($admin)->post(route('conversions.store', $lead), $payload)
            ->assertRedirect(route('clients.show', $client));
        $this->assertSame(1, Client::where('lead_id', $lead->id)->count());
    }

    public function test_invoice_totals_and_payment_ledger_recalculate_on_record_and_reversal(): void
    {
        Settings::put(['tax_system' => 'simple', 'tax_label' => 'Tax']);
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $client = Client::factory()->create(['account_manager_id' => $admin->id]);

        $invoice = app(InvoiceService::class)->createDraft([
            'client_id' => $client->id,
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(15)->toDateString(),
            'currency' => 'INR',
            'payment_terms' => 'Net 15',
        ], [[
            'description' => 'Implementation',
            'quantity' => '2',
            'unit_price' => '100.00',
            'discount' => '10.00',
            'tax_rate' => '18',
        ]], $admin);

        $this->assertSame('200.00', $invoice->subtotal);
        $this->assertSame('190.00', $invoice->taxable_total);
        $this->assertSame('34.20', $invoice->tax_total);
        $this->assertSame('224.20', $invoice->grand_total);

        $invoice = app(InvoiceService::class)->issue($invoice, $admin);
        $payments = app(PaymentService::class);
        $first = $payments->record($invoice, [
            'amount' => '100.00', 'payment_date' => today()->toDateString(), 'method' => 'Bank Transfer',
        ], $admin);

        $invoice->refresh();
        $this->assertSame('100.00', $invoice->amount_paid);
        $this->assertSame('124.20', $invoice->balance_due);
        $this->assertSame('partially_paid', $invoice->displayStatus());

        $second = $payments->record($invoice, [
            'amount' => '124.20', 'payment_date' => today()->toDateString(), 'method' => 'Cash',
        ], $admin);
        $this->assertSame('paid', $invoice->fresh()->displayStatus());

        $payments->reverse($second, 'Payment entered against the wrong invoice', $admin);
        $this->assertSame('124.20', $invoice->fresh()->balance_due);
        $this->assertSame('partially_paid', $invoice->fresh()->displayStatus());
        $this->assertSame('completed', $first->fresh()->status);
    }

    public function test_sales_executive_cannot_create_an_invoice_without_permission(): void
    {
        $rep = User::factory()->create()->assignRole('sales_rep');
        $this->actingAs($rep)->get(route('invoices.create'))->assertForbidden();
    }

    public function test_admin_navigation_and_lead_client_actions_are_visible(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame('superadmin', $admin->roleName());
        $lead = Lead::factory()->create([
            'lead_status_id' => LeadStatus::where('name', 'Qualified')->value('id'),
        ]);

        $this->actingAs($admin)->get(route('leads.index'))
            ->assertOk()
            ->assertSee('User Management')
            ->assertSee('Reports')
            ->assertSee(route('conversions.create', $lead));

        $this->actingAs($admin)->get(route('clients.index'))
            ->assertOk()
            ->assertSee('New Client');
    }

    public function test_privileged_accounts_cannot_be_deleted(): void
    {
        $superadmin = User::where('email', 'admin@example.com')->firstOrFail();
        $anotherSuperadmin = User::factory()->create()->assignRole('superadmin');

        $this->actingAs($superadmin)->delete(route('users.destroy', $anotherSuperadmin))
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $anotherSuperadmin->id]);
    }

    public function test_conversion_form_offers_service_options_and_custom_other_value(): void
    {
        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $lead = Lead::factory()->create([
            'lead_status_id' => LeadStatus::where('name', 'Qualified')->value('id'),
        ]);

        $this->actingAs($admin)->get(route('conversions.create', $lead))
            ->assertOk()
            ->assertSee('<option value="Website Development">Website Development</option>', false)
            ->assertSee('<option value="Website Redesign">Website Redesign</option>', false)
            ->assertSee('<option value="CRM">CRM</option>', false)
            ->assertSee('<option value="Digital Marketing">Digital Marketing</option>', false)
            ->assertSee('<option value="SEO">SEO</option>', false)
            ->assertSee('<option value="Social Media Marketing">Social Media Marketing</option>', false)
            ->assertSee('Describe the service or product');
    }
}
