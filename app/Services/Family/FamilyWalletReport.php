<?php

namespace App\Services\Family;

use App\Models\CoinTransaction;
use App\Models\Family;
use App\Models\FamilyCoinPool;
use App\Models\Wallet;
use App\Models\WalletCurrencyTransaction;
use App\Models\WalletFundingTransaction;
use Illuminate\Support\Collection;

class FamilyWalletReport
{
    public function __construct(private WalletService $wallets) {}

    /** @return array<string, mixed> */
    public function forFamily(Family $family): array
    {
        $owners = collect([['wallet' => $this->wallets->walletFor($family), 'name' => 'Family']]);
        foreach ($family->learnerProfiles as $learner) {
            $owners->push(['wallet' => $this->wallets->walletFor($learner), 'name' => $learner->display_name]);
        }
        foreach (FamilyCoinPool::where('family_id', $family->id)->get() as $pool) {
            $owners->push(['wallet' => $this->wallets->walletFor($pool), 'name' => $pool->name]);
        }

        $wallets = $owners->pluck('wallet');
        $walletIds = $wallets->pluck('id');
        $names = $owners->mapWithKeys(fn (array $owner) => [$owner['wallet']->id => $owner['name']]);
        $entries = CoinTransaction::whereIn('wallet_id', $walletIds)->orderBy('id')->get();
        $byWallet = $entries->groupBy('wallet_id');
        $accounts = $wallets->map(function (Wallet $wallet) use ($names, $byWallet) {
            $ledgerBalance = $this->netCoins($byWallet->get($wallet->id, collect()));

            return [
                'name' => $names[$wallet->id],
                'coin_balance' => $wallet->coin_balance,
                'ledger_balance' => $ledgerBalance,
                'reconciled' => $wallet->coin_balance === $ledgerBalance,
            ];
        });
        $fundings = WalletFundingTransaction::where('wallet_id', $wallets->first()->id)->get();
        $confirmedFunding = (int) $fundings->where('status', 'success')->sum('amount_minor');
        $cashEntries = WalletCurrencyTransaction::whereIn('wallet_id', $walletIds)->orderBy('id')->get();
        $cashByWallet = $cashEntries->groupBy('wallet_id');
        $reclaimedAllowance = (int) $cashEntries->where('source', 'funding_refund')->sum('amount_minor');
        $cashAccounts = $wallets->map(function (Wallet $wallet) use ($names, $cashByWallet, $confirmedFunding, $reclaimedAllowance, $wallets) {
            $transfers = (int) $cashByWallet->get($wallet->id, collect())->sum(
                fn (WalletCurrencyTransaction $entry) => $entry->type === 'credit' ? $entry->amount_minor : -$entry->amount_minor,
            );
            $expected = ($wallet->id === $wallets->first()->id ? $confirmedFunding + $reclaimedAllowance : 0) + $transfers;

            return [
                'name' => $names[$wallet->id], 'balance_minor' => $wallet->currency_balance_minor,
                'expected_minor' => $expected, 'reconciled' => $wallet->currency_balance_minor === $expected,
            ];
        });
        $cashBalance = (int) $cashAccounts->sum('balance_minor');
        $sourceTotals = $entries->groupBy('source')->map(fn (Collection $rows) => $this->netCoins($rows));

        return [
            'total_coins' => (int) $accounts->sum('coin_balance'),
            'coins_added' => (int) $sourceTotals->filter(fn (int $net) => $net > 0)->sum(),
            'coins_spent' => (int) -$sourceTotals->filter(fn (int $net) => $net < 0)->sum(),
            'accounts' => $accounts->values(),
            'coin_reconciled' => $accounts->every(fn (array $account) => $account['reconciled']),
            'cash' => [
                'balance_minor' => $cashBalance,
                'confirmed_funding_minor' => $confirmedFunding,
                'unexplained_minor' => $cashBalance - $confirmedFunding,
                'reconciled' => $cashAccounts->every(fn (array $account) => $account['reconciled']),
                'accounts' => $cashAccounts->values(),
            ],
            'coin_sources' => $sourceTotals->map(fn (int $net, string $source) => [
                'source' => $source,
                'net_coins' => $net,
            ])->values(),
            'activity' => $entries->reverse()->take(50)->map(fn (CoinTransaction $entry) => [
                'id' => $entry->id,
                'account' => $names[$entry->wallet_id],
                'type' => $entry->type,
                'source' => $entry->source,
                'amount' => $entry->amount,
                'balance_after' => $entry->balance_after,
                'created_at' => $entry->created_at,
            ])->values(),
            'cash_activity' => $cashEntries->reverse()->take(50)->map(fn (WalletCurrencyTransaction $entry) => [
                'id' => $entry->id,
                'account' => $names[$entry->wallet_id],
                'type' => $entry->type,
                'source' => $entry->source,
                'amount_minor' => $entry->amount_minor,
                'balance_after_minor' => $entry->balance_after_minor,
                'created_at' => $entry->created_at,
            ])->values(),
        ];
    }

    /** @param Collection<int, CoinTransaction> $entries */
    private function netCoins(Collection $entries): int
    {
        return (int) $entries->sum(fn (CoinTransaction $entry) => $entry->type === 'credit' ? $entry->amount : -$entry->amount);
    }
}
