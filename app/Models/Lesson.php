<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_level_id
 * @property string $title
 * @property int $position
 * @property int $est_minutes
 * @property bool $is_locked_by_default
 * @property int $version
 * @property Carbon|null $published_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LessonComponent> $components
 * @property-read int|null $components_count
 * @property-read CourseLevel $courseLevel
 * @property-read CourseLevel $level
 * @property-read Collection<int, LessonProgress> $progress
 * @property-read int|null $progress_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereCourseLevelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereEstMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereIsLockedByDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lesson withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Lesson extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_locked_by_default' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CourseLevel, $this>
     */
    public function courseLevel(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    /**
     * @return BelongsTo<CourseLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(CourseLevel::class, 'course_level_id');
    }

    /**
     * @return HasMany<LessonComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(LessonComponent::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}
