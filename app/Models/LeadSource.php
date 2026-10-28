<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadSource extends Model
{
    use HasFactory;

    public const OTHER = 'Other';

    protected $fillable = ['name'];

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
