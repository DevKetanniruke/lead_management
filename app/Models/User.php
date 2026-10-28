<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Notification types a user can opt out of by email (in-app notifications always arrive). */
    public const NOTIFICATION_TYPES = [
        'lead_assigned' => 'A lead is assigned to me',
        'lead_converted' => 'A lead is converted to a client',
        'invoice_issued' => 'An invoice for my client is issued',
        'invoice_due' => 'An invoice is approaching its due date',
        'invoice_overdue' => 'An invoice becomes overdue',
        'payment_recorded' => 'A payment is recorded',
        'follow_up_overdue' => 'A follow-up is overdue',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'department',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function roleName(): ?string
    {
        return $this->getRoleNames()->first();
    }

    public function roleLabel(): string
    {
        return Permissions::roleLabel($this->roleName());
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    public function photoUrl(): ?string
    {
        return $this->profile_photo_path ? route('users.photo', ['user' => $this, 'v' => $this->updated_at?->timestamp]) : null;
    }

    public function wantsMail(string $type): bool
    {
        return (bool) ($this->notification_preferences[$type] ?? true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'notification_preferences' => 'array',
        ];
    }
}
