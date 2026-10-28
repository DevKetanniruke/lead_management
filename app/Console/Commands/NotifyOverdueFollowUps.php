<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Notifications\FollowUpOverdue;
use Illuminate\Console\Command;

class NotifyOverdueFollowUps extends Command
{
    protected $signature = 'leads:notify-overdue';

    protected $description = 'Email owners about lead and client follow-up tasks that are past due (once per task)';

    public function handle(): int
    {
        $tasks = Activity::query()
            ->where('type', 'task')
            ->whereNull('completed_at')
            ->whereNull('overdue_notified_at')
            ->where('due_at', '<', now())
            ->where(fn ($q) => $q->whereHas('lead')->orWhereHas('client'))
            ->with(['lead.assignee', 'client.accountManager', 'user'])
            ->orderBy('due_at')
            ->get();

        // Notify the lead assignee / client account manager, falling back to whoever created the task.
        $byRecipient = $tasks->groupBy(fn (Activity $t) => $t->owner()?->id);

        foreach ($byRecipient as $recipientId => $group) {
            $recipient = $group->first()->owner();

            if ($recipient) {
                $recipient->notify(new FollowUpOverdue($group->values()));
            }

            Activity::whereIn('id', $group->pluck('id'))->update(['overdue_notified_at' => now()]);
        }

        $this->info("Processed {$tasks->count()} overdue task(s).");

        return self::SUCCESS;
    }
}
