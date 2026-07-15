<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A prospective school's contact details, captured from the public pricing
 * page's "Get Quote" flow for manual sales follow-up. Not an Organization —
 * no account or tenant is created until the school actually signs up.
 *
 * @property int $id
 * @property string $school_name
 * @property string $contact_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $school_size
 * @property string|null $city
 * @property string|null $notes
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 *
 * @mixin \Eloquent
 */
class SchoolLead extends Model
{
    use HasFactory;

    protected $guarded = [];
}
