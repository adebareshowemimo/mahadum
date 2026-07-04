<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's submission against a teacher-created ClassAssignment. Distinct
 * from AssignmentSubmission (a CMS lesson-component clip, parent-reviewed):
 * this is school-class work, graded by the owning teacher.
 *
 * @property int $id
 * @property int $class_assignment_id
 * @property int $learner_profile_id
 * @property int|null $media_asset_id
 * @property string $status
 * @property bool|null $passed
 * @property int|null $score
 * @property string|null $feedback
 * @property Carbon|null $submitted_at
 * @property int|null $graded_by
 * @property Carbon|null $graded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClassAssignment $classAssignment
 * @property-read User|null $gradedBy
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read MediaAsset|null $mediaAsset
 *
 * @mixin \Eloquent
 */
class ClassAssignmentSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'passed' => 'boolean',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<ClassAssignment, $this>
     */
    public function classAssignment(): BelongsTo
    {
        return $this->belongsTo(ClassAssignment::class);
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class);
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
