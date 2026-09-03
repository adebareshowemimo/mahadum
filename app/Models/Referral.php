<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $referral_code_id
 * @property int|null $referred_user_id
 * @property int|null $referred_subscription_id
 * @property string $status
 * @property string|null $device_fingerprint
 * @property string|null $contact_channel
 * @property string|null $contact_value
 * @property int|null $referral_invitation_id
 * @property Carbon|null $signed_up_at
 * @property Carbon|null $first_lesson_completed_at
 * @property Carbon|null $first_quiz_completed_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ReferralCode $code
 * @property-read Collection<int, Commission> $commissions
 * @property-read int|null $commissions_count
 * @property-read ReferralCode $referralCode
 * @property-read Subscription|null $referredSubscription
 * @property-read User|null $referredUser
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereDeviceFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereReferralCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereReferredSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereReferredUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereSignedUpAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Referral whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Referral extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'signed_up_at' => 'datetime',
        'first_lesson_completed_at' => 'datetime',
        'first_quiz_completed_at' => 'datetime',
        'activated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ReferralCode, $this>
     */
    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function referredSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'referred_subscription_id');
    }

    /**
     * @return BelongsTo<ReferralCode, $this>
     */
    public function code(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class, 'referral_code_id');
    }

    /**
     * @return HasMany<Commission, $this>
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * @return BelongsTo<ReferralInvitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ReferralInvitation::class, 'referral_invitation_id');
    }
}
