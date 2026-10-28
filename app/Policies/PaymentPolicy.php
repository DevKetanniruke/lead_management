<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // scoped by Payment::visibleTo
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('invoices.view_all') || ($payment->client && $payment->client->isVisibleTo($user));
    }

    public function create(User $user): bool
    {
        return $user->can('payments.record');
    }

    public function reverse(User $user, Payment $payment): bool
    {
        return $user->can('payments.reverse') && $payment->isCompleted() && $this->view($user, $payment);
    }

    /** A correction is a reversal plus a new recorded payment. */
    public function correct(User $user, Payment $payment): bool
    {
        return $this->reverse($user, $payment) && $user->can('payments.record');
    }
}
