<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chore_id
 * @property int|null $evidence_media_id
 * @property string $evidence_type
 * @property Carbon|null $submitted_at
 * @property string|null $decision
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Chore $chore
 * @property-read User|null $decidedBy
 * @property-read MediaAsset|null $evidenceMedia
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereChoreId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereDecidedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereDecidedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereDecision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereEvidenceMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereEvidenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChoreSubmission whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ChoreSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Chore, $this>
     */
    public function chore(): BelongsTo
    {
        return $this->belongsTo(Chore::class, 'chore_id');
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function evidenceMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'evidence_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
