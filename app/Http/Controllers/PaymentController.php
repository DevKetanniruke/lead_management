<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceStateException;
use App\Http\Requests\PaymentRequest;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Invoicing\PaymentService;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\PerPage;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payment::class);
        $currency = Settings::currency();

        $payments = $this->filtered($request)
            ->with(['client:id,name,company_name', 'allocations.invoice:id,number', 'recorder:id,name'])
            ->latest('payment_date')->latest('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'received' => $this->filtered($request)->completed()->where('currency', $currency)->sum('amount'),
            'currency' => $currency,
            'methods' => Settings::get('payment_methods', []),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Payment::class);
        abort_unless($request->user()->canAny(['invoices.view_all', 'reports.view']), 403);

        $query = $this->filtered($request)->with(['client:id,name,company_name', 'allocations.invoice:id,number', 'recorder:id,name'])->orderBy('payment_date')->orderBy('id');
        Audit::log('payment.exported', null, 'Payment list exported to CSV', ['filters' => $request->only('search', 'method', 'status', 'from', 'to')]);

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $p) {
                yield [
                    $p->receiptNumber(), $p->payment_date->toDateString(), $p->client?->displayName(),
                    $p->allocations->map(fn ($a) => $a->invoice?->number)->implode(' '), $p->currency, $p->amount,
                    $p->method, $p->reference, ucfirst($p->status), $p->reversal_reason, $p->recorder?->name,
                ];
            }
        })();

        return Csv::download('payments-'.now()->format('Ymd-His').'.csv', [
            'Receipt', 'Payment date', 'Client', 'Invoice(s)', 'Currency', 'Amount', 'Method', 'Reference', 'Status', 'Reversal reason', 'Recorded by',
        ], $rows);
    }

    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Payment::class);
        $user = $request->user();

        $invoice = $request->filled('invoice') ? Invoice::with('client')->findOrFail($request->query('invoice')) : null;
        if ($invoice) {
            if (! $user->can('recordPayment', $invoice)) {
                return redirect()->route('invoices.show', $invoice)->with('error', $invoice->isReceivable()
                    ? 'You are not allowed to record payments for this invoice.'
                    : 'This invoice has no outstanding balance to pay.');
            }
        }

        return view('payments.create', [
            'invoice' => $invoice,
            'receivables' => $invoice ? collect() : Invoice::visibleTo($user)->receivable()->with('client:id,name,company_name')
                ->orderBy('due_date')->limit(500)->get(['id', 'number', 'client_id', 'balance_due', 'currency', 'due_date']),
            'methods' => Settings::get('payment_methods', []),
        ]);
    }

    public function store(PaymentRequest $request): RedirectResponse
    {
        Gate::authorize('create', Payment::class);
        $request->validate(['invoice_id' => ['required', 'integer', 'exists:invoices,id']], ['invoice_id.required' => 'Choose the invoice this payment is for.']);

        $invoice = Invoice::findOrFail($request->input('invoice_id'));
        Gate::authorize('view', $invoice);

        try {
            $payment = $this->payments->record($invoice, $request->validated(), $request->user());
        } catch (InvoiceStateException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        $invoice->refresh();

        return redirect()->route('invoices.show', $invoice)->with('status', 'Payment '.$payment->receiptNumber().' of '.money($payment->amount, $payment->currency).' recorded. '
            .($invoice->displayStatus() === 'paid' ? 'The invoice is now fully paid.' : 'Remaining balance: '.money($invoice->balance_due, $invoice->currency).'.'));
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['client', 'allocations.invoice', 'recorder', 'reverser', 'corrects', 'correctedBy']);

        return view('payments.show', [
            'payment' => $payment,
            'methods' => Settings::get('payment_methods', []),
            'history' => AuditLog::where('subject_type', $payment->getMorphClass())->where('subject_id', $payment->id)->with('user:id,name')->latest('id')->get(),
        ]);
    }

    public function reverse(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('reverse', $payment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Give a reason for the reversal.']);

        try {
            $this->payments->reverse($payment, $data['reason'], $request->user());
        } catch (InvoiceStateException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('payments.show', $payment)->with('status', "Payment {$payment->receiptNumber()} reversed. The invoice balance has been restored.");
    }

    public function correct(PaymentRequest $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('correct', $payment);
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Give a reason for the correction.']);

        try {
            $new = $this->payments->correct($payment, $request->validated(), $request->input('reason'), $request->user());
        } catch (InvoiceStateException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect()->route('payments.show', $new)->with('status', "Payment corrected: {$payment->receiptNumber()} was reversed and {$new->receiptNumber()} recorded.");
    }

    private function filtered(Request $request): Builder
    {
        return Payment::query()
            ->visibleTo($request->user())
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes($request->query('search'), '%_\\').'%';
                $q->where(fn ($q) => $q->where('reference', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)->orWhere('company_name', 'like', $term))
                    ->orWhereHas('allocations.invoice', fn ($i) => $i->where('number', 'like', $term)));
            })
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->query('method')))
            ->when(in_array($request->query('status'), [Payment::COMPLETED, Payment::REVERSED], true), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->query('to')));
    }
}
