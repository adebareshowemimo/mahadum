<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_referrer_invites_by_email_and_phone(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $this->actingAsUser($referrer);

        $this->postJson('/api/v1/referrals/invitations', ['channel' => 'email', 'contact' => 'Friend@Example.com'])
            ->assertCreated()
            ->assertJsonPath('data.channel', 'email')
            ->assertJsonPath('data.contact', 'friend@example.com');

        $this->postJson('/api/v1/referrals/invitations', ['channel' => 'phone', 'contact' => '0803 000 1111'])
            ->assertCreated()
            ->assertJsonPath('data.contact', '2348030001111');

        $this->getJson('/api/v1/referrals/invitations')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cannot_invite_an_existing_active_account(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $existing = $this->userWithRole('parent', ['email' => 'already@here.com', 'phone' => '+2348099998888']);
        $existing->update(['status' => 'active']);
        $this->actingAsUser($referrer);

        $this->postJson('/api/v1/referrals/invitations', ['channel' => 'email', 'contact' => 'already@here.com'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'account_exists');

        $this->postJson('/api/v1/referrals/invitations', ['channel' => 'phone', 'contact' => '08099998888'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'account_exists');
    }

    public function test_signup_is_matched_back_to_the_invitation(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer)->code;

        app(ReferralService::class)->invite($referrer, 'email', 'invitee@test.local');

        $this->postJson('/api/v1/auth/register', [
            'first_name' => 'In', 'last_name' => 'Vitee', 'email' => 'invitee@test.local', 'phone' => '+2348012341234',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'device_name' => 'd',
            'referral_code' => $code,
        ], ['X-Device-Id' => 'inv-dev'])->assertCreated();

        $referred = User::where('email', 'invitee@test.local')->first();
        $referral = Referral::where('referred_user_id', $referred->id)->firstOrFail();

        $this->assertSame('email', $referral->contact_channel);
        $this->assertSame('invitee@test.local', $referral->contact_value);
        $this->assertDatabaseHas('referral_invitations', ['contact' => 'invitee@test.local', 'status' => 'accepted']);
    }

    public function test_activations_list_is_searchable_by_contact(): void
    {
        $this->seedRbac();
        $referrer = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($referrer);

        $a = $this->userWithRole('parent', ['email' => 'ada@test.local', 'phone' => '+2348010000001']);
        $b = $this->userWithRole('parent', ['email' => 'ben@test.local', 'phone' => '+2348010000002']);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $a->id, 'status' => 'qualified',
            'signed_up_at' => now(), 'activated_at' => now(), 'contact_channel' => 'phone', 'contact_value' => '2348010000001']);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $b->id, 'status' => 'qualified',
            'signed_up_at' => now(), 'activated_at' => now(), 'contact_channel' => 'email', 'contact_value' => 'ben@test.local']);

        $this->actingAsUser($referrer);
        $this->getJson('/api/v1/referrals/activations')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/referrals/activations?search=ben@test.local')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.via_email', 'ben@test.local');
        $this->getJson('/api/v1/referrals/activations?search=2348010000001')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.via_phone', '2348010000001');
    }

    public function test_activity_includes_pending_signups_both_contacts_and_later_pages_without_other_owners(): void
    {
        $this->seedRbac();
        $owner = $this->userWithRole('parent');
        $code = app(ReferralService::class)->codeFor($owner);
        $legacyCode = $code->replicate();
        $legacyCode->code = 'LEGACYOWNER';
        $legacyCode->save();
        $otherCode = app(ReferralService::class)->codeFor($this->userWithRole('parent'));
        $active = $this->userWithRole('parent', ['email' => 'active@example.test', 'phone' => '+2348011111111', 'last_login_at' => now()]);
        $pending = $this->userWithRole('parent', ['email' => 'pending@example.test', 'phone' => '+2348022222222']);
        Referral::create(['referral_code_id' => $otherCode->id, 'referred_user_id' => $otherCode->owner_id, 'status' => 'signed_up', 'signed_up_at' => now()]);
        Referral::create(['referral_code_id' => $legacyCode->id, 'referred_user_id' => $active->id, 'status' => 'qualified',
            'signed_up_at' => now()->subDay(), 'activated_at' => now(), 'contact_channel' => 'email', 'contact_value' => $active->email]);
        Referral::create(['referral_code_id' => $code->id, 'referred_user_id' => $pending->id, 'status' => 'signed_up',
            'signed_up_at' => now(), 'contact_channel' => 'phone', 'contact_value' => null]);
        $this->actingAsUser($owner);
        $this->getJson('/api/v1/referrals/activations?per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.activated_at', null)
            ->assertJsonPath('data.0.via_email', $pending->email)->assertJsonPath('data.0.via_phone', $pending->phone);
        $this->getJson('/api/v1/referrals/activations?per_page=1&page=2')
            ->assertOk()->assertJsonPath('data.0.sn', 2)->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.activated_at', now()->toDateString())
            ->assertJsonPath('data.0.via_email', $active->email)->assertJsonPath('data.0.via_phone', $active->phone);
        $this->getJson('/api/v1/referrals/summary')->assertOk()
            ->assertJsonPath('data.referrals.qualified', 1)->assertJsonPath('data.referrals.signed_up', 1);
        $this->getJson('/api/v1/referrals/activations?search=pending@example.test')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');
    }
}
