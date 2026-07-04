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
 * @property int $learner_profile_id
 * @property int $lesson_id
 * @property string $status
 * @property numeric|null $score
 * @property int $components_completed
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ComponentProgress> $componentProgress
 * @property-read int|null $component_progress_count
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read Lesson|null $lesson
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereComponentsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LessonProgress whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LessonProgress extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
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
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }

    /**
     * @return HasMany<ComponentProgress, $this>
     */
    public function componentProgress(): HasMany
    {
        return $this->hasMany(ComponentProgress::class);
    }
}
