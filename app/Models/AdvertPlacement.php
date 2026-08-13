<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $position
 * @property string|null $size
 * @property int $media_asset_id
 * @property string $target_url
 * @property bool $is_active
 * @property Carbon|null $activated_at
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $impressions_count
 * @property int $clicks_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaAsset $mediaAsset
 *
 * @mixin \Eloquent
 */
class AdvertPlacement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'activated_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @param  Builder<AdvertPlacement>  $query
     * @return Builder<AdvertPlacement>
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    /**
     * @param  Builder<AdvertPlacement>  $query
     * @return Builder<AdvertPlacement>
     */
    public function scopeForPosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position);
    }

    /**
     * The advert currently shown for a position. MVP selection rule: most
     * recently activated wins — a placeholder for future rotation/weighting/
     * targeting logic once there's more than one advertiser per position.
     */
    public static function currentFor(string $position): ?self
    {
        return static::query()->active()->forPosition($position)->latest('activated_at')->first();
    }
}
