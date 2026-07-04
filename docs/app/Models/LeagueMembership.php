<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueMembership extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
