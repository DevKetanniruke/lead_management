<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->canManageAll($user) || $this->owns($user, $lead);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Lead $lead): bool
    {
        return $this->view($user, $lead);
    }

    /** Converted leads are kept as the client's history and cannot be deleted. */
    public function delete(User $user, Lead $lead): Response
    {
        if (! $this->view($user, $lead)) {
            return Response::deny();
        }

        return $lead->isConverted()
            ? Response::deny('Converted leads are part of the client history and cannot be deleted.')
            : Response::allow();
    }

    public function assign(User $user): bool
    {
        return $user->can('leads.assign');
    }

    /** Eligible: permitted user, lead not already converted/linked, and not marked lost. */
    public function convert(User $user, Lead $lead): Response
    {
        if (! $user->can('leads.convert') || ! $this->view($user, $lead)) {
            return Response::deny('You are not allowed to convert this lead.');
        }
        if ($lead->trashed()) {
            return Response::deny('Deleted leads cannot be converted.');
        }
        if ($lead->isConverted()) {
            return Response::deny('This lead has already been converted to a client.');
        }
        if ($lead->status?->is_lost) {
            return Response::deny('Lost leads cannot be converted. Move the lead back to an open stage first.');
        }

        return Response::allow();
    }

    private function canManageAll(User $user): bool
    {
        return $user->can('leads.view_all');
    }

    private function owns(User $user, Lead $lead): bool
    {
        return $lead->assigned_to === $user->id || $lead->created_by === $user->id;
    }
}
