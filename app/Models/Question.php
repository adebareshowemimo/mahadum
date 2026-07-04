<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quiz_id
 * @property string $type
 * @property string $prompt
 * @property int|null $prompt_audio_asset_id
 * @property int|null $prompt_image_asset_id
 * @property string|null $explanation
 * @property string|null $target_text
 * @property array<array-key, mixed>|null $tone_marks
 * @property int $difficulty
 * @property int $points
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, QuestionOption> $options
 * @property-read int|null $options_count
 * @property-read MediaAsset|null $promptAudioAsset
 * @property-read MediaAsset|null $promptImageAsset
 * @property-read Quiz $quiz
 * @property-read Collection<int, QuestionResponse> $responses
 * @property-read int|null $responses_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereDifficulty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereExplanation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question wherePrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question wherePromptAudioAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question wherePromptImageAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereTargetText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereToneMarks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Question whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Question extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tone_marks' => 'array',
    ];

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function promptAudioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'prompt_audio_asset_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function promptImageAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'prompt_image_asset_id');
    }

    /**
     * @return HasMany<QuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    /**
     * @return HasMany<QuestionResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(QuestionResponse::class);
    }
}
