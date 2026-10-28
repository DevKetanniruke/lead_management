<?php

namespace App\Models;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'company', 'lead_source_id', 'lead_source_other', 'lead_status_id',
        'assigned_to', 'created_by', 'value', 'notes', 'next_follow_up_at', 'converted_at', 'client_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /** Source name for display; "Other" shows the custom text when provided. */
    public function sourceLabel(): ?string
    {
        $name = $this->source?->name;

        return $name === LeadSource::OTHER && $this->lead_source_other
            ? "Other: {$this->lead_source_other}"
            : $name;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isConverted(): bool
    {
        return $this->client_id !== null;
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest('created_at')->latest('id');
    }

    /** Leads the given user may see: everything with leads.view_all, otherwise own leads. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('leads.view_all')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
    }

    /** Move to a status, keeping converted_at in sync and recording history. */
    public function changeStatus(LeadStatus $to, ?User $by = null): void
    {
        $from = $this->lead_status_id;

        $this->lead_status_id = $to->id;
        $this->converted_at = $to->is_won ? ($this->converted_at ?? now()) : null;
        $this->save();

        if ($from !== $to->id) {
            $this->statusHistory()->create([
                'user_id' => $by?->id,
                'from_status_id' => $from,
                'to_status_id' => $to->id,
            ]);

            Audit::log('lead.status_changed', $this, "Lead \"{$this->name}\" moved to {$to->name}", [
                'from_status_id' => $from,
                'to_status_id' => $to->id,
            ]);
        }
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->where('assigned_to', $user->id);
    }

    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<', now());
    }
}
