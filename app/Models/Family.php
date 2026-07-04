<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $owner_user_id
 * @property int|null $organization_id
 * @property string $name
 * @property string|null $parental_pin
 * @property string|null $referral_source_code
 * @property int $child_limit
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Chore> $chores
 * @property-read int|null $chores_count
 * @property-read Collection<int, LearnerProfile> $learnerProfiles
 * @property-read int|null $learner_profiles_count
 * @property-read Collection<int, FamilyMember> $members
 * @property-read int|null $members_count
 * @property-read Organization|null $organization
 * @property-read User $owner
 * @property-read User $ownerUser
 * @property-read Wallet|null $wallet
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereChildLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereOwnerUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereParentalPin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereReferralSourceCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Family withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Family extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<FamilyMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * @return HasMany<LearnerProfile, $this>
     */
    public function learnerProfiles(): HasMany
    {
        return $this->hasMany(LearnerProfile::class);
    }

    /**
     * @return HasMany<Chore, $this>
     */
    public function chores(): HasMany
    {
        return $this->hasMany(Chore::class);
    }

    /**
     * @return MorphOne<Wallet, $this>
     */
    public function wallet(): MorphOne
    {
        return $this->morphOne(Wallet::class, 'owner');
    }
}
