<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Referral;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\TelcoBillingAttempt;
use App\Models\User;
use App\Models\WalletFundingTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    /**
     * Income by revenue channel × month. Channels:
     *   - card      : wallet funding via Paystack/Flutterwave (WalletFundingTransaction)
     *   - telco     : daily airtime VAS billing (TelcoBillingAttempt)
     *   - invoices  : school term/seat invoices (Invoice, recognised when paid)
     *
     * Reports gross, refunds, and net (gross − refunds) per channel, plus a
     * month-by-month series so the reviewer can see the trend and mix.
     */
    public function income(Request $request): JsonResponse
    {
        [$from, $to, $months] = $this->window($request);

        $channels = [
            $this->channel('card', 'Card / wallet funding', $months,
                $this->monthly(WalletFundingTransaction::where('status', 'success')->whereBetween('created_at', [$from, $to])->get(['amount_minor', 'created_at']), 'created_at'),
                $this->monthly(WalletFundingTransaction::where('status', 'refunded')->whereBetween('created_at', [$from, $to])->get(['amount_minor', 'created_at']), 'created_at'),
            ),
            $this->channel('telco', 'Telco airtime (VAS)', $months,
                $this->monthly(TelcoBillingAttempt::where('result', 'success')->whereBetween('attempted_at', [$from, $to])->get(['amount_minor', 'attempted_at']), 'attempted_at'),
                [],
            ),
            $this->channel('invoices', 'School invoices', $months,
                $this->monthly(Invoice::where('status', 'paid')->whereBetween('paid_at', [$from, $to])->get(['amount_minor', 'paid_at']), 'paid_at'),
                [],
            ),
        ];

        // Column + grand totals across channels.
        $totalsByMonth = [];
        foreach ($months as $m) {
            $totalsByMonth[$m] = array_sum(array_map(fn ($c) => $c['by_month'][$m], $channels));
        }

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'channels' => $channels,
            'totals' => [
                'by_month' => $totalsByMonth,
                'gross' => array_sum(array_column($channels, 'gross')),
                'refunds' => array_sum(array_column($channels, 'refunds')),
                'net' => array_sum(array_column($channels, 'net')),
            ],
        ]]);
    }

    /**
     * User & organization growth: new sign-ups per month + all-time totals.
     */
    public function growth(Request $request): JsonResponse
    {
        [$from, $to, $months] = $this->window($request);

        $users = $this->countByMonth(User::whereBetween('created_at', [$from, $to])->get(['created_at']), 'created_at');
        $orgs = $this->countByMonth(Organization::whereBetween('created_at', [$from, $to])->get(['created_at']), 'created_at');

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'series' => [
                $this->series('users', 'New users', $months, $users),
                $this->series('orgs', 'New organizations', $months, $orgs),
            ],
            'totals' => [
                'users' => User::count(),
                'organizations' => Organization::count(),
            ],
        ]]);
    }

    /**
     * Subscription funnel: new subscriptions per month, counts by status, and the
     * active total (a simple paid-conversion signal for the reviewer).
     */
    public function subscriptions(Request $request): JsonResponse
    {
        [$from, $to, $months] = $this->window($request);

        $newByMonth = $this->countByMonth(Subscription::whereBetween('created_at', [$from, $to])->get(['created_at']), 'created_at');
        $byStatus = Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'new' => $this->series('new', 'New subscriptions', $months, $newByMonth),
            'by_status' => $byStatus,
            'active' => (int) ($byStatus['active'] ?? 0),
            'total' => Subscription::count(),
        ]]);
    }

    /**
     * Organizations & schools: new orgs per month, the org status mix, and
     * platform-wide class + enrolled-student totals.
     */
    public function orgActivity(Request $request): JsonResponse
    {
        [$from, $to, $months] = $this->window($request);

        $newByMonth = $this->countByMonth(Organization::whereBetween('created_at', [$from, $to])->get(['created_at']), 'created_at');
        $byStatus = Organization::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'new' => $this->series('new', 'New organizations', $months, $newByMonth),
            'by_status' => $byStatus,
            'totals' => [
                'organizations' => Organization::count(),
                'classes' => SchoolClass::count(),
                'students' => ClassEnrollment::distinct('learner_profile_id')->count('learner_profile_id'),
            ],
        ]]);
    }

    /**
     * Referrals & commissions: new referrals per month, the referral status mix,
     * and commission totals by status (cleared / escrow / clawback-pending).
     */
    public function referrals(Request $request): JsonResponse
    {
        [$from, $to, $months] = $this->window($request);

        $newByMonth = $this->countByMonth(Referral::whereBetween('created_at', [$from, $to])->get(['created_at']), 'created_at');
        $refByStatus = Referral::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $commByStatus = Commission::selectRaw('status, COUNT(*) c, COALESCE(SUM(amount_minor),0) t')
            ->groupBy('status')->get()
            ->mapWithKeys(fn (Commission $r) => [$r->status => [
                'count' => (int) $r->getAttribute('c'),
                'total_minor' => (int) $r->getAttribute('t'),
            ]]);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'new' => $this->series('new', 'New referrals', $months, $newByMonth),
            'referrals_by_status' => $refByStatus,
            'commissions_by_status' => $commByStatus,
        ]]);
    }

    /**
     * Upcoming billing renewals: active subscriptions due to renew, bucketed by
     * month over a forward window. Reports the renewal count, expected renewal
     * revenue (each sub's plan price), the payment-method mix, and how much of the
     * upcoming book has already had a renewal reminder sent.
     */
    public function renewals(Request $request): JsonResponse
    {
        [$from, $to, $months] = $this->forwardWindow($request);

        /** @var Collection<int, Subscription> $due */
        $due = Subscription::with('plan:id,price_minor')
            ->where('status', 'active')
            ->whereNotNull('renews_at')
            ->whereBetween('renews_at', [$from, $to])
            ->get(['id', 'plan_id', 'method', 'renews_at', 'renewal_reminded_at']);

        $countByMonth = [];
        $revenueByMonth = [];
        $byMethod = [];
        $reminded = 0;

        foreach ($due as $sub) {
            $m = Carbon::parse($sub->renews_at)->format('Y-m');
            $countByMonth[$m] = ($countByMonth[$m] ?? 0) + 1;
            $revenueByMonth[$m] = ($revenueByMonth[$m] ?? 0) + (int) ($sub->plan->price_minor ?? 0);
            $byMethod[$sub->method] = ($byMethod[$sub->method] ?? 0) + 1;
            if ($sub->renewal_reminded_at !== null) {
                $reminded++;
            }
        }

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'months' => $months,
            'count' => $this->series('count', 'Renewals due', $months, $countByMonth),
            'revenue' => $this->series('revenue', 'Expected revenue (minor)', $months, $revenueByMonth),
            'by_method' => $byMethod,
            'reminders' => [
                'reminded' => $reminded,
                'total' => $due->count(),
            ],
        ]]);
    }

    /**
     * Validated + defaulted reporting window → [from, to, monthKeys]. Default is
     * the trailing 6 months.
     *
     * @return array{0: Carbon, 1: Carbon, 2: array<int, string>}
     */
    private function window(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : Carbon::now()->endOfMonth();
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : $to->copy()->startOfMonth()->subMonths(5);

        return [$from, $to, $this->monthKeys($from, $to)];
    }

    /**
     * Forward-looking window for upcoming events (renewals). Default is the
     * current month through the next 5 months.
     *
     * @return array{0: Carbon, 1: Carbon, 2: array<int, string>}
     */
    private function forwardWindow(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : $from->copy()->addMonths(5)->endOfMonth();

        return [$from, $to, $this->monthKeys($from, $to)];
    }

    /**
     * A generic padded month series with a total (for count-based reports).
     *
     * @param  array<int, string>  $months
     * @param  array<string, int>  $byMonthRaw
     * @return array<string, mixed>
     */
    private function series(string $key, string $label, array $months, array $byMonthRaw): array
    {
        $byMonth = [];
        foreach ($months as $m) {
            $byMonth[$m] = $byMonthRaw[$m] ?? 0;
        }

        return ['key' => $key, 'label' => $label, 'by_month' => $byMonth, 'total' => array_sum($byMonth)];
    }

    /**
     * Count rows per month (DB-agnostic, done in PHP).
     *
     * @param  iterable<int, Model>  $rows
     * @return array<string, int>
     */
    private function countByMonth(iterable $rows, string $dateCol): array
    {
        $out = [];
        foreach ($rows as $row) {
            $m = Carbon::parse($row->getAttribute($dateCol))->format('Y-m');
            $out[$m] = ($out[$m] ?? 0) + 1;
        }

        return $out;
    }

    /**
     * Collapse rows into a month → summed-minor map (DB-agnostic, done in PHP).
     *
     * @param  iterable<int, Model>  $rows
     * @return array<string, int>
     */
    private function monthly(iterable $rows, string $dateCol): array
    {
        $out = [];
        foreach ($rows as $row) {
            $m = Carbon::parse($row->getAttribute($dateCol))->format('Y-m');
            $out[$m] = ($out[$m] ?? 0) + (int) $row->getAttribute('amount_minor');
        }

        return $out;
    }

    /**
     * Build a single channel's monthly series (padded to $months) + gross/refunds/net.
     *
     * @param  array<int, string>  $months
     * @param  array<string, int>  $grossByMonth
     * @param  array<string, int>  $refundsByMonth
     * @return array<string, mixed>
     */
    private function channel(string $key, string $label, array $months, array $grossByMonth, array $refundsByMonth): array
    {
        $byMonth = [];
        foreach ($months as $m) {
            $byMonth[$m] = $grossByMonth[$m] ?? 0;
        }

        $gross = array_sum($byMonth);
        $refunds = array_sum(array_intersect_key($refundsByMonth, $byMonth));

        return [
            'key' => $key,
            'label' => $label,
            'by_month' => $byMonth,
            'gross' => $gross,
            'refunds' => $refunds,
            'net' => $gross - $refunds,
        ];
    }

    /**
     * Inclusive list of YYYY-MM keys spanning [$from, $to].
     *
     * @return array<int, string>
     */
    private function monthKeys(Carbon $from, Carbon $to): array
    {
        $keys = [];
        $cursor = $from->copy()->startOfMonth();
        $end = $to->copy()->startOfMonth();
        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }

        return $keys;
    }
}
