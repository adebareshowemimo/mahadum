<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $email_campaign_id
 * @property string $email
 * @property int|null $user_id
 * @property int|null $contact_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EmailCampaign|null $campaign
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailCampaignRecipient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailCampaignRecipient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|EmailCampaignRecipient query()
 *
 * @mixin \Eloquent
 */
class EmailCampaignRecipient extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<EmailCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}
