<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $family_id
 * @property int $created_by_user_id
 * @property int|null $assignee_learner_profile_id
 * @property string $title
 * @property string|null $description
 * @property int $coin_reward
 * @property string $status
 * @property Carbon|null $due_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $assigneeLearnerProfile
 * @property-read User $createdByUser
 * @property-read User $creator
 * @property-read Family|null $family
 * @property-read Collection<int, ChoreSubmission> $submissions
 * @property-read int|null $submissions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereAssigneeLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereCoinReward($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereCreatedByUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereDueAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereFamilyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Chore whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Chore extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function assigneeLearnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'assignee_learner_profile_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<ChoreSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(ChoreSubmission::class);
    }
}
