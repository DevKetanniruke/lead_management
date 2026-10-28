<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    /** Everyone has a (possibly empty) scoped client list. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $client->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('clients.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can('clients.update') && $this->view($user, $client);
    }

    /** Anyone who can see the client may log notes and follow-ups on it. */
    public function addNote(User $user, Client $client): bool
    {
        return $this->view($user, $client);
    }

    public function export(User $user): bool
    {
        return $user->can('clients.export');
    }
}
