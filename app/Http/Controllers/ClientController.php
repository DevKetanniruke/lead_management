<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Support\Audit;
use App\Support\Csv;
use App\Support\Money;
use App\Support\PerPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{
    private const SORTABLE = ['id', 'name', 'company_name', 'converted_at', 'created_at', 'total_invoiced', 'total_outstanding'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Client::class);

        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $clients = $this->filtered($request)
            ->with('accountManager:id,name')
            ->withFinancials()
            ->orderBy($sort, $dir)->orderByDesc('id')
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'managers' => User::orderBy('name')->get(['id', 'name']),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('export', Client::class);

        $query = $this->filtered($request)->with('accountManager:id,name')->withFinancials()->orderBy('id');
        Audit::log('client.exported', null, 'Client list exported to CSV', ['filters' => $request->only('search', 'status', 'manager', 'from', 'to')]);

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $c) {
                yield [
                    $c->code(), $c->name, $c->company_name, $c->email, $c->phone, $c->gstin,
                    $c->address, $c->city, $c->state, $c->state_code, $c->postal_code, $c->country,
                    Client::STATUSES[$c->status] ?? $c->status, $c->accountManager?->name,
                    $c->converted_at?->toDateString(), $c->lead_id, $c->created_at->toDateString(),
                    Money::round($c->total_invoiced ?? 0), Money::round($c->total_paid ?? 0), Money::round($c->total_outstanding ?? 0),
                ];
            }
        })();

        return Csv::download('clients-'.now()->format('Ymd-His').'.csv', [
            'Client ID', 'Name', 'Company', 'Email', 'Phone', 'GSTIN', 'Address', 'City', 'State', 'State code',
            'Postal code', 'Country', 'Status', 'Account manager', 'Conversion date', 'Original lead ID', 'Created',
            'Total invoiced', 'Total paid', 'Outstanding',
        ], $rows);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.create', ['client' => new Client(['country' => 'India', 'status' => 'active']), ...$this->formData()]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Gate::authorize('create', Client::class);

        $client = new Client($request->validated());
        $client->forceFill(['created_by' => $request->user()->id])->save();

        Audit::log('client.created', $client, "Client {$client->displayName()} added manually");

        return redirect()->route('clients.show', $client)->with('status', 'Client created.');
    }

    public function show(Client $client): View
    {
        Gate::authorize('view', $client);

        $client->load([
            'accountManager', 'convertedBy', 'creator', 'deals.creator', 'activities.user',
            'originalLead.status', 'originalLead.assignee', 'originalLead.source',
            'originalLead.statusHistory.user', 'originalLead.statusHistory.fromStatus', 'originalLead.statusHistory.toStatus',
            'leads.status', 'leads.assignee', 'invoices', 'payments.allocations.invoice:id,number',
        ]);

        $leadIds = $client->leads->pluck('id')->push($client->lead_id)->filter()->unique();

        return view('clients.show', [
            'client' => $client,
            'leadActivities' => Activity::whereIn('lead_id', $leadIds)->with(['user', 'lead'])->latest()->limit(100)->get(),
            'history' => AuditLog::query()
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('subject_type', $client->getMorphClass())->where('subject_id', $client->id))
                    ->orWhere(fn ($q) => $q->where('subject_type', (new Lead)->getMorphClass())->whereIn('subject_id', $leadIds)))
                ->with('user:id,name')->latest('id')->limit(100)->get(),
        ]);
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.edit', ['client' => $client, ...$this->formData($client)]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $client->fill($request->validated());
        $changes = array_keys($client->getDirty());
        $original = collect($client->getOriginal())->only($changes)->all();
        $client->save();

        if ($changes) {
            Audit::log('client.updated', $client, "Client {$client->displayName()} updated", [
                'fields' => $changes,
                'old' => collect($original)->except(['notes', 'address'])->all(),
            ]);
        }

        return redirect()->route('clients.show', $client)->with('status', 'Client updated.');
    }

    /** Quick status change (activate / deactivate / archive) from the client page. */
    public function updateStatus(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Client::STATUSES))]]);
        $from = $client->status;
        $client->update(['status' => $data['status']]);

        Audit::log('client.status_changed', $client, "Client {$client->displayName()} marked ".Client::STATUSES[$data['status']], ['from' => $from, 'to' => $data['status']]);

        return back()->with('status', 'Client marked '.strtolower(Client::STATUSES[$data['status']]).'.');
    }

    private function filtered(Request $request): Builder
    {
        return Client::query()
            ->visibleTo($request->user())
            ->search($request->query('search'))
            // Archived clients are hidden unless asked for (status=archived or status=all).
            ->when(array_key_exists((string) $request->query('status'), Client::STATUSES), fn ($q) => $q->where('status', $request->query('status')))
            ->when(! $request->filled('status'), fn ($q) => $q->where('status', '!=', 'archived'))
            ->when($request->filled('manager'), fn ($q) => $q->where('account_manager_id', $request->query('manager')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('converted_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('converted_at', '<=', $request->query('to')));
    }

    private function formData(?Client $client = null): array
    {
        return [
            'managers' => User::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $client?->account_manager_id))
                ->orderBy('name')->get(['id', 'name']),
        ];
    }
}
