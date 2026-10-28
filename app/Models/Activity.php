<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A note, call, meeting or follow-up task on a lead or on a client. */
class Activity extends Model
{
    use HasFactory;

    public const TYPES = ['call', 'email', 'meeting', 'note', 'task'];

    protected $fillable = ['lead_id', 'client_id', 'user_id', 'type', 'body', 'due_at', 'completed_at', 'overdue_notified_at'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime', 'overdue_notified_at' => 'datetime'];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Name of the lead or client this entry belongs to, for lists and notifications. */
    public function subjectName(): string
    {
        return $this->lead?->name ?? $this->client?->displayName() ?? '—';
    }

    public function subjectUrl(): ?string
    {
        return match (true) {
            $this->lead !== null => route('leads.show', $this->lead),
            $this->client !== null => route('clients.show', $this->client).'#notes',
            default => null,
        };
    }

    /** Person responsible for following up: lead assignee, else client account manager, else the author. */
    public function owner(): ?User
    {
        return $this->lead?->assignee ?? $this->client?->accountManager ?? $this->user;
    }

    /** Entries on leads or clients the user may see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('lead', fn ($l) => $l->visibleTo($user))
            ->orWhereHas('client', fn ($c) => $c->visibleTo($user)));
    }
}
