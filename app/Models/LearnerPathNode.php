<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property int $lesson_id
 * @property string $state
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Enrollment $enrollment
 * @property-read Lesson|null $lesson
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode whereEnrollmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode whereLessonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerPathNode whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class LearnerPathNode extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'enrollment_id');
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'lesson_id');
    }
}
