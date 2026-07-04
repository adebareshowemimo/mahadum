<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $competition_id
 * @property int $competition_entry_id
 * @property string $category
 * @property int $user_id
 *
 * @mixin \Eloquent
 */
class CompetitionVote extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<CompetitionEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class, 'competition_entry_id');
    }
}
