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
 * @property int|null $lesson_component_id
 * @property int|null $language_id
 * @property string $title
 * @property string|null $description
 * @property string|null $presenter_name
 * @property bool $is_cultural
 * @property string $kind
 * @property int|null $duration_seconds
 * @property int|null $source_asset_id
 * @property int|null $poster_asset_id
 * @property string $default_quality
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Caption> $captions
 * @property-read int|null $captions_count
 * @property-read Language|null $language
 * @property-read LessonComponent|null $lessonComponent
 * @property-read MediaAsset|null $posterAsset
 * @property-read Collection<int, VideoProgress> $progress
 * @property-read int|null $progress_count
 * @property-read Collection<int, VideoRendition> $renditions
 * @property-read int|null $renditions_count
 * @property-read MediaAsset|null $sourceAsset
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereDefaultQuality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereIsCultural($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereLessonComponentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video wherePosterAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video wherePresenterName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereSourceAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Video whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Video extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_cultural' => 'boolean',
    ];

    /**
     * @return BelongsTo<LessonComponent, $this>
     */
    public function lessonComponent(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'language_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function sourceAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'source_asset_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function posterAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'poster_asset_id');
    }

    /**
     * @return HasMany<VideoRendition, $this>
     */
    public function renditions(): HasMany
    {
        return $this->hasMany(VideoRendition::class);
    }

    /**
     * @return HasMany<Caption, $this>
     */
    public function captions(): HasMany
    {
        return $this->hasMany(Caption::class);
    }

    /**
     * @return HasMany<VideoProgress, $this>
     */
    public function progress(): HasMany
    {
        return $this->hasMany(VideoProgress::class);
    }
}
