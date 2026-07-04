<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $lesson_component_id
 * @property string $prompt
 * @property string $expected_media
 * @property int|null $max_duration_seconds
 * @property int $coin_reward
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LessonComponent $lessonComponent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereExpectedMedia($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereMaxDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment wherePrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Assignment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Assignment extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<LessonComponent, $this>
     */
    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }
}
