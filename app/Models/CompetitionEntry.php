<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $competition_id
 * @property string $category
 * @property int|null $organization_id
 * @property int|null $learner_profile_id
 * @property int|null $language_id
 * @property int|null $submitted_by
 * @property string $title
 * @property string|null $synopsis
 * @property int|null $media_asset_id
 * @property string $status
 * @property int $votes_count
 * @property int|null $award_rank
 * @property Carbon|null $submitted_at
 * @property-read Competition $competition
 * @property-read Organization|null $organization
 * @property-read LearnerProfile|null $learnerProfile
 * @property-read Language|null $language
 *
 * @mixin \Eloquent
 */
class CompetitionEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'submitted_at' => 'datetime',
        'votes_count' => 'integer',
        'award_rank' => 'integer',
    ];

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<LearnerProfile, $this>
     */
    public function learnerProfile(): BelongsTo
    {
        return $this->belongsTo(LearnerProfile::class);
    }

    /**
     * @return BelongsTo<Language, $this>
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }
}
