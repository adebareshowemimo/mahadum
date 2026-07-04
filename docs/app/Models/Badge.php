<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(LearnerProfile::class, 'learner_badges')->withTimestamps();
    }
}
