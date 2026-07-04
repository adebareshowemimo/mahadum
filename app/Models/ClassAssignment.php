<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $school_class_id
 * @property string $title
 * @property string|null $instructions
 * @property Carbon|null $due_at
 * @property int $coin_reward
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read SchoolClass $schoolClass
 * @property-read Collection<int, ClassAssignmentSubmission> $submissions
 * @property-read int|null $submissions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClassAssignment withoutTenancy()
 *
 * @mixin \Eloquent
 */
class ClassAssignment extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<ClassAssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(ClassAssignmentSubmission::class);
    }
}
