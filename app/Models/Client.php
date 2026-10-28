<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    public const STATUSES = ['active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'];

    public const STATUS_COLORS = ['active' => 'green', 'inactive' => 'yellow', 'archived' => 'gray'];

    /** Contact/business fields editable through forms; ownership/link fields are set explicitly. */
    protected $fillable = [
        'name', 'company_name', 'email', 'phone', 'address', 'city', 'state', 'state_code',
        'postal_code', 'country', 'gstin', 'status', 'account_manager_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['converted_at' => 'date'];
    }

    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    public function originalLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id')->withTrashed();
    }

    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** All leads linked to this client (the original lead and any later repeat-business leads). */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(ClientDeal::class)->latest('closed_on')->latest('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('invoice_date')->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('payment_date')->latest('id');
    }

    /** Adds total_invoiced / total_paid / total_outstanding over issued invoices (drafts and voids excluded). */
    public function scopeWithFinancials(Builder $query): Builder
    {
        $issued = fn ($q) => $q->where('status', Invoice::ISSUED);

        return $query
            ->withSum(['invoices as total_invoiced' => $issued], 'grand_total')
            ->withSum(['invoices as total_paid' => $issued], 'amount_paid')
            ->withSum(['invoices as total_outstanding' => $issued], 'balance_due');
    }

    public function displayName(): string
    {
        return $this->company_name ?: $this->name;
    }

    public function code(): string
    {
        return 'CL-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function fullAddress(): string
    {
        return collect([$this->address, trim($this->city.' '.$this->postal_code), $this->state, $this->country])
            ->filter()->implode(', ');
    }

    /** Clients the user may see: everything with clients.view_all, otherwise ones they manage, created or converted. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('clients.view_all')) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('account_manager_id', $user->id)
            ->orWhere('created_by', $user->id)
            ->orWhere('converted_by', $user->id));
    }

    public function isVisibleTo(User $user): bool
    {
        return $user->can('clients.view_all')
            || in_array($user->id, [$this->account_manager_id, $this->created_by, $this->converted_by], true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! filled($term)) {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn ($q) => $q->where('name', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhere('gstin', 'like', $like));
    }
}
