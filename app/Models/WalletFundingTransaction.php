<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $gateway
 * @property int $amount_minor
 * @property string $currency
 * @property string $status
 * @property string|null $gateway_ref
 * @property string|null $gateway_txn_ref
 * @property array<array-key, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Wallet $wallet
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereAmountMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereGateway($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereGatewayRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereGatewayTxnRef($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereRaw($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WalletFundingTransaction whereWalletId($value)
 *
 * @mixin \Eloquent
 */
class WalletFundingTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'raw' => 'array',
    ];

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }
}
