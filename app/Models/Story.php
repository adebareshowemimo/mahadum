<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property string|null $summary
 * @property string $age_band
 * @property string $format
 * @property int $price_minor
 * @property string|null $cover_path
 * @property string|null $preview_video_path
 * @property string|null $full_video_path
 * @property Carbon|null $published_at
 * @property-read Course $course
 * @property-read Collection<int, StoryPage> $pages
 * @property-read int|null $pages_count
 */
class Story extends Model
{
    public const PRICE_MINOR = 50000;

    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<StoryPage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(StoryPage::class)->orderBy('position');
    }

    /** @return HasMany<StoryPurchase, $this> */
    public function purchases(): HasMany
    {
        return $this->hasMany(StoryPurchase::class);
    }
}
