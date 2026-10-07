<?php

namespace App\Services\Billing;

use App\Models\TelcoBillingAttempt;

class BillingAnalytics
{
    /** @return array<string, mixed> */
    public function dailyTelco(): array
    {
        $end = now()->endOfDay();
        $start = now()->startOfDay()->subDays(6);
        $rows = TelcoBillingAttempt::whereBetween('attempted_at', [$start, $end])
            ->selectRaw("DATE(attempted_at) as day, COUNT(*) as attempts, SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as success")
            ->groupByRaw('DATE(attempted_at)')->get()->keyBy('day');
        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $attempts = (int) ($rows[$key]->attempts ?? 0);
            $success = (int) ($rows[$key]->success ?? 0);
            $days[] = ['date' => $key, 'attempts' => $attempts, 'success' => $success, 'success_rate' => $attempts > 0 ? round($success / $attempts * 100, 1) : null];
        }

        return ['timezone' => config('app.timezone'), 'days' => $days];
    }
}
