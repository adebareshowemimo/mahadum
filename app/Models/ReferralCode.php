<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $code
 * @property string $kind
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $owner
 * @property-read Collection<int, Referral> $referrals
 * @property-read int|null $referrals_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReferralCode whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ReferralCode extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<Referral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }
}
