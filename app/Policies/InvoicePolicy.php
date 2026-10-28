<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list is scoped by Invoice::visibleTo
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view_all') || ($invoice->client && $invoice->client->isVisibleTo($user));
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    /** Only drafts are editable; issued invoices are amended through void-and-reissue. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.create') && $invoice->isDraft() && $this->view($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.issue') && $invoice->isDraft() && $this->view($user, $invoice);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.send') && $invoice->isIssued() && $this->view($user, $invoice);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.void') && $invoice->isIssued() && $this->view($user, $invoice);
    }

    /** Void-and-reissue needs both void and create rights. */
    public function reissue(User $user, Invoice $invoice): bool
    {
        return $this->void($user, $invoice) && $user->can('invoices.create');
    }

    public function duplicate(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.create') && $this->view($user, $invoice);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->can('payments.record') && $invoice->isReceivable() && $this->view($user, $invoice);
    }
}
