<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An admin email blast to a user segment or a contact list, rendered in the one
 * brand template and delivered over batched, suppression-aware sends.
 *
 * @property int $id
 * @property string $subject
 * @property string $body
 * @property string $audience_type
 * @property array<string, mixed>|null $audience
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property int $recipients_count
 * @property int $sent_count
 * @property int $failed_count
 * @property Carbon|null $sent_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, EmailCampaignRecipient> $recipients
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailCampaign newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailCampaign newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailCampaign query()
 *
 * @mixin \Eloquent
 */
class EmailCampaign extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'audience' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * @return HasMany<EmailCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }
}
