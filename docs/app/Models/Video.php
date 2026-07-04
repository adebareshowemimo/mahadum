<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_cultural' => 'boolean',
    ];


    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'source_asset_id');
    }

    public function posterAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'poster_asset_id');
    }

    public function renditions(): HasMany
    {
        return $this->hasMany(VideoRendition::class);
    }

    public function captions(): HasMany
    {
        return $this->hasMany(Caption::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(VideoProgress::class);
    }
}
