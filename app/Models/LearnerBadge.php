<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $badge_id
 * @property Carbon|null $earned_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Badge $badge
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge whereBadgeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge whereEarnedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerBadge whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LearnerBadge extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'earned_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    /**
     * @return BelongsTo<Badge, $this>
     */
    public function badge(): BelongsTo
    {
        return $this->belongsTo(Badge::class, 'badge_id');
    }
}
