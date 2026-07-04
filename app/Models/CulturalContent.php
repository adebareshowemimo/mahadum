<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $language_id
 * @property string $kind
 * @property string $title
 * @property string|null $body
 * @property int|null $media_asset_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Language $language
 * @property-read MediaAsset|null $mediaAsset
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereMediaAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CulturalContent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CulturalContent extends Model
{
    use HasFactory;

    protected $guarded = [];

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
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
