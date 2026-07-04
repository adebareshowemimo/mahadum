<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $family_id
 * @property int|null $organization_id
 * @property int|null $user_id
 * @property string $display_name
 * @property int|null $avatar_id
 * @property Carbon|null $date_of_birth
 * @property string|null $age_band
 * @property int|null $target_language_id
 * @property int $current_level
 * @property bool $parental_pin_protected
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read Family|null $family
 * @property-read Heart|null $hearts
 * @property-read Collection<int, LessonProgress> $lessonProgress
 * @property-read int|null $lesson_progress_count
 * @property-read Organization|null $organization
 * @property-read Collection<int, SpeakingSubmission> $speakingSubmissions
 * @property-read int|null $speaking_submissions_count
 * @property-read Streak|null $streak
 * @property-read Language|null $targetLanguage
 * @property-read User|null $user
 * @property-read Collection<int, XpLedger> $xpEntries
 * @property-read int|null $xp_entries_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereAgeBand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereAvatarId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereCurrentLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereDateOfBirth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereDisplayName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereFamilyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereOrganizationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereParentalPinProtected($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereTargetLanguageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LearnerProfile withoutTrashed()
 *
 * @mixin \Eloquent
 */
class LearnerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date_of_birth' => 'date',
        'parental_pin_protected' => 'boolean',
    ];

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'family_id');
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function targetLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'target_language_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * @return HasMany<XpLedger, $this>
     */
    public function xpEntries(): HasMany
    {
        return $this->hasMany(XpLedger::class);
    }

    /**
     * @return HasMany<SpeakingSubmission, $this>
     */
    public function speakingSubmissions(): HasMany
    {
        return $this->hasMany(SpeakingSubmission::class);
    }

    /**
     * @return HasOne<Streak, $this>
     */
    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }

    /**
     * @return HasOne<Heart, $this>
     */
    public function hearts(): HasOne
    {
        return $this->hasOne(Heart::class);
    }
}
