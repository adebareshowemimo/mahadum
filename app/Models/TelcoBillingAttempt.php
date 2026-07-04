<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $telco_subscription_id
 * @property Carbon|null $attempted_at
 * @property int $amount_minor
 * @property string|null $result
 * @property string|null $operator_ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TelcoSubscription $telcoSubscription
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereAttemptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereOperatorRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereTelcoSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoBillingAttempt whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TelcoBillingAttempt extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<TelcoSubscription, $this>
     */
    public function telcoSubscription(): BelongsTo
    {
        return $this->belongsTo(TelcoSubscription::class, 'telco_subscription_id');
    }
}
