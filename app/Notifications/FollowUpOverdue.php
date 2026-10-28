<?php

namespace App\Notifications;

use App\Notifications\Concerns\CrmNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class FollowUpOverdue extends Notification implements ShouldQueue
{
    use CrmNotification, Queueable;

    public const PREFERENCE = 'follow_up_overdue';

    /** @param Collection<int, \App\Models\Activity> $tasks */
    public function __construct(public Collection $tasks) {}

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->tasks->count();
        $mail = (new MailMessage)
            ->subject($count === 1 ? 'Overdue follow-up' : "$count overdue follow-ups")
            ->greeting("Hi {$notifiable->name},")
            ->line('These follow-ups are past their due date:');

        foreach ($this->tasks->take(15) as $task) {
            $mail->line("• {$task->subjectName()}: {$task->body} (due {$task->due_at->format('M j, H:i')})");
        }

        return $mail->action('Open follow-ups', route('follow-ups.index'));
    }

    protected function payload(object $notifiable): array
    {
        $count = $this->tasks->count();

        return [
            'title' => $count === 1 ? 'Follow-up overdue' : "$count follow-ups overdue",
            'body' => $this->tasks->take(3)->map->subjectName()->implode(', '),
            'url' => route('follow-ups.index'),
        ];
    }
}
