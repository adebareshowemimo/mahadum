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
 * @property string $mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Flashcard> $flashcards
 * @property-read int|null $flashcards_count
 * @property-read LessonComponent $lessonComponent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExerciseDeck whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ExerciseDeck extends Model
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

    /**
     * @return HasMany<Flashcard, $this>
     */
    public function flashcards(): HasMany
    {
        return $this->hasMany(Flashcard::class);
    }
}
