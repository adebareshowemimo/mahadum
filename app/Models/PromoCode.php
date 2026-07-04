<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $discount_type
 * @property int $value
 * @property string|null $applicable_tier
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property int|null $max_redemptions
 * @property int $redeemed_count
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PromoRedemption> $redemptions
 * @property-read int|null $redemptions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereApplicableTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereDiscountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereMaxRedemptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereRedeemedCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereValidFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereValidTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoCode whereValue($value)
 *
 * @mixin \Eloquent
 */
class PromoCode extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    /**
     * @return HasMany<PromoRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }
}
