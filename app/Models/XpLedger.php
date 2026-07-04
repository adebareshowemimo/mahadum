<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $amount
 * @property string $source
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereReferenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereReferenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|XpLedger whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class XpLedger extends Model
{
    use HasFactory;

    // Table is singular `xp_ledger` (an append-only ledger), not the
    // auto-pluralized `xp_ledgers`.
    protected $table = 'xp_ledger';

    protected $guarded = [];

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
