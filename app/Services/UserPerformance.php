<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Sales performance figures per user, optionally limited to a date range (by lead creation / conversion date). */
class UserPerformance
{
    /** @return array<string, mixed> */
    public function forUser(User $user, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        return $this->forUsers(collect([$user]), $from, $to)->first();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return Collection<int, array<string, mixed>> keyed by user id
     */
    public function forUsers(Collection $users, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $ids = $users->pluck('id');

        $range = fn ($q, string $column) => $q
            ->when($from, fn ($q) => $q->where($column, '>=', $from))
            ->when($to, fn ($q) => $q->where($column, '<=', $to));

        $assigned = $range(Lead::whereIn('assigned_to', $ids), 'created_at')
            ->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        $open = $range(Lead::whereIn('assigned_to', $ids), 'created_at')
            ->whereHas('status', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
            ->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        $lost = $range(Lead::whereIn('assigned_to', $ids), 'updated_at')
            ->whereHas('status', fn ($q) => $q->where('is_lost', true))
            ->selectRaw('assigned_to, count(*) as total')->groupBy('assigned_to')->pluck('total', 'assigned_to');

        $won = $range(Lead::whereIn('assigned_to', $ids)->whereNotNull('converted_at'), 'converted_at')
            ->selectRaw('assigned_to, count(*) as total, sum(value) as value')->groupBy('assigned_to')->get()->keyBy('assigned_to');

        return $users->mapWithKeys(function (User $user) use ($assigned, $open, $lost, $won) {
            $total = (int) ($assigned[$user->id] ?? 0);
            $wonCount = (int) ($won[$user->id]->total ?? 0);
            $lostCount = (int) ($lost[$user->id] ?? 0);
            $closed = $wonCount + $lostCount;

            return [$user->id => [
                'user' => $user,
                'leads' => $total,
                'open' => (int) ($open[$user->id] ?? 0),
                'won' => $wonCount,
                'lost' => $lostCount,
                'won_value' => (string) ($won[$user->id]->value ?? '0'),
                'conversion_rate' => $closed > 0 ? round($wonCount / $closed * 100, 1) : null,
            ]];
        });
    }
}
