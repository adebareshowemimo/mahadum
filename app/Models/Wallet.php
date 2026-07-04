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
 * @property int $coin_balance
 * @property int $currency_balance_minor
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $owner
 * @property-read Collection<int, CoinTransaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCoinBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereCurrencyBalanceMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereOwnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Wallet whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Wallet extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CoinTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CoinTransaction::class);
    }
}
