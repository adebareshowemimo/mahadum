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
 * @property string|null $body
 * @property array<array-key, mixed>|null $segments
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Video $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereLanguageCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereSegments($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transcript whereVideoId($value)
 *
 * @mixin \Eloquent
 */
class Transcript extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'segments' => 'array',
    ];

    /**
     * @return BelongsTo<Video, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
