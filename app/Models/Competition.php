<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The annual national Language & Culture competition. Global (untenanted).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property int $season
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $submissions_close_at
 * @property Carbon|null $voting_closes_at
 * @property int $min_activity_days
 * @property-read Collection<int, CompetitionEntry> $entries
 *
 * @mixin \Eloquent
 */
class Competition extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'submissions_close_at' => 'datetime',
        'voting_closes_at' => 'datetime',
        'season' => 'integer',
        'min_activity_days' => 'integer',
    ];

    public const CATEGORIES = ['school_play', 'diaspora_folklore'];

    /**
     * @return HasMany<CompetitionEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class);
    }

    /**
     * @return HasMany<CompetitionVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(CompetitionVote::class);
    }

    public function acceptingEntries(): bool
    {
        return $this->status === 'open';
    }

    public function acceptingVotes(): bool
    {
        return in_array($this->status, ['open', 'voting'], true);
    }
}
