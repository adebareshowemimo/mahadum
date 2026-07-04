<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property string $type
 * @property string $source
 * @property Carbon|null $active_from
 * @property Carbon|null $active_to
 * @property bool $consumed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereActiveFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereActiveTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereConsumed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StreakProtection whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class StreakProtection extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'active_from' => 'datetime',
        'active_to' => 'datetime',
        'consumed' => 'boolean',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
