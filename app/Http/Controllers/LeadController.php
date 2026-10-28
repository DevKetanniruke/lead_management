<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeadRequest;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use App\Services\LeadAssigner;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LeadController extends Controller
{
    private const SORTABLE = ['name', 'company', 'value', 'created_at', 'next_follow_up_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Lead::class);

        $user = $request->user();
        $sort = in_array($request->query('sort'), self::SORTABLE, true) ? $request->query('sort') : 'created_at';
        $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';

        $leads = Lead::query()
            ->with(['status', 'source', 'assignee', 'creator:id,name'])
            ->visibleTo($user)
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes($request->query('search'), '%_\\').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('company', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('lead_status_id', $request->query('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('lead_source_id', $request->query('source')))
            ->when($request->filled('assignee'), fn ($q) => $q->where('assigned_to', $request->query('assignee')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->query('to')))
            ->orderBy($sort, $dir)
            ->paginate(PerPage::from($request))
            ->withQueryString();

        return view('leads.index', [
            'leads' => $leads,
            'statuses' => LeadStatus::orderBy('position')->get(),
            'sources' => LeadSource::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name', 'is_active']),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Lead::class);

        return view('leads.create', ['lead' => new Lead, ...$this->formData(new Lead)]);
    }

    public function store(LeadRequest $request, LeadAssigner $assigner): RedirectResponse
    {
        Gate::authorize('create', Lead::class);

        $data = $this->payload($request) + ['created_by' => $request->user()->id];

        // Managers/admins who leave the assignee blank get round-robin assignment.
        $data['assigned_to'] ??= $assigner->nextRep()?->id;

        $lead = Lead::create($data);
        $lead->statusHistory()->create(['user_id' => $request->user()->id, 'to_status_id' => $lead->lead_status_id]);

        if ($lead->assigned_to) {
            $assigner->notify(collect([$lead]), $lead->assignee, $request->user());
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead created.');
    }

    public function show(Lead $lead): View
    {
        Gate::authorize('view', $lead);

        $lead->load([
            'status', 'source', 'assignee', 'creator', 'activities.user', 'client',
            'statusHistory.user', 'statusHistory.fromStatus', 'statusHistory.toStatus',
        ]);

        return view('leads.show', compact('lead'));
    }

    public function edit(Lead $lead): View
    {
        Gate::authorize('update', $lead);

        return view('leads.edit', ['lead' => $lead, ...$this->formData($lead)]);
    }

    public function update(LeadRequest $request, Lead $lead, LeadAssigner $assigner): RedirectResponse
    {
        Gate::authorize('update', $lead);

        $data = $this->payload($request, $lead);
        $status = LeadStatus::findOrFail($data['lead_status_id']);
        unset($data['lead_status_id']);

        if ($lead->isConverted() && ! $status->is_won) {
            return back()->withErrors(['lead_status_id' => 'Converted leads stay in Won / Converted.'])->withInput();
        }

        // Capture before changeStatus() saves again, which would reset wasChanged().
        $lead->fill($data);
        $assigneeChanged = $lead->isDirty('assigned_to');
        $lead->save();
        $lead->changeStatus($status, $request->user());

        if ($assigneeChanged && $lead->assigned_to) {
            $assigner->notify(collect([$lead]), $lead->assignee, $request->user());
        }

        return redirect()->route('leads.show', $lead)->with('status', 'Lead updated.');
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::authorize('delete', $lead);

        $lead->delete();

        return redirect()->route('leads.index')->with('status', 'Lead deleted.');
    }

    public function bulkAssign(Request $request, LeadAssigner $assigner): RedirectResponse
    {
        Gate::authorize('assign', Lead::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:leads,id'],
            'assigned_to' => ['required', Rule::exists('users', 'id')->where('is_active', true)],
        ], ['ids.required' => 'Select at least one lead.']);

        $count = $assigner->reassign(
            Lead::whereIn('id', $data['ids'])->get(),
            User::findOrFail($data['assigned_to']),
            $request->user(),
        );

        return back()->with('status', "$count lead(s) reassigned.");
    }

    private function formData(Lead $lead): array
    {
        return [
            'statuses' => LeadStatus::orderBy('position')->get(),
            'sources'  => LeadSource::orderBy('name')->get(),
            // Active users, plus the current assignee so editing never silently unassigns.
            'users'    => User::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $lead->assigned_to))
                ->orderBy('name')->get(['id', 'name']),
            // Active clients for optional linking (already-converted or manual clients).
            'clients'  => \App\Models\Client::where('status', 'active')->orderBy('name')
                ->get(['id', 'name', 'company_name']),
        ];
    }

    /** Validated data; users who cannot assign leads keep/get themselves as assignee. */
    private function payload(LeadRequest $request, ?Lead $lead = null): array
    {
        $data = $request->validated();
        $data['value'] = $data['value'] ?? 0;

        if (! $request->user()->can('assign', Lead::class)) {
            $data['assigned_to'] = $lead ? $lead->assigned_to : $request->user()->id;
        }

        return $data;
    }
}
