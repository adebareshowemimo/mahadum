<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $language_id
 * @property string|null $result_level
 * @property array<array-key, mixed>|null $answers
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Language $language
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereAnswers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereResultLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlacementAssessment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PlacementAssessment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'answers' => 'array',
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
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }
}
