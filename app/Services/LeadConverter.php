<?php

namespace App\Services;

use App\Exceptions\LeadAlreadyConverted;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use App\Notifications\LeadConverted;
use App\Support\Audit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Converts a lead into a new client, or links it to an existing client, exactly once.
 *
 * The lead row is locked for the duration of the transaction and clients.lead_id is unique,
 * so double submissions or concurrent requests can never produce two clients.
 */
class LeadConverter
{
    /**
     * @param  array<string, mixed>  $data  validated client fields + service, deal_value, converted_at, notes
     *
     * @throws LeadAlreadyConverted
     */
    public function convert(Lead $lead, array $data, User $by, ?Client $linkTo = null): Client
    {
        $client = DB::transaction(function () use ($lead, $data, $by, $linkTo) {
            /** @var Lead $locked */
            $locked = Lead::whereKey($lead->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->client_id) {
                throw new LeadAlreadyConverted($locked->client);
            }

            $convertedAt = $data['converted_at'];

            if ($linkTo) {
                $client = Client::whereKey($linkTo->getKey())->lockForUpdate()->firstOrFail();
                if ($client->status !== 'active') {
                    $client->status = 'active';
                    $client->save();
                }
            } else {
                $client = new Client(collect($data)->only((new Client)->getFillable())->all());
                $client->status = 'active';
                $client->forceFill([
                    'lead_id' => $locked->id,
                    'converted_at' => $convertedAt,
                    'converted_by' => $by->id,
                    'created_by' => $by->id,
                ])->save();
            }

            $client->deals()->create([
                'lead_id' => $locked->id,
                'title' => $data['service'],
                'value' => $data['deal_value'] ?? 0,
                'closed_on' => $convertedAt,
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]);

            $locked->client_id = $client->id;
            if ($won = LeadStatus::won()) {
                $locked->changeStatus($won, $by);
            }
            $locked->forceFill(['converted_at' => $convertedAt])->save();

            $verb = $linkTo ? 'linked to existing client' : 'converted to client';
            Activity::create([
                'lead_id' => $locked->id,
                'user_id' => $by->id,
                'type' => 'note',
                'body' => ucfirst($verb)." {$client->displayName()} ({$client->code()}). Service: {$data['service']}.",
            ]);

            Audit::log($linkTo ? 'lead.linked_to_client' : 'lead.converted', $locked, "Lead \"{$locked->name}\" $verb {$client->displayName()}", [
                'client_id' => $client->id,
                'deal_value' => (string) ($data['deal_value'] ?? '0'),
                'converted_at' => (string) $convertedAt,
            ]);

            return $client;
        });

        $this->notify($lead->fresh(['assignee']), $client->load('accountManager'), $by);

        return $client;
    }

    /** Existing clients that look like this lead (same email, GSTIN, phone or company). */
    public function possibleMatches(Lead $lead, array $input = []): Collection
    {
        $email = $input['email'] ?? $lead->email;
        $phone = $input['phone'] ?? $lead->phone;
        $company = $input['company_name'] ?? $lead->company;
        $gstin = $input['gstin'] ?? null;

        if (! $email && ! $phone && ! $company && ! $gstin) {
            return collect();
        }

        return Client::query()
            ->where(function ($q) use ($email, $phone, $company, $gstin) {
                $q->when($email, fn ($q) => $q->orWhere('email', $email))
                    ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
                    ->when($company, fn ($q) => $q->orWhere('company_name', $company))
                    ->when($gstin, fn ($q) => $q->orWhere('gstin', $gstin));
            })
            ->with('accountManager:id,name')
            ->limit(10)
            ->get();
    }

    private function notify(Lead $lead, Client $client, User $by): void
    {
        collect([$client->accountManager, $lead->assignee])
            ->filter()
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $by->id || ! $u->is_active)
            ->each(function (User $u) use ($lead, $client, $by) {
                try {
                    $u->notify(new LeadConverted($lead, $client, $by));
                } catch (\Throwable $e) {
                    report($e); // a notification problem must never undo a completed conversion
                }
            });
    }
}
