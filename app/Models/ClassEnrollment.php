<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $school_class_id
 * @property int $learner_profile_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read SchoolClass $schoolClass
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereLearnerProfileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereSchoolClassId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassEnrollment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ClassEnrollment extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** Current same-school membership; historical rows remain available for review. */
    public function scopeCurrentLearners(Builder $query): Builder
    {
        return $query->whereExists(fn ($members) => $members->selectRaw('1')
            ->from('learner_profiles as roster_learners')
            ->join('school_classes as roster_classes', 'roster_classes.id', '=', 'class_enrollments.school_class_id')
            ->whereColumn('roster_learners.id', 'class_enrollments.learner_profile_id')
            ->whereColumn('roster_learners.organization_id', 'roster_classes.organization_id')
            ->whereNull('roster_learners.deleted_at'));
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class, 'learner_profile_id');
    }
}
