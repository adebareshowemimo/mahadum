<?php

namespace Tests\Feature;

use App\Models\CoinTransaction;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Services\Family\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class WalletRewardFundingTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public static function sources(): array
    {
        return [['chore'], ['assignment'], ['study']];
    }

    #[DataProvider('sources')]
    public function test_parent_funding_conserves_coins_and_repeated_release_is_safe(string $source): void
    {
        $this->seedRbac();
        $learner = $this->parentWithChild($this->userWithRole('parent'));
        $reference = $this->publishedLesson();
        $wallets = app(WalletService::class);
        try {
            $wallets->rewardFromParent($learner, 10, $source, $reference);
            $this->fail('An unfunded reward must remain unpaid.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('coin_transactions', 0);
        $wallets->credit($wallets->walletFor($learner->family), 10, 'test_seed');
        $wallets->rewardFromParent($learner, 10, $source, $reference);
        $wallets->rewardFromParent($learner, 10, $source, $reference);
        $this->assertSame(0, $wallets->walletFor($learner->family)->coin_balance);
        $this->assertSame(10, $wallets->walletFor($learner)->coin_balance);
        $this->assertSame(2, CoinTransaction::where('source', $source)->where('reference_type', Lesson::class)->where('reference_id', $reference->id)->count());
        $this->assertSame(0, CoinTransaction::where('source', $source)->get()->sum(fn ($entry) => $entry->type === 'debit' ? -$entry->amount : $entry->amount));
    }

    public function test_stale_family_relationship_cannot_release_a_reward_from_the_old_household(): void
    {
        $this->seedRbac();
        $learner = $this->parentWithChild($this->userWithRole('parent'));
        $other = $this->parentWithChild($this->userWithRole('parent'));
        $wallets = app(WalletService::class);
        $original = $wallets->walletFor($learner->family);
        $destination = $wallets->walletFor($other->family);
        $wallets->credit($original, 20, 'test_seed');
        $wallets->credit($destination, 20, 'test_seed');
        // Keep the service caller's profile and loaded family stale.
        LearnerProfile::whereKey($learner->id)->update(['family_id' => $other->family_id]);
        try {
            $wallets->rewardFromParent($learner, 10, 'study', $this->publishedLesson());
            $this->fail('A stale household must require a fresh review.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(20, $original->fresh()->coin_balance);
        $this->assertSame(20, $destination->fresh()->coin_balance);
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'study']);
    }

    public function test_deleted_learner_cannot_receive_a_reward_from_a_stale_service_caller(): void
    {
        $this->seedRbac();
        $learner = $this->parentWithChild($this->userWithRole('parent'));
        $wallets = app(WalletService::class);
        $parent = $wallets->walletFor($learner->family);
        $wallets->credit($parent, 20, 'test_seed');
        LearnerProfile::whereKey($learner->id)->delete();
        try {
            $wallets->rewardFromParent($learner, 10, 'study', $this->publishedLesson());
            $this->fail('A deleted learner must not receive a reward.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(20, $parent->fresh()->coin_balance);
        $this->assertDatabaseMissing('coin_transactions', ['source' => 'study']);
    }
}
