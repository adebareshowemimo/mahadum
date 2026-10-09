<?php

namespace Tests\Feature;

use App\Http\Controllers\Referral\PayoutController;
use App\Models\AuditLog;
use App\Models\Payout;
use App\Models\User;
use App\Notifications\PayoutApproved;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayoutReviewIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function requestedPayout(): Payout
    {
        $payout = new Payout(['amount_minor' => 500_000, 'method' => 'bank', 'status' => 'requested', 'requested_at' => now()]);
        $payout->beneficiary()->associate(User::factory()->create());
        $payout->save();

        return $payout;
    }

    public static function staleReviews(): array
    {
        return [['approve', 'approved'], ['approve', 'rejected'], ['reject', 'approved'], ['reject', 'rejected']];
    }

    #[DataProvider('staleReviews')]
    public function test_stale_review_cannot_overwrite_an_existing_decision(string $action, string $status): void
    {
        $this->seedRbac();
        Notification::fake();
        $admin = $this->userWithRole('super_admin');
        $payout = $this->requestedPayout();
        $stale = $payout->fresh();
        $payout->update(['status' => $status, 'approved_by' => $status === 'approved' ? $admin->id : null]);
        $request = Request::create('/payout-review', 'POST');
        $request->setUserResolver(fn () => $admin);

        $response = app(PayoutController::class)->{$action}($request, $stale);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('payout_not_pending', $response->getData(true)['error']['code']);
        $this->assertSame($status, $payout->fresh()->status);
        $this->assertSame($status === 'approved' ? $admin->id : null, $payout->fresh()->approved_by);
        $this->assertDatabaseCount('audit_logs', 0);
        Notification::assertNothingSent();
    }

    public static function decisions(): array
    {
        return [['approve', 'approved'], ['reject', 'rejected']];
    }

    #[DataProvider('decisions')]
    public function test_audit_failure_rolls_back_the_decision_and_allows_one_retry(string $action, string $status): void
    {
        $this->seedRbac();
        Notification::fake();
        $admin = $this->actingAsUser($this->userWithRole('super_admin'));
        $payout = $this->requestedPayout();
        $failAudit = true;
        $this->mock(AuditLogger::class)->shouldReceive('record')->twice()->andReturnUsing(function (...$arguments) use (&$failAudit) {
            if ($failAudit) {
                $failAudit = false;
                throw new \RuntimeException('Simulated audit failure');
            }

            return (new AuditLogger)->record(...$arguments);
        });
        $url = "/api/v1/admin/payouts/{$payout->id}/{$action}";

        $this->postJson($url)->assertStatus(500);

        $this->assertSame('requested', $payout->fresh()->status);
        $this->assertNull($payout->fresh()->approved_by);
        $this->assertDatabaseCount('audit_logs', 0);
        Notification::assertNothingSent();

        $this->postJson($url)->assertOk()->assertJsonPath('data.status', $status);
        $this->assertSame($action === 'approve' ? $admin->id : null, $payout->fresh()->approved_by);
        $this->assertSame(1, AuditLog::where('action', 'payout.'.$status)->where('subject_id', $payout->id)->count());
        $this->postJson($url)->assertStatus(409);
        if ($action === 'approve') {
            Notification::assertSentToTimes($payout->beneficiary, PayoutApproved::class, 1);
        } else {
            Notification::assertNothingSent();
        }
    }

    public function test_notification_failure_does_not_turn_an_audited_approval_into_an_error(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $payout = $this->requestedPayout();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated queue failure'));

        $url = "/api/v1/admin/payouts/{$payout->id}/approve";
        $this->postJson($url)->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertSame('approved', $payout->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'payout.approved')->where('subject_id', $payout->id)->count());
        $this->postJson($url)->assertStatus(409);
    }
}
