<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Business records a user owns: used to block unsafe deletion and to hand work over. */
class UserRecords
{
    public function __construct(private LeadAssigner $assigner) {}

    /** @return array<string, int> non-zero counts keyed by record type */
    public function counts(User $user): array
    {
        return array_filter([
            'leads' => Lead::withTrashed()->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id))->count(),
            'clients' => Client::where(fn ($q) => $q->where('account_manager_id', $user->id)
                ->orWhere('created_by', $user->id)->orWhere('converted_by', $user->id))->count(),
            'invoices' => Invoice::withTrashed()->where(fn ($q) => $q->where('created_by', $user->id)
                ->orWhere('issued_by', $user->id)->orWhere('voided_by', $user->id))->count(),
            'payments' => Payment::where(fn ($q) => $q->where('recorded_by', $user->id)->orWhere('reversed_by', $user->id))->count(),
        ]);
    }

    public function hasRecords(User $user): bool
    {
        return $this->counts($user) !== [];
    }

    /** Move assigned leads and managed clients to another user. Returns counts moved. */
    public function reassign(User $from, User $to, User $by): array
    {
        return DB::transaction(function () use ($from, $to, $by) {
            $moved = [
                'leads' => $this->assigner->reassign(Lead::where('assigned_to', $from->id)->get(), $to, $by),
                'clients' => Client::where('account_manager_id', $from->id)->update(['account_manager_id' => $to->id, 'updated_at' => now()]),
            ];

            Audit::log('user.records_reassigned', $from, "Records of {$from->name} reassigned to {$to->name}", $moved + ['to_user_id' => $to->id]);

            return $moved;
        });
    }
}
