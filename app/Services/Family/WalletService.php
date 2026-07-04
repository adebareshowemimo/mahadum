<?php

namespace App\Services\Family;

use App\Models\CoinTransaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Coin movements via an append-only ledger (coin_transactions). The wallet's
 * coin_balance is a cached running total kept in lockstep with each ledger row's
 * balance_after; every mutation locks the wallet row to stay concurrency-safe.
 * Debits never overdraw.
 */
class WalletService
{
    public function walletFor(Model $owner): Wallet
    {
        return Wallet::firstOrCreate(
            ['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey()],
            ['coin_balance' => 0, 'currency' => 'NGN'],
        );
    }

    public function credit(Wallet $wallet, int $amount, string $source, ?int $learnerId = null, ?Model $reference = null): CoinTransaction
    {
        return $this->move($wallet, 'credit', $amount, $source, $learnerId, $reference);
    }

    public function debit(Wallet $wallet, int $amount, string $source, ?int $learnerId = null, ?Model $reference = null): CoinTransaction
    {
        return $this->move($wallet, 'debit', $amount, $source, $learnerId, $reference);
    }

    /**
     * Credit the real-money balance (minor units). Used when a gateway funding
     * webhook confirms — the wallet_funding_transactions row is the ledger here,
     * so we only move the cached balance.
     */
    public function creditCurrency(Wallet $wallet, int $amountMinor): Wallet
    {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Amount must be positive.');
        }

        return DB::transaction(function () use ($wallet, $amountMinor) {
            $wallet = Wallet::whereKey($wallet->getKey())->lockForUpdate()->first();
            $wallet->currency_balance_minor += $amountMinor;
            $wallet->save();

            return $wallet;
        });
    }

    /**
     * Reverse a real-money credit (minor units) — a gateway refund or chargeback.
     * Clamps at zero so a reversal of already-spent funds never drives the cached
     * balance negative; the wallet_funding_transactions row carries the audit trail.
     */
    public function debitCurrency(Wallet $wallet, int $amountMinor): Wallet
    {
        if ($amountMinor <= 0) {
            throw new RuntimeException('Amount must be positive.');
        }

        return DB::transaction(function () use ($wallet, $amountMinor) {
            $wallet = Wallet::whereKey($wallet->getKey())->lockForUpdate()->first();
            $wallet->currency_balance_minor = max(0, $wallet->currency_balance_minor - $amountMinor);
            $wallet->save();

            return $wallet;
        });
    }

    /** Move coins between two wallets atomically (e.g. family → child). */
    public function transfer(Wallet $from, Wallet $to, int $amount, string $source, ?int $learnerId = null): void
    {
        DB::transaction(function () use ($from, $to, $amount, $source, $learnerId) {
            $this->debit($from, $amount, $source, $learnerId);
            $this->credit($to, $amount, $source, $learnerId);
        });
    }

    private function move(Wallet $wallet, string $type, int $amount, string $source, ?int $learnerId, ?Model $reference): CoinTransaction
    {
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be positive.');
        }

        return DB::transaction(function () use ($wallet, $type, $amount, $source, $learnerId, $reference) {
            $wallet = Wallet::whereKey($wallet->getKey())->lockForUpdate()->first();

            $delta = $type === 'credit' ? $amount : -$amount;
            $newBalance = $wallet->coin_balance + $delta;

            if ($newBalance < 0) {
                throw new RuntimeException('Insufficient coin balance.');
            }

            $wallet->coin_balance = $newBalance;
            $wallet->save();

            return CoinTransaction::create([
                'wallet_id' => $wallet->id,
                'learner_profile_id' => $learnerId,
                'type' => $type,
                'source' => $source,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
            ]);
        });
    }
}
