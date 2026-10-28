<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientDeal;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Notes, follow-ups and deals recorded directly on a client. */
class ClientActivityController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('addNote', $client);

        $data = $request->validate([
            'type' => ['required', Rule::in(Activity::TYPES)],
            'body' => ['required', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date', Rule::requiredIf($request->input('type') === 'task')],
        ]);

        if ($data['type'] !== 'task') {
            $data['due_at'] = null;
        }

        $client->activities()->create($data + ['user_id' => $request->user()->id]);

        return redirect()->to(route('clients.show', $client).'#notes')->with('status', 'Activity logged.');
    }

    public function storeDeal(Request $request, Client $client): RedirectResponse
    {
        Gate::authorize('update', $client);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'closed_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $deal = $client->deals()->create($data + ['value' => $data['value'] ?? 0, 'created_by' => $request->user()->id]);
        Audit::log('client.deal_added', $client, "Deal \"{$deal->title}\" added to {$client->displayName()}", ['value' => (string) $deal->value]);

        return redirect()->to(route('clients.show', $client).'#deals')->with('status', 'Deal added.');
    }

    public function destroyDeal(Client $client, ClientDeal $deal): RedirectResponse
    {
        Gate::authorize('update', $client);
        abort_unless($deal->client_id === $client->id, 404);

        Audit::log('client.deal_removed', $client, "Deal \"{$deal->title}\" removed from {$client->displayName()}", ['value' => (string) $deal->value]);
        $deal->delete();

        return redirect()->to(route('clients.show', $client).'#deals')->with('status', 'Deal removed.');
    }
}
