<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_fingerprint
 * @property string|null $imei
 * @property string|null $ip_last_seen
 * @property string|null $platform
 * @property string|null $push_token
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereDeviceFingerprint($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereFirstSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereImei($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereIpLastSeen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device wherePlatform($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device wherePushToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Device whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Device extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
