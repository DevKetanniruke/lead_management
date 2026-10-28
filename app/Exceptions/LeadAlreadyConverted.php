<?php

namespace App\Exceptions;

use App\Models\Client;
use RuntimeException;

class LeadAlreadyConverted extends RuntimeException
{
    public function __construct(public readonly ?Client $client)
    {
        parent::__construct('This lead has already been converted to a client.');
    }
}
