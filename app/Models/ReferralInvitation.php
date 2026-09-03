<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invite a referrer sent to a specific email or phone. At sign-up the new
 * user is matched back to a pending invitation by this contact, which is how the
 * referrer / admin dashboards know a referral came "via email" vs "via phone".
 *
 * @property int $id
 * @property int $referral_code_id
 * @property int|null $inviter_user_id
 * @property string $channel
 * @property string $contact
 * @property string $status
 * @property int|null $accepted_referral_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ReferralCode $referralCode
 * @property-read User|null $inviter
 * @property-read Referral|null $acceptedReferral
 *
 * @mixin \Eloquent
 */
class ReferralInvitation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
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
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    /**
     * @return BelongsTo<Referral, $this>
     */
    public function acceptedReferral(): BelongsTo
    {
        return $this->belongsTo(Referral::class, 'accepted_referral_id');
    }
}
