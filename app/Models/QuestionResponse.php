<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $question_id
 * @property int|null $quiz_attempt_id
 * @property array<array-key, mixed>|null $given_answer
 * @property bool $is_correct
 * @property int|null $time_ms
 * @property int $hearts_lost
 * @property Carbon|null $answered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read Question $question
 * @property-read QuizAttempt|null $quizAttempt
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereAnsweredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereGivenAnswer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereHeartsLost($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereIsCorrect($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereQuestionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereQuizAttemptId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereTimeMs($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuestionResponse whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class QuestionResponse extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'given_answer' => 'array',
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    /**
     * @return BelongsTo<QuizAttempt, $this>
     */
    public function quizAttempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }
}
