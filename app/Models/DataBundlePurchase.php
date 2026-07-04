<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $operator
 * @property int $bundle_mb
 * @property int $amount_minor
 * @property string $status
 * @property Carbon|null $consent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereBundleMb($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereConsentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|DataBundlePurchase whereUserId($value)
 *
 * @mixin \Eloquent
 */
class DataBundlePurchase extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'consent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
