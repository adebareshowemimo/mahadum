<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $quiz_id
 * @property int $attempt_no
 * @property numeric|null $score
 * @property bool $passed
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read Quiz $quiz
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereAttemptNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt wherePassed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereQuizId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QuizAttempt whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class QuizAttempt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'passed' => 'boolean',
        'started_at' => 'datetime',
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
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }
}
