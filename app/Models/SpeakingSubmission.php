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
 * @property int|null $audio_asset_id
 * @property numeric|null $ai_score
 * @property numeric|null $tone_accuracy
 * @property string $status
 * @property bool $reviewed_by_parent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaAsset|null $audioAsset
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read LessonComponent $lessonComponent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereAiScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereAudioAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereReviewedByParent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereToneAccuracy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingSubmission whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SpeakingSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'ai_score' => 'decimal:2',
        'tone_accuracy' => 'decimal:2',
        'reviewed_by_parent' => 'boolean',
    ];

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
    public function audioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'audio_asset_id');
    }
}
