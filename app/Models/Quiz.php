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
 * @property int $lesson_component_id
 * @property string|null $title
 * @property numeric $pass_threshold
 * @property bool $shuffle_questions
 * @property int|null $max_attempts
 * @property bool $hearts_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, QuizAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read LessonComponent $lessonComponent
 * @property-read Collection<int, Question> $questions
 * @property-read int|null $questions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereHeartsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereMaxAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz wherePassThreshold($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereShuffleQuestions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Quiz whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Quiz extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'pass_threshold' => 'decimal:2',
        'shuffle_questions' => 'boolean',
        'hearts_enabled' => 'boolean',
    ];

    /**
     * @return BelongsTo<LessonComponent, $this>
     */
    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
