<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A named list of email contacts (registered or not) that a campaign can target.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Contact> $contacts
 * @property-read int|null $contacts_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactList newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactList newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactList query()
 *
 * @mixin \Eloquent
 */
class ContactList extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
