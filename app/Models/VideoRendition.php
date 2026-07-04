<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $video_id
 * @property string $quality
 * @property string $protocol
 * @property string $manifest_url
 * @property int|null $bitrate_kbps
 * @property int|null $size_bytes
 * @property bool $ready
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Video $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereBitrateKbps($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereManifestUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereProtocol($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereQuality($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereReady($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VideoRendition whereVideoId($value)
 *
 * @mixin \Eloquent
 */
class VideoRendition extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'ready' => 'boolean',
    ];

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
