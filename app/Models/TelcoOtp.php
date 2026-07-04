<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A one-time code that proves the caller controls an MSISDN before it can be
 * enrolled into airtime (VAS) billing. The plaintext code is never stored —
 * only a hash — and each row is single-use (consumed_at).
 *
 * @property int $id
 * @property int $user_id
 * @property string $msisdn
 * @property string $operator
 * @property string $code_hash
 * @property int $attempts
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $consumed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoOtp newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoOtp newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TelcoOtp query()
 *
 * @mixin \Eloquent
 */
class TelcoOtp extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
