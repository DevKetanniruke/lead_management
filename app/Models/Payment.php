<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A received payment, allocated to one or more invoices. Written only by PaymentService. */
class Payment extends Model
{
    use HasFactory;

    public const COMPLETED = 'completed';

    public const REVERSED = 'reversed';

    /** Nothing is mass assignable: every column is set deliberately by the payment service. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')->withPivot('amount')->withTimestamps();
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_payment_id');
    }

    public function correctedBy(): HasOne
    {
        return $this->hasOne(self::class, 'corrects_payment_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::REVERSED;
    }

    public function receiptNumber(): string
    {
        return 'PAY-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('payments.status', self::COMPLETED);
    }

    /** Payments the user may see: all with invoices.view_all, otherwise those of clients they can see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('invoices.view_all')) {
            return $query;
        }

        return $query->whereHas('client', fn ($q) => $q->visibleTo($user));
    }
}
