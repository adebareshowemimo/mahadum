<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $total_purchased
 * @property int $active_filled
 * @property string|null $term_label
 * @property Carbon|null $expires_at
 * @property bool $auto_renew
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereActiveFilled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereAutoRenew($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereTermLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereTotalPurchased($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SeatAllocation withoutTenancy()
 *
 * @mixin \Eloquent
 */
class SeatAllocation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }
}
