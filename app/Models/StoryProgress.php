<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $story_id
 * @property int $position
 * @property-read Story $story
 */
class StoryProgress extends Model
{
    protected $table = 'story_progress';

    protected $guarded = [];

    /** @return BelongsTo<Story, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
