<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $video_id
 * @property int $watched_seconds
 * @property int $last_position_seconds
 * @property int $percent
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read Video $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereLastPositionSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress wherePercent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoProgress whereWatchedSeconds($value)
 *
 * @mixin \Eloquent
 */
class VideoProgress extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
