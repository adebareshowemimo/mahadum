<?php

namespace Tests\Feature;

use App\Models\CoinTransaction;
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
}
