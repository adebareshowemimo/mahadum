<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $story_id
 * @property int $user_id
 * @property string $provider
 * @property string $status
 * @property int $amount_minor
 * @property string $reference
 * @property string|null $gateway_txn_ref
 * @property string|null $checkout_url
 * @property Carbon|null $paid_at
 * @property-read Story $story
 * @property-read User $user
 */
class StoryPurchase extends Model
{
    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime'];

    /** @return BelongsTo<Story, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
