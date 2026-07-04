<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreakProtection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'active_from' => 'datetime',
        'active_to' => 'datetime',
        'consumed' => 'boolean',
    ];


    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
