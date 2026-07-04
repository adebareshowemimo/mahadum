<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpeakingPrompt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tone_targets' => 'array',
    ];


    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    public function targetAudioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'target_audio_asset_id');
    }
}
