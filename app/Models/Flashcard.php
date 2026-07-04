<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exercise_deck_id
 * @property string $front_text
 * @property string $back_text
 * @property int|null $image_asset_id
 * @property int|null $audio_asset_id
 * @property string|null $mnemonic
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MediaAsset|null $audioAsset
 * @property-read ExerciseDeck $exerciseDeck
 * @property-read MediaAsset|null $imageAsset
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereAudioAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereBackText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereExerciseDeckId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereFrontText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereImageAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereMnemonic($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Flashcard whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Flashcard extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<ExerciseDeck, $this>
     */
    public function exerciseDeck(): BelongsTo
    {
        return $this->belongsTo(ExerciseDeck::class, 'exercise_deck_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function imageAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'image_asset_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function audioAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'audio_asset_id');
    }
}
