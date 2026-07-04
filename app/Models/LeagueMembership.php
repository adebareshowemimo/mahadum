<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $league_id
 * @property int $learner_profile_id
 * @property int $weekly_xp
 * @property int|null $rank
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read League $league
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereLeagueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereRank($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LeagueMembership whereWeeklyXp($value)
 *
 * @mixin \Eloquent
 */
class LeagueMembership extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<League, $this>
     */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class, 'league_id');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
