<?php

namespace App\Console\Commands;

use App\Models\EmailCampaign;
use App\Services\Email\CampaignSender;
use Illuminate\Console\Command;

/**
 * Sends any campaign whose scheduled time has arrived. Idempotent — CampaignSender
 * skips a campaign already sending/sent, so a double tick can't double-send.
 */
class DispatchScheduledCampaigns extends Command
{
    protected $signature = 'emails:dispatch-scheduled';

    protected $description = 'Dispatch email campaigns whose scheduled time has arrived';

    public function handle(CampaignSender $sender): int
    {
        $due = EmailCampaign::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $campaign) {
            $sender->send($campaign);
        }

        $this->info("Dispatched {$due->count()} scheduled campaign(s).");

        return self::SUCCESS;
    }
}
