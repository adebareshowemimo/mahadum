<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $learner_profile_id
 * @property int $lesson_component_id
 * @property int $inviter_user_id
 * @property int $recipient_user_id
 * @property string $token_hash
 * @property string $channel
 * @property Carbon $expires_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $accepted_at
 * @property-read LearnerProfile $learner
 * @property-read LessonComponent $component
 * @property-read User $inviter
 * @property-read User $recipient
 */
class TonePracticeInvitation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /** @return BelongsTo<LearnerProfile, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }

    /** @return BelongsTo<LessonComponent, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(LessonComponent::class, 'lesson_component_id');
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
