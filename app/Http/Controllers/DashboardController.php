<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\User;
use App\Services\Reports\CrmReports;
use App\Support\DateRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private CrmReports $reports) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $range = DateRange::fromRequest($request);

        return view('dashboard', [
            'periods' => DateRange::PERIODS,
            'initial' => $this->payload($user, $range),
            'stages' => $this->reports->leadsByStage($user),
            'aging' => $this->reports->receivablesAging($user),
            'recentLeads' => Lead::visibleTo($user)->with(['status', 'assignee'])->latest()->limit(6)->get(),
            'upcomingTasks' => $this->openTasks($user)->with(['lead', 'client'])->orderBy('due_at')->limit(5)->get(),
            'recentConversions' => $this->reports->recentConversions($user),
            'upcomingDue' => $this->reports->upcomingDue($user),
            'overdueAlerts' => $this->reports->overdueAlerts($user),
            'performance' => $user->can('reports.view')
                ? $this->reports->salesPerformance($user, $range)->sortByDesc(fn ($r) => (float) $r['won_value'])->take(5)->values()
                : null,
        ]);
    }

    /** JSON feed for the cards and period-dependent charts (period switch + auto refresh). */
    public function stats(Request $request): JsonResponse
    {
        return response()->json($this->payload($request->user(), DateRange::fromRequest($request)));
    }

    private function payload(User $user, DateRange $range): array
    {
        return [
            'period' => $range->key,
            'from' => $range->from->toDateString(),
            'to' => $range->to->toDateString(),
            'label' => $range->label(),
            'cards' => $this->cards($user, $range),
            'charts' => [
                'trend' => $this->reports->conversionTrend($user, $range),
                'monthly' => $this->reports->monthlyInvoicedVsCollected($user, $range),
            ],
            'updated_at' => now()->format('H:i:s'),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function cards(User $user, DateRange $range): array
    {
        $label = $range->label();
        $crm = $this->reports->crmMetrics($user, $range);
        $prev = $this->reports->crmMetrics($user, $range->previous());
        $fin = $this->reports->financialMetrics($user, $range);
        $cur = $fin['currency'];
        $dates = ['from' => $range->from->toDateString(), 'to' => $range->to->toDateString()];

        $tasks = $this->openTasks($user);
        $overdueTasks = (clone $tasks)->where('due_at', '<', now()->startOfDay())->count();
        $todayTasks = (clone $tasks)->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count();

        return [
            // Leads & clients
            'total' => ['value' => number_format($crm['total_leads']), 'sub' => 'All time', 'trend' => null, 'href' => route('leads.index')],
            'new' => ['value' => number_format($crm['new_leads']), 'sub' => $label, 'trend' => $this->trend($crm['new_leads'], $prev['new_leads']), 'href' => route('leads.index', $dates)],
            'qualified' => ['value' => number_format($crm['qualified_leads']), 'sub' => 'Reached Qualified+ · '.$label, 'trend' => $this->trend($crm['qualified_leads'], $prev['qualified_leads']), 'href' => route('pipeline.index')],
            'converted' => ['value' => number_format($crm['converted_clients']), 'sub' => 'New clients · '.$label, 'trend' => $this->trend($crm['converted_clients'], $prev['converted_clients']), 'href' => route('clients.index', $dates)],
            'conversion_rate' => ['value' => $crm['conversion_rate'] !== null ? $crm['conversion_rate'].'%' : '—', 'sub' => 'Of leads created · '.$label, 'trend' => null, 'href' => route('clients.index')],
            'lost' => ['value' => number_format($crm['lost_leads']), 'sub' => $label, 'trend' => null, 'href' => route('leads.index', ['status' => \App\Models\LeadStatus::where('is_lost', true)->value('id')])],
            'active_clients' => ['value' => number_format($crm['active_clients']), 'sub' => 'Current', 'trend' => null, 'href' => route('clients.index', ['status' => 'active'])],
            'followups' => ['value' => number_format($overdueTasks + $todayTasks), 'sub' => "$overdueTasks overdue · $todayTasks today", 'trend' => null, 'alert' => $overdueTasks > 0, 'href' => route('follow-ups.index')],

            // Invoicing & payments
            'invoiced' => ['value' => money($fin['total_invoiced'], $cur), 'sub' => 'Issued · '.$label, 'trend' => null, 'href' => route('invoices.index', $dates)],
            'received' => ['value' => money($fin['payments_received'], $cur), 'sub' => 'Received · '.$label, 'trend' => null, 'href' => route('payments.index', $dates + ['status' => 'completed'])],
            'outstanding' => ['value' => money($fin['outstanding'], $cur), 'sub' => 'As of today', 'trend' => null, 'href' => route('invoices.index', ['status' => 'unpaid'])],
            'overdue' => ['value' => money($fin['overdue_amount'], $cur), 'sub' => $fin['overdue_count'].' '.str('invoice')->plural($fin['overdue_count']).' past due', 'trend' => null, 'alert' => $fin['overdue_count'] > 0, 'href' => route('invoices.index', ['status' => 'overdue'])],
            'paid_invoices' => ['value' => number_format($fin['paid_invoices']), 'sub' => 'Issued in period', 'trend' => null, 'href' => route('invoices.index', $dates + ['status' => 'paid'])],
            'unpaid_invoices' => ['value' => number_format($fin['unpaid_invoices']), 'sub' => 'Issued in period', 'trend' => null, 'href' => route('invoices.index', $dates + ['status' => 'unpaid'])],
        ];
    }

    /** Percent change vs the previous period; null when there is nothing to compare against. */
    private function trend(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round(($current - $previous) / $previous * 100);
    }

    private function openTasks(User $user)
    {
        return Activity::where('type', 'task')
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->visibleTo($user);
    }
}
