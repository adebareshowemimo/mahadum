<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'tone_marks' => 'array',
    ];


    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'quiz_id');
    }

    public function promptAudioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'prompt_audio_asset_id');
    }

    public function promptImageAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'prompt_image_asset_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuestionResponse::class);
    }
}
