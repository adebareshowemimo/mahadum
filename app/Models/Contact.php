<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contact_list_id
 * @property string $email
 * @property string|null $name
 * @property string $status
 * @property string|null $source
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $consent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContactList|null $list
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Contact query()
 *
 * @mixin \Eloquent
 */
class Contact extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
        'consent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ContactList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }
}
