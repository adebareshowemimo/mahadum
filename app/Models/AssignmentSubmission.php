<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $lesson_component_id
 * @property int|null $media_asset_id
 * @property string $parent_review_status
 * @property int $coins_locked
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $decidedBy
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read LessonComponent $lessonComponent
 * @property-read MediaAsset|null $mediaAsset
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereCoinsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereMediaAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereParentReviewStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssignmentSubmission whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    /**
     * @return BelongsTo<LessonComponent, $this>
     */
    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
