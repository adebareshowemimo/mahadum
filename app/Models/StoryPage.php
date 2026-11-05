<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $story_id
 * @property int $position
 * @property string|null $title
 * @property string|null $body
 * @property string|null $image_path
 * @property-read Story $story
 */
class StoryPage extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<Story, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
