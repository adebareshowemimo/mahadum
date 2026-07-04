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
 * @property int $course_id
 * @property string $title
 * @property int $position
 * @property bool $has_assessment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course|null $course
 * @property-read Collection<int, Lesson> $lessons
 * @property-read int|null $lessons_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel whereHasAssessment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseLevel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CourseLevel extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'has_assessment' => 'boolean',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
