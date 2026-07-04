<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_id
 * @property string $type
 * @property int $position
 * @property string|null $title
 * @property bool $is_required
 * @property int $xp_value
 * @property array<array-key, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Assignment|null $assignment
 * @property-read ExerciseDeck|null $exercise
 * @property-read Game|null $game
 * @property-read Lesson|null $lesson
 * @property-read Quiz|null $quiz
 * @property-read SpeakingPrompt|null $speakingPrompt
 * @property-read Video|null $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonComponent whereXpValue($value)
 *
 * @mixin \Eloquent
 */
class LessonComponent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_required' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /**
     * @return HasOne<Video, $this>
     */
    public function video(): HasOne
    {
        return $this->hasOne(Video::class);
    }

    /**
     * @return HasOne<Quiz, $this>
     */
    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    /**
     * @return HasOne<SpeakingPrompt, $this>
     */
    public function speakingPrompt(): HasOne
    {
        return $this->hasOne(SpeakingPrompt::class);
    }

    /**
     * @return HasOne<Assignment, $this>
     */
    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }

    /**
     * @return HasOne<ExerciseDeck, $this>
     */
    public function exercise(): HasOne
    {
        return $this->hasOne(ExerciseDeck::class);
    }

    /**
     * @return HasOne<Game, $this>
     */
    public function game(): HasOne
    {
        return $this->hasOne(Game::class);
    }
}
