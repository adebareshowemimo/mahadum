<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_component_id
 * @property string $prompt_text
 * @property string|null $target_text
 * @property int|null $target_audio_asset_id
 * @property array<array-key, mixed>|null $tone_targets
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LessonComponent $lessonComponent
 * @property-read MediaAsset|null $targetAudioAsset
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt wherePromptText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereTargetAudioAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereTargetText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereToneTargets($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SpeakingPrompt whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SpeakingPrompt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tone_targets' => 'array',
    ];

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
    public function targetAudioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'target_audio_asset_id');
    }
}
