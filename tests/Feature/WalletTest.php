<?php

namespace Tests\Feature;

use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_transfer_moves_coins_and_blocks_overdraft(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $family = $learner->family;

        // seed family wallet with 100 coins
        $wallets = app(WalletService::class);
        $wallets->credit($wallets->walletFor($family), 100, 'seed');

        $this->postJson('/api/v1/wallet/transfer', ['to_learner_id' => $learner->id, 'coins' => 30], [
            'Idempotency-Key' => 'tr-1',
        ])->assertOk()->assertJsonPath('data.family_balance', 70)->assertJsonPath('data.learner_balance', 30);

        // overdraft rejected
        $this->postJson('/api/v1/wallet/transfer', ['to_learner_id' => $learner->id, 'coins' => 1000], [
            'Idempotency-Key' => 'tr-2',
        ])->assertStatus(422);

        // ledger has the credit with running balance_after
        $this->assertDatabaseHas('coin_transactions', [
            'learner_profile_id' => $learner->id, 'source' => 'transfer', 'amount' => 30, 'balance_after' => 30,
        ]);
    }

    public function test_chore_approval_releases_coins_only_on_approve(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);

        $chore = $this->postJson('/api/v1/chores', [
            'title' => 'Tidy', 'assignee_learner_profile_id' => $learner->id, 'coin_reward' => 15,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/chores/$chore/review", ['decision' => 'approve'])
            ->assertOk()->assertJsonPath('data.coins_released', 15);

        $this->assertDatabaseHas('coin_transactions', [
            'learner_profile_id' => $learner->id, 'source' => 'chore', 'amount' => 15,
        ]);
    }

    public function test_fund_creates_pending_transaction_without_crediting(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'], [
            'Idempotency-Key' => 'f-1',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('wallet_funding_transactions', ['amount_minor' => 50000, 'status' => 'pending']);
    }

    public function test_money_post_requires_idempotency_key(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->parentWithChild($parent);

        $this->postJson('/api/v1/wallet/fund', ['amount' => 50000, 'gateway' => 'paystack'])
            ->assertStatus(422)->assertJsonPath('error.code', 'idempotency_key_required');
    }
}
