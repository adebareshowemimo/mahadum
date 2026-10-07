<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilyChallenge extends Model
{
    protected $guarded = [];

    protected $casts = ['learner_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
}
