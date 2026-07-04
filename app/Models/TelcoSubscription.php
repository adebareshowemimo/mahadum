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
 * @property int $subscription_id
 * @property string $msisdn
 * @property string $operator
 * @property int $daily_amount_minor
 * @property string $state
 * @property Carbon|null $grace_until
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TelcoBillingAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read Subscription $subscription
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereDailyAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereGraceUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereMsisdn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereNextAttemptAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoSubscription whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TelcoSubscription extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'grace_until' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * @return HasMany<TelcoBillingAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(TelcoBillingAttempt::class);
    }
}
