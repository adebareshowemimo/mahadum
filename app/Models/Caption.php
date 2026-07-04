<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $video_id
 * @property string $language_code
 * @property string $format
 * @property string $url
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Video $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereIsDefault($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereLanguageCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Caption whereVideoId($value)
 *
 * @mixin \Eloquent
 */
class Caption extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
