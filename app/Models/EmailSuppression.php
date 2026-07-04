<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Global suppression list — an address here is excluded from marketing sends
 * (unsubscribes, hard bounces, complaints). Transactional mail is unaffected.
 *
 * @property int $id
 * @property string $email
 * @property string $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSuppression newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSuppression newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailSuppression query()
 *
 * @mixin \Eloquent
 */
class EmailSuppression extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** True if the given address is globally suppressed. */
    public static function suppresses(string $email): bool
    {
        return static::where('email', mb_strtolower(trim($email)))->exists();
    }
}
