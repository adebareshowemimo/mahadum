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
        $evaluated = 0;
        $failed = 0;
        Family::whereIn('id', FamilyAlertPreference::select('family_id'))->chunkById(100, function ($families) use ($alerts, &$evaluated, &$failed) {
            foreach ($families as $family) {
                $evaluated++;
                try {
                    $alerts->evaluate($family);
                } catch (\Throwable $error) {
                    $failed++;
                    report($error);
                    $this->error("Alert evaluation failed for family {$family->id}. See the application log.");
                }
            }
        });
        $this->info("Evaluated {$evaluated} families; {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
