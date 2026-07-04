<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentProgress extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'score' => 'decimal:2',
        'data' => 'array',
    ];


    public function lessonProgress(): BelongsTo
    {
        return $this->belongsTo(LessonProgress::class, 'lesson_progress_id');
    }

    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }
}
