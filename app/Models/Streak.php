<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $current_count
 * @property int $longest_count
 * @property Carbon|null $last_active_date
 * @property Carbon|null $frozen_until
 * @property string $state
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereCurrentCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereFrozenUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereLastActiveDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereLongestCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Streak whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Streak extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_active_date' => 'date',
        'frozen_until' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
