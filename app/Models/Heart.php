<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $current
 * @property Carbon|null $refills_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereCurrent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereRefillsAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Heart extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'refills_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
