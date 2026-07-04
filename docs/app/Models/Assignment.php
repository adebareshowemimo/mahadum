<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    use HasFactory;

    protected $guarded = [];


    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }
}
