<?php

namespace App\Http\Controllers;

use App\Exceptions\LeadAlreadyConverted;
use App\Http\Requests\ConversionRequest;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadConverter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConversionController extends Controller
{
    public function create(Lead $lead, LeadConverter $converter): View|RedirectResponse
    {
        if ($lead->isConverted() && Gate::allows('view', $lead)) {
            return redirect()->route('clients.show', $lead->client_id)->with('status', 'This lead has already been converted.');
        }
        Gate::authorize('convert', $lead);

        $lead->load(['status', 'assignee']);
        $user = request()->user();

        return view('conversions.create', [
            'lead' => $lead,
            'matches' => $converter->possibleMatches($lead)->filter(fn (Client $c) => $c->isVisibleTo($user))->values(),
            'managers' => User::active()->orderBy('name')->get(['id', 'name']),
            'canAssignManager' => $user->can('leads.assign'),
        ]);
    }

    public function store(ConversionRequest $request, Lead $lead, LeadConverter $converter): RedirectResponse
    {
        // A repeated submit for an already converted lead just lands on the client.
        if ($lead->isConverted() && Gate::allows('view', $lead)) {
            return redirect()->route('clients.show', $lead->client_id)->with('status', 'This lead was already converted; no duplicate client was created.');
        }
        Gate::authorize('convert', $lead);

        $data = $request->conversionData();
        $user = $request->user();

        // Users who cannot assign work become the account manager themselves.
        if (! $user->can('leads.assign')) {
            $data['account_manager_id'] = $user->id;
        }

        $linkTo = null;
        if ($data['mode'] === 'link') {
            $linkTo = Client::findOrFail($data['client_id']);
            if (! $linkTo->isVisibleTo($user)) {
                return back()->withErrors(['client_id' => 'You do not have access to that client.'])->withInput();
            }
        }

        try {
            $client = $converter->convert($lead, $data, $user, $linkTo);
        } catch (LeadAlreadyConverted $e) {
            return redirect()->route('clients.show', $e->client)->with('status', 'This lead was already converted; no duplicate client was created.');
        }

        return redirect()->route('clients.show', $client)->with('status', $linkTo
            ? "Lead linked to {$client->displayName()} and marked Won / Converted."
            : "{$client->displayName()} is now a client. The lead has been marked Won / Converted.");
    }
}
