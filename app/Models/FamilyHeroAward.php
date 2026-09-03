<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyHeroAward extends Model
{
    protected $guarded = [];

    protected $casts = ['award_date' => 'date'];

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
