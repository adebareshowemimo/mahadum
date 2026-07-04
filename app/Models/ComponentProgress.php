<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_progress_id
 * @property int $lesson_component_id
 * @property string $status
 * @property numeric|null $score
 * @property int $attempts
 * @property array<array-key, mixed>|null $data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LessonComponent $lessonComponent
 * @property-read LessonProgress $lessonProgress
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereLessonProgressId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComponentProgress whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ComponentProgress extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'data' => 'array',
    ];

    /**
     * @return BelongsTo<LessonProgress, $this>
     */
    public function lessonProgress(): BelongsTo
    {
        return $this->belongsTo(LessonProgress::class, 'lesson_progress_id');
    }

    /**
     * @return BelongsTo<LessonComponent, $this>
     */
    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }
}
