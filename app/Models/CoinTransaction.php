<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int|null $learner_profile_id
 * @property string $type
 * @property string $source
 * @property int $amount
 * @property int|null $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read Wallet $wallet
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereBalanceAfter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereReferenceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereReferenceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CoinTransaction whereWalletId($value)
 *
 * @mixin \Eloquent
 */
class CoinTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
