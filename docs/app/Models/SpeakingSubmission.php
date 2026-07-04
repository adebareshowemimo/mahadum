<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeakingSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'ai_score' => 'decimal:2',
        'tone_accuracy' => 'decimal:2',
        'reviewed_by_parent' => 'boolean',
    ];


    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    public function audioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'audio_asset_id');
    }
}
