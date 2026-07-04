<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per teacher per organization per accrual month
 * (`compensation:accrue-teachers`, run monthly) — the count of that teacher's
 * currently-enrolled students whose school has an active/paid seat allocation,
 * times the admin-configured per-student rate. Append-only; a teacher's
 * available balance is this sum minus their 'teaching'-source Payouts.
 *
 * @property int $id
 * @property int $teacher_user_id
 * @property int $organization_id
 * @property string $period
 * @property int $paying_student_count
 * @property int $rate_minor
 * @property int $amount_minor
 * @property-read Organization|null $organization
 * @property-read User|null $teacher
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TeacherCompensationEntry withoutTenancy()
 *
 * @mixin \Eloquent
 */
class TeacherCompensationEntry extends Model
{
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_user_id');
    }
}
