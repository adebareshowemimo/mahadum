<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $promo_code_id
 * @property int|null $organization_id
 * @property int|null $user_id
 * @property int|null $subscription_id
 * @property int|null $payment_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read PromoCode $promoCode
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption wherePromoCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PromoRedemption whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PromoRedemption extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class, 'promo_code_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
