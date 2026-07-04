<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property bool $is_staff
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 * @property-read SupportTicket|null $ticket
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupportTicketMessage query()
 *
 * @mixin \Eloquent
 */
class SupportTicketMessage extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_staff' => 'boolean',
    ];

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
