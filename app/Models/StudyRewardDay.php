<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** @property int $id @property int $learner_profile_id @property int $family_id @property int $progress_seconds @property string $status @property \Illuminate\Support\Carbon|null $last_seen_at */
class StudyRewardDay extends Model
{
    protected $guarded = [];

    protected $casts = ['last_seen_at' => 'datetime', 'paid_at' => 'datetime'];
}
