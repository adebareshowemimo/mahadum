<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $referral_id
 * @property string $beneficiary_type
 * @property int $beneficiary_id
 * @property int $amount_minor
 * @property string $status
 * @property Carbon|null $escrow_until
 * @property Carbon|null $cleared_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $beneficiary
 * @property-read Referral $referral
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereBeneficiaryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereBeneficiaryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereClearedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereEscrowUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereReferralId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Commission whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Commission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'escrow_until' => 'datetime',
        'cleared_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Referral, $this>
     */
    public function referral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'referral_id');
    }

    public function beneficiary(): MorphTo
    {
        return $this->morphTo();
    }
}
