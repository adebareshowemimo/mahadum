<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $url
 * @property string|null $original_name
 * @property array<array-key, mixed>|null $qualities
 * @property int|null $duration_seconds
 * @property array<array-key, mixed>|null $captions
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $uploadedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereCaptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereDurationSeconds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereQualities($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereUploadedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MediaAsset whereUrl($value)
 *
 * @mixin \Eloquent
 */
class MediaAsset extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'qualities' => 'array',
        'captions' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
