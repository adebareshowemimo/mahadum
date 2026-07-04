<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A record of one email-upload import into a contact list, so the import can be
 * reviewed and rolled back (deleting the contacts it added).
 *
 * @property int $id
 * @property int $contact_list_id
 * @property int|null $created_by
 * @property int $imported
 * @property int $skipped
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUploadBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUploadBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactUploadBatch query()
 *
 * @mixin \Eloquent
 */
class ContactUploadBatch extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'upload_batch_id');
    }
}
