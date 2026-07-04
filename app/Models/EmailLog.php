<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A record of a single outbound email — transactional or campaign — for support,
 * compliance, and delivery debugging. Rows are written centrally by
 * App\Listeners\RecordSentEmail on every send; bounce/complaint webhooks later
 * update the matching row by message_id.
 *
 * @property int $id
 * @property string $to_email
 * @property int|null $user_id
 * @property int|null $contact_id
 * @property string $type
 * @property string|null $source
 * @property string|null $subject
 * @property string $status
 * @property string|null $error
 * @property string|null $message_id
 * @property Carbon|null $queued_at
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailLog query()
 *
 * @mixin \Eloquent
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
