<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'week_start' => 'date',
    ];


    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }
}
