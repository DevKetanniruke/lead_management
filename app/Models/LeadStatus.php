<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadStatus extends Model
{
    use HasFactory;

    public const WON = 'Won / Converted';

    protected $fillable = ['name', 'position', 'color', 'is_won', 'is_lost'];

    protected function casts(): array
    {
        return ['is_won' => 'boolean', 'is_lost' => 'boolean'];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public static function won(): ?self
    {
        return static::where('is_won', true)->orderBy('position')->first();
    }
}
