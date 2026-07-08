<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property string|null $ad_ref
 * @property string $placement
 * @property bool $coppa_passed
 * @property Carbon|null $shown_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereAdRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereCoppaPassed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression wherePlacement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereShownAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AdImpression whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AdImpression extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'coppa_passed' => 'boolean',
        'shown_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
