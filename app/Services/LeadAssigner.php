<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LeadAssigner
{
    private const CACHE_KEY = 'lead-assigner.last-user-id';

    /** Next sales rep in round-robin order (by user id), or null when there are no reps. */
    public function nextRep(): ?User
    {
        $reps = User::role('sales_rep')->active()->orderBy('id')->get();

        if ($reps->isEmpty()) {
            return null;
        }

        $last = (int) Cache::get(self::CACHE_KEY, 0);
        $next = $reps->first(fn (User $u) => $u->id > $last) ?? $reps->first();

        Cache::forever(self::CACHE_KEY, $next->id);

        return $next;
    }

    /** Reassign leads to a user (or unassign) and notify the new assignee. Returns the number changed. */
    public function reassign(Collection $leads, ?User $to, ?User $by): int
    {
        $changed = $leads->filter(fn (Lead $lead) => $lead->assigned_to !== $to?->id);

        $changed->each(fn (Lead $lead) => $lead->update(['assigned_to' => $to?->id]));

        $this->notify($changed, $to, $by);

        return $changed->count();
    }

    /** Tell the assignee about new leads, unless they assigned them to themselves. */
    public function notify(Collection $leads, ?User $assignee, ?User $by): void
    {
        if ($assignee && $leads->isNotEmpty() && $assignee->id !== $by?->id) {
            $assignee->notify(new LeadAssigned($leads->values(), $by));
        }
    }
}
