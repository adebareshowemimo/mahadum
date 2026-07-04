<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $tier
 * @property Carbon|null $week_start
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, LeagueMembership> $memberships
 * @property-read int|null $memberships_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereTier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereWeekStart($value)
 *
 * @mixin \Eloquent
 */
class League extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'week_start' => 'date',
    ];

    /**
     * @return HasMany<LeagueMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }
}
