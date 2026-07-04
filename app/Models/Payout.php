<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $beneficiary_type
 * @property int $beneficiary_id
 * @property int $amount_minor
 * @property string $method
 * @property string $source
 * @property string $status
 * @property Carbon|null $requested_at
 * @property int|null $approved_by
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $approvedBy
 * @property-read Model|\Eloquent $beneficiary
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereApprovedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereBeneficiaryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereBeneficiaryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereRequestedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Payout extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'requested_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function beneficiary(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * True when $user is the payout's beneficiary (directly, or as an active
     * member of the beneficiary organization). Drives separation-of-duties on
     * approval — see PayoutController::approve and PayoutPolicy::approve.
     */
    public function isBeneficiary(User $user): bool
    {
        if ($this->beneficiary_type === User::class) {
            return (int) $this->beneficiary_id === (int) $user->id;
        }

        return $user->organizations()
            ->wherePivot('status', 'active')
            ->whereKey($this->beneficiary_id)
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
