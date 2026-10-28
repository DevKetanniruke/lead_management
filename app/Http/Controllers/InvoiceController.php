<?php

namespace App\Http\Controllers;

use App\Exceptions\InvoiceStateException;
use App\Http\Requests\InvoiceRequest;
use App\Jobs\SendInvoiceEmail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Services\Invoicing\InvoicePdf;
use App\Services\Invoicing\InvoiceService;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Money;
use App\Support\PerPage;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    private const SORTABLE = ['number', 'invoice_date', 'due_date', 'grand_total', 'balance_due', 'created_at'];

    public function __construct(private InvoiceService $invoices) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invoice::class);

        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
        $user = $request->user();
        $currency = Settings::currency();

        $invoices = $this->filtered($request)
            ->with('client:id,name,company_name')
            ->orderBy($sort, $dir)->orderByDesc('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        $visible = fn () => Invoice::visibleTo($user)->where('currency', $currency);

        return view('invoices.index', [
            'invoices' => $invoices,
            'sort' => $sort,
            'dir' => $dir,
            'clients' => Client::visibleTo($user)->orderBy('name')->get(['id', 'name', 'company_name']),
            'summary' => [
                'outstanding' => $visible()->receivable()->sum('balance_due'),
                'overdue' => $visible()->overdue()->sum('balance_due'),
                'overdue_count' => $visible()->overdue()->count(),
                'drafts' => Invoice::visibleTo($user)->where('status', Invoice::DRAFT)->count(),
            ],
            'currency' => $currency,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Invoice::class);
        abort_unless($request->user()->canAny(['invoices.view_all', 'reports.view', 'clients.export']), 403);

        $query = $this->filtered($request)->with('client:id,name,company_name,gstin')->orderBy('invoice_date')->orderBy('id');
        Audit::log('invoice.exported', null, 'Invoice list exported to CSV', ['filters' => $request->only('search', 'status', 'client', 'from', 'to')]);

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $i) {
                yield [
                    $i->number, $i->client?->displayName(), $i->client?->gstin, $i->invoice_date->toDateString(), $i->due_date->toDateString(),
                    $i->displayStatusLabel(), $i->currency, $i->subtotal, $i->discount_total, $i->taxable_total,
                    $i->cgst_total, $i->sgst_total, $i->igst_total, $i->tax_total, $i->grand_total, $i->amount_paid, $i->balance_due,
                ];
            }
        })();

        return Csv::download('invoices-'.now()->format('Ymd-His').'.csv', [
            'Invoice number', 'Client', 'Client GSTIN', 'Invoice date', 'Due date', 'Status', 'Currency', 'Subtotal', 'Discount',
            'Taxable', 'CGST', 'SGST', 'IGST', 'Tax total', 'Grand total', 'Amount paid', 'Balance due',
        ], $rows);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invoice::class);
        $user = $request->user();

        $lead = $request->filled('lead') ? Lead::with('client', 'client.deals')->find($request->query('lead')) : null;
        if ($lead) {
            Gate::authorize('view', $lead);
            abort_unless($lead->client, 422, 'Convert the lead to a client before invoicing.');
        }

        $client = $lead?->client ?? ($request->filled('client') ? Client::find($request->query('client')) : null);
        if ($client) {
            Gate::authorize('view', $client);
        }

        // Prefill from the deal recorded at conversion when invoicing a converted lead.
        $deal = $lead ? $client->deals->firstWhere('lead_id', $lead->id) : null;
        $items = $deal
            ? [['description' => $deal->title, 'hsn_sac' => '', 'quantity' => '1', 'unit_price' => (string) $deal->value, 'discount' => '0', 'tax_rate' => (string) Settings::get('default_tax_rate')]]
            : [];

        $terms = (int) Settings::get('payment_terms_days', 15);
        $invoice = new Invoice([
            'client_id' => $client?->id,
            'lead_id' => $lead?->id,
            'invoice_date' => today(),
            'due_date' => today()->addDays($terms),
            'currency' => Settings::currency(),
            'place_of_supply' => $client?->state_code,
            'payment_terms' => Settings::get('payment_terms_text'),
            'terms' => Settings::get('invoice_terms'),
        ]);

        return view('invoices.create', ['invoice' => $invoice, 'items' => $items, ...$this->formData($user)]);
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        Gate::authorize('create', Invoice::class);
        [$data, $items] = $request->invoiceData();

        $client = Client::findOrFail($data['client_id']);
        Gate::authorize('view', $client);
        if (! empty($data['lead_id'])) {
            $lead = Lead::findOrFail($data['lead_id']);
            abort_unless($lead->client_id === $client->id, 422, 'The lead does not belong to this client.');
        }

        $invoice = $this->invoices->createDraft($data, $items, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('status', "Draft invoice {$invoice->number} created. Review it, then issue it when ready.");
    }

    public function show(Invoice $invoice, InvoicePdf $pdf): View
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['client.accountManager', 'lead', 'creator', 'issuer', 'voider', 'duplicatedFrom', 'amends', 'amendments']);

        return view('invoices.show', $pdf->data($invoice) + [
            'history' => \App\Models\AuditLog::where('subject_type', $invoice->getMorphClass())->where('subject_id', $invoice->id)
                ->with('user:id,name')->latest('id')->get(),
            'paymentMethods' => Settings::get('payment_methods', []),
        ]);
    }

    public function edit(Invoice $invoice): View|RedirectResponse
    {
        if (! $invoice->isDraft()) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Issued invoices cannot be edited. Use "Void & reissue" to correct them.');
        }
        Gate::authorize('update', $invoice);

        $invoice->load('items');
        $items = $invoice->items->map(fn ($i) => [
            'description' => $i->description, 'hsn_sac' => $i->hsn_sac, 'quantity' => $i->displayQuantity(),
            'unit_price' => (string) $i->unit_price, 'discount' => (string) $i->discount, 'tax_rate' => (string) (float) $i->tax_rate,
        ])->all();

        return view('invoices.edit', ['invoice' => $invoice, 'items' => $items, ...$this->formData(request()->user(), $invoice)]);
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);
        [$data, $items] = $request->invoiceData();
        Gate::authorize('view', Client::findOrFail($data['client_id']));

        return $this->attempt(function () use ($invoice, $data, $items, $request) {
            $this->invoices->updateDraft($invoice, $data, $items, $request->user());

            return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice updated.');
        }, $invoice);
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        return $this->attempt(function () use ($invoice, $request) {
            $this->invoices->deleteDraft($invoice, $request->user());

            return redirect()->route('invoices.index')->with('status', "Draft {$invoice->number} deleted. Its number will not be reused.");
        }, $invoice);
    }

    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('issue', $invoice);

        return $this->attempt(function () use ($invoice, $request) {
            $this->invoices->issue($invoice, $request->user());

            return redirect()->route('invoices.show', $invoice)->with('status', "Invoice {$invoice->number} issued. Its financial details are now locked.");
        }, $invoice);
    }

    public function void(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('void', $invoice);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Give a reason for voiding.']);

        return $this->attempt(function () use ($invoice, $data, $request) {
            $this->invoices->void($invoice, $data['reason'], $request->user());

            return redirect()->route('invoices.show', $invoice)->with('status', "Invoice {$invoice->number} has been voided.");
        }, $invoice);
    }

    public function reissue(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('reissue', $invoice);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Give a reason for the correction.']);

        return $this->attempt(function () use ($invoice, $data, $request) {
            $draft = $this->invoices->voidAndReissue($invoice, $data['reason'], $request->user());

            return redirect()->route('invoices.edit', $draft)->with('status', "{$invoice->number} was voided. Correct the new draft {$draft->number} and issue it.");
        }, $invoice);
    }

    public function duplicate(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('duplicate', $invoice);

        $copy = $this->invoices->duplicate($invoice, $request->user());

        return redirect()->route('invoices.edit', $copy)->with('status', "Copied to new draft {$copy->number}.");
    }

    public function pdf(Request $request, Invoice $invoice, InvoicePdf $pdf): Response
    {
        Gate::authorize('view', $invoice);

        $document = $pdf->make($invoice);

        return $request->boolean('inline') ? $document->stream($pdf->filename($invoice)) : $document->download($pdf->filename($invoice));
    }

    public function print(Invoice $invoice, InvoicePdf $pdf): View
    {
        Gate::authorize('view', $invoice);

        return view('invoices.print', $pdf->data($invoice));
    }

    public function send(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('send', $invoice);

        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'message' => ['nullable', 'string', 'max:5000'],
        ]);

        $cc = collect(preg_split('/[\s,;]+/', (string) ($data['cc'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))->unique()->values();
        if ($bad = $cc->first(fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
            return back()->withErrors(['cc' => "\"$bad\" is not a valid email address."])->withInput();
        }

        // Sending happens outside any financial transaction; a failure leaves the invoice untouched.
        try {
            SendInvoiceEmail::dispatch($invoice, $data['to'], $cc->all(), $data['message'] ?? null, $request->user());
        } catch (\Throwable $e) {
            report($e);
            Audit::log('invoice.email_failed', $invoice, "Emailing invoice {$invoice->number} to {$data['to']} failed", ['error' => mb_substr($e->getMessage(), 0, 300)]);

            return back()->with('error', 'The email could not be sent right now. The invoice is unchanged; please try again later.');
        }

        Audit::log('invoice.sent', $invoice, "Invoice {$invoice->number} emailed to {$data['to']}", ['to' => $data['to'], 'cc' => $cc->all()]);

        return back()->with('status', "Invoice {$invoice->number} is being emailed to {$data['to']}.");
    }

    /** Run a state-changing action, turning business-rule violations into a friendly message. */
    private function attempt(callable $action, Invoice $invoice): RedirectResponse
    {
        try {
            return $action();
        } catch (InvoiceStateException $e) {
            return redirect()->route('invoices.show', $invoice)->with('error', $e->getMessage());
        }
    }

    private function filtered(Request $request): Builder
    {
        return Invoice::query()
            ->visibleTo($request->user())
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes($request->query('search'), '%_\\').'%';
                $q->where(fn ($q) => $q->where('number', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $term)->orWhere('company_name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->withDisplayStatus($request->query('status')))
            ->when($request->filled('client'), fn ($q) => $q->where('client_id', $request->query('client')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('invoice_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('invoice_date', '<=', $request->query('to')));
    }

    private function formData($user, ?Invoice $invoice = null): array
    {
        return [
            'clients' => Client::visibleTo($user)
                ->where(fn ($q) => $q->where('status', '!=', 'archived')->orWhere('id', $invoice?->client_id))
                ->orderBy('company_name')->orderBy('name')
                ->get(['id', 'name', 'company_name', 'state_code', 'state']),
            'taxRates' => Settings::get('tax_rates', ['0']),
            'defaultTaxRate' => (string) Settings::get('default_tax_rate', '0'),
            'taxSystem' => Settings::get('tax_system', 'gst'),
            'sellerStateCode' => Settings::get('company_state_code'),
            'currencies' => Money::CURRENCIES,
        ];
    }
}
