<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $fillable = ['series', 'next_number'];

    protected function casts(): array
    {
        return ['next_number' => 'integer'];
    }
}
