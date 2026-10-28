<?php

namespace App\Http\Controllers;

use App\Services\Reports\CrmReports;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\DateRange;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private CrmReports $reports) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $range = DateRange::fromRequest($request);

        return view('reports.index', [
            'range' => $range,
            'crm' => $this->reports->crmMetrics($user, $range),
            'finance' => $this->reports->financialMetrics($user, $range),
            'trend' => $this->reports->conversionTrend($user, $range),
            'stages' => $this->reports->leadsByStage($user),
            'performance' => $this->reports->salesPerformance($user, $range),
            'monthly' => $this->reports->monthlyInvoicedVsCollected($user, $range),
            'aging' => $this->reports->receivablesAging($user),
        ]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        $user = $request->user();
        $range = DateRange::fromRequest($request);
        $suffix = $range->from->format('Ymd').'-'.$range->to->format('Ymd');

        Audit::log('report.exported', null, "Report \"$report\" exported for {$range->label()}", $range->query());

        return match ($report) {
            'sales-performance' => Csv::download("sales-performance-$suffix.csv",
                ['User', 'Role', 'Leads assigned', 'Open', 'Won', 'Lost', 'Win rate %', 'Won deal value', 'Invoiced', 'Collected'],
                $this->reports->salesPerformance($user, $range)->map(fn ($r) => [
                    $r['user']->name, Permissions::roleLabel($r['user']->roleName()), $r['leads'], $r['open'], $r['won'], $r['lost'],
                    $r['conversion_rate'] ?? '', $r['won_value'], $r['invoiced'], $r['collected'],
                ])),
            'aging' => Csv::download('receivables-aging-'.now()->format('Ymd').'.csv',
                ['Bucket', 'Invoices', 'Outstanding'],
                collect($this->reports->receivablesAging($user))->map(fn ($b) => [$b['label'], $b['count'], $b['amount']])),
            'monthly' => Csv::download("invoiced-vs-collected-$suffix.csv",
                ['Month', 'Invoiced', 'Collected'],
                (function () use ($user, $range) {
                    $m = $this->reports->monthlyInvoicedVsCollected($user, $range);
                    foreach ($m['labels'] as $i => $label) {
                        yield [$label, number_format($m['invoiced'][$i], 2, '.', ''), number_format($m['collected'][$i], 2, '.', '')];
                    }
                })()),
            default => abort(404),
        };
    }
}
