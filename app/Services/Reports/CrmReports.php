<?php

namespace App\Services\Reports;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LeadStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\UserPerformance;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Every dashboard and report figure, computed from live records with role-based visibility:
 * leads/clients/invoices/payments are always scoped with their visibleTo() rules.
 * Money sums cover the default currency only and use decimal arithmetic.
 */
class CrmReports
{
    public const AGING_BUCKETS = ['current' => 'Not yet due', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'];

    public function __construct(private UserPerformance $performance) {}

    /** @return array<string, int|float|null> */
    public function crmMetrics(User $user, DateRange $range): array
    {
        $leads = fn () => Lead::visibleTo($user);
        $created = fn () => $leads()->whereBetween('created_at', [$range->from, $range->to]);

        $newCount = $created()->count();
        $convertedOfNew = $created()->whereNotNull('client_id')->count();

        $qualified = LeadStatus::where('name', 'Qualified')->first();
        $qualifiedIds = $qualified
            ? LeadStatus::where('position', '>=', $qualified->position)->where('is_lost', false)->pluck('id')
            : collect();
        $lostIds = LeadStatus::where('is_lost', true)->pluck('id');

        return [
            'total_leads' => $leads()->count(),
            'new_leads' => $newCount,
            'qualified_leads' => $this->leadsEnteringStages($user, $qualifiedIds, $range),
            'converted_clients' => Client::visibleTo($user)->tap(fn ($q) => $this->onDates($q, 'converted_at', $range))->count(),
            // Cohort rate: of the leads created in the window, how many have become clients so far.
            'conversion_rate' => $newCount > 0 ? round($convertedOfNew / $newCount * 100, 1) : null,
            'lost_leads' => $this->leadsEnteringStages($user, $lostIds, $range, currentlyIn: true),
            'active_clients' => Client::visibleTo($user)->where('status', 'active')->count(),
        ];
    }

    /** @return array<string, string|int> */
    public function financialMetrics(User $user, DateRange $range): array
    {
        $currency = Settings::currency();
        $invoices = fn () => Invoice::visibleTo($user)->where('invoices.currency', $currency);
        $issuedInRange = fn () => $invoices()->issued()->tap(fn ($q) => $this->onDates($q, 'invoice_date', $range));

        return [
            'currency' => $currency,
            'total_invoiced' => Money::sum($issuedInRange()->pluck('grand_total')),
            'payments_received' => Money::sum(Payment::visibleTo($user)->completed()->where('currency', $currency)
                ->tap(fn ($q) => $this->onDates($q, 'payment_date', $range))->pluck('amount')),
            'outstanding' => Money::sum($invoices()->receivable()->pluck('balance_due')),
            'overdue_amount' => Money::sum($invoices()->overdue()->pluck('balance_due')),
            'overdue_count' => $invoices()->overdue()->count(),
            'paid_invoices' => $issuedInRange()->where('balance_due', '<=', 0)->count(),
            'unpaid_invoices' => $issuedInRange()->where('balance_due', '>', 0)->count(),
        ];
    }

    /** Leads created vs converted per bucket. @return array{labels: list<string>, created: list<int>, converted: list<int>} */
    public function conversionTrend(User $user, DateRange $range): array
    {
        $created = $this->dailyCounts(Lead::visibleTo($user), 'created_at', $range);
        $converted = $this->dailyCounts(Lead::visibleTo($user)->whereNotNull('client_id'), 'converted_at', $range);
        $buckets = $this->buckets($range);

        return [
            'labels' => array_column($buckets, 'label'),
            'created' => array_map(fn ($b) => $this->sumIn($created, $b), $buckets),
            'converted' => array_map(fn ($b) => $this->sumIn($converted, $b), $buckets),
        ];
    }

    /** Current pipeline snapshot. @return Collection<int, array{status: LeadStatus, count: int, value: string}> */
    public function leadsByStage(User $user): Collection
    {
        $rows = Lead::visibleTo($user)->selectRaw('lead_status_id, count(*) as total, sum(value) as value')
            ->groupBy('lead_status_id')->get()->keyBy('lead_status_id');

        return LeadStatus::orderBy('position')->get()->map(fn (LeadStatus $s) => [
            'status' => $s,
            'count' => (int) ($rows[$s->id]->total ?? 0),
            'value' => Money::round($rows[$s->id]->value ?? 0),
        ]);
    }

    /**
     * Sales performance per user in the window. Users with reports.view see the team; others only themselves.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function salesPerformance(User $viewer, DateRange $range): Collection
    {
        $users = $viewer->can('reports.view')
            ? User::where(fn ($q) => $q->where('is_active', true)->orWhereHas('assignedLeads'))->orderBy('name')->get()
            : collect([$viewer]);

        $stats = $this->performance->forUsers($users, $range->from, $range->to);
        $currency = Settings::currency();
        $from = $range->from->toDateString();
        $to = $range->to->toDateString();

        $invoiced = Invoice::issued()->where('invoices.currency', $currency)
            ->tap(fn ($q) => $this->onDates($q, 'invoice_date', $range))
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->whereIn('clients.account_manager_id', $users->pluck('id'))
            ->get(['clients.account_manager_id as manager_id', 'invoices.grand_total'])
            ->groupBy('manager_id');

        $collected = Payment::completed()->where('payments.currency', $currency)
            ->tap(fn ($q) => $this->onDates($q, 'payment_date', $range))
            ->join('clients', 'clients.id', '=', 'payments.client_id')
            ->whereIn('clients.account_manager_id', $users->pluck('id'))
            ->get(['clients.account_manager_id as manager_id', 'payments.amount'])
            ->groupBy('manager_id');

        return $users->map(fn (User $u) => $stats[$u->id] + [
            'invoiced' => Money::sum(($invoiced[$u->id] ?? collect())->pluck('grand_total')),
            'collected' => Money::sum(($collected[$u->id] ?? collect())->pluck('amount')),
        ])->values();
    }

    /** Invoiced (by invoice date) vs collected (by payment date) per month. */
    public function monthlyInvoicedVsCollected(User $user, DateRange $range): array
    {
        $currency = Settings::currency();
        $end = $range->to->startOfMonth();
        $start = $range->from->startOfMonth()->min($end->subMonths(5)); // at least 6 months for context
        $window = new DateRange('custom', $start->startOfDay(), $range->to);

        $invoiced = $this->dailySums(Invoice::visibleTo($user)->issued()->where('invoices.currency', $currency), 'invoice_date', 'grand_total', $window);
        $collected = $this->dailySums(Payment::visibleTo($user)->completed()->where('currency', $currency), 'payment_date', 'amount', $window);

        $labels = $inv = $col = [];
        for ($m = $start; $m->lte($end); $m = $m->addMonth()) {
            $bucket = ['from' => $m, 'to' => $m->endOfMonth()];
            $labels[] = $m->format('M Y');
            $inv[] = (float) $this->moneyIn($invoiced, $bucket);
            $col[] = (float) $this->moneyIn($collected, $bucket);
        }

        return ['labels' => $labels, 'invoiced' => $inv, 'collected' => $col, 'currency' => $currency];
    }

    /** Current receivables by days past due. @return array<string, array{label: string, amount: string, count: int}> */
    public function receivablesAging(User $user): array
    {
        $result = collect(self::AGING_BUCKETS)->map(fn ($label) => ['label' => $label, 'amount' => '0.00', 'count' => 0])->all();
        $today = CarbonImmutable::today();

        Invoice::visibleTo($user)->receivable()->where('invoices.currency', Settings::currency())
            ->select(['invoices.id', 'invoices.due_date', 'invoices.balance_due'])
            ->lazyById(1000, 'invoices.id', 'id')
            ->each(function (Invoice $i) use (&$result, $today) {
                $days = (int) $i->due_date->diffInDays($today, false);
                $key = match (true) {
                    $days <= 0 => 'current',
                    $days <= 30 => '1_30',
                    $days <= 60 => '31_60',
                    $days <= 90 => '61_90',
                    default => '90_plus',
                };
                $result[$key]['amount'] = Money::add($result[$key]['amount'], $i->balance_due);
                $result[$key]['count']++;
            });

        return $result;
    }

    public function recentConversions(User $user, int $limit = 6): Collection
    {
        return Client::visibleTo($user)->whereNotNull('converted_at')->with(['accountManager:id,name', 'originalLead:id,name,value'])
            ->latest('converted_at')->latest('id')->limit($limit)->get();
    }

    public function upcomingDue(User $user, int $days = 14, int $limit = 6): Collection
    {
        return Invoice::visibleTo($user)->receivable()
            ->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays($days))
            ->with('client:id,name,company_name')->orderBy('due_date')->limit($limit)->get();
    }

    public function overdueAlerts(User $user, int $limit = 6): Collection
    {
        return Invoice::visibleTo($user)->overdue()->with('client:id,name,company_name')
            ->orderBy('due_date')->limit($limit)->get();
    }

    /* ---------------- helpers ---------------- */

    /** Inclusive whole-day filter on a DATE column (portable across MySQL and SQLite). */
    private function onDates(Builder $query, string $column, DateRange $range): Builder
    {
        return $query->whereDate($column, '>=', $range->from->toDateString())->whereDate($column, '<=', $range->to->toDateString());
    }

    /** Distinct leads that moved into one of the stages during the window (optionally still there). */
    private function leadsEnteringStages(User $user, Collection $statusIds, DateRange $range, bool $currentlyIn = false): int
    {
        if ($statusIds->isEmpty()) {
            return 0;
        }

        return LeadStatusHistory::query()
            ->whereIn('to_status_id', $statusIds)
            ->whereBetween('lead_status_histories.created_at', [$range->from, $range->to])
            ->whereHas('lead', fn ($q) => $q->visibleTo($user)->when($currentlyIn, fn ($q) => $q->whereIn('lead_status_id', $statusIds)))
            ->distinct()
            ->count('lead_id');
    }

    /** @return array<string, int> Y-m-d => count */
    private function dailyCounts(Builder $query, string $column, DateRange $range): array
    {
        return $query->whereBetween($column, [$range->from, $range->to])
            ->selectRaw("DATE($column) as day, count(*) as total")
            ->groupBy('day')->pluck('total', 'day')->map(fn ($v) => (int) $v)->all();
    }

    /** @return array<string, string> Y-m-d => sum */
    private function dailySums(Builder $query, string $column, string $sumColumn, DateRange $range): array
    {
        return $this->onDates($query, $column, $range)
            ->selectRaw("DATE($column) as day, sum($sumColumn) as total")
            ->groupBy('day')->pluck('total', 'day')->map(fn ($v) => Money::round($v ?? 0))->all();
    }

    /** @return list<array{label: string, from: CarbonImmutable, to: CarbonImmutable}> */
    private function buckets(DateRange $range): array
    {
        $buckets = [];
        $cursor = $range->from->startOfDay();

        while ($cursor->lte($range->to)) {
            [$end, $label] = match ($range->bucket()) {
                'day' => [$cursor->endOfDay(), $cursor->format('M j')],
                'week' => [$cursor->addDays(6)->endOfDay()->min($range->to), $cursor->format('M j')],
                default => [$cursor->endOfMonth()->min($range->to), $cursor->format('M Y')],
            };
            $buckets[] = ['label' => $label, 'from' => $cursor, 'to' => $end];
            $cursor = $end->addSecond()->startOfDay();
        }

        return $buckets;
    }

    private function sumIn(array $daily, array $bucket): int
    {
        $total = 0;
        foreach ($daily as $day => $count) {
            if ($day >= $bucket['from']->toDateString() && $day <= $bucket['to']->toDateString()) {
                $total += $count;
            }
        }

        return $total;
    }

    private function moneyIn(array $daily, array $bucket): string
    {
        return Money::sum(array_filter($daily, fn ($day) => $day >= $bucket['from']->toDateString() && $day <= $bucket['to']->toDateString(), ARRAY_FILTER_USE_KEY));
    }
}
