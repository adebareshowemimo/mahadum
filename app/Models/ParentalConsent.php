<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Verifiable parental-consent record (COPPA / NDPA) captured when a guardian
 * creates a child learner profile. Immutable audit evidence — who consented,
 * for whom, under which policy version, from where.
 *
 * @property int $id
 * @property int $family_id
 * @property int $guardian_user_id
 * @property int|null $learner_profile_id
 * @property string $type
 * @property string $policy_version
 * @property Carbon $granted_at
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $guardian
 * @property-read LearnerProfile|null $learnerProfile
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParentalConsent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParentalConsent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ParentalConsent query()
 *
 * @mixin \Eloquent
 */
class ParentalConsent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
