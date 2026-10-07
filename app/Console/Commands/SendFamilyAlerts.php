<?php

namespace App\Console\Commands;

use App\Models\Family;
use App\Models\FamilyAlertPreference;
use App\Services\Family\FamilyAlertService;
use Illuminate\Console\Command;

class SendFamilyAlerts extends Command
{
    protected $signature = 'family:send-alerts';

    protected $description = 'Send configured family alerts once per low-balance, inactivity or review episode';

    public function handle(FamilyAlertService $alerts): int
    {
        Family::whereIn('id', FamilyAlertPreference::select('family_id'))->chunkById(100, function ($families) use ($alerts) {
            foreach ($families as $family) {
                $alerts->evaluate($family);
            }
        });

        return self::SUCCESS;
    }
}
