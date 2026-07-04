<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $family_id
 * @property int|null $user_id
 * @property int|null $learner_profile_id
 * @property string $relationship
 * @property bool $is_account_owner
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Family|null $family
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereFamilyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereIsAccountOwner($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereRelationship($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FamilyMember whereUserId($value)
 *
 * @mixin \Eloquent
 */
class FamilyMember extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_account_owner' => 'boolean',
    ];

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
