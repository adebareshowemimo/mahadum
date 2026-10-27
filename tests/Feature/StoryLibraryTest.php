<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Family;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class StoryLibraryTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function story(string $band = '0_5'): Story
    {
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $course = Course::create(['language_id' => $language->id, 'title' => 'Stories', 'status' => 'published', 'is_published' => true]);
        $story = Story::create(['course_id' => $course->id, 'title' => 'The Moon', 'age_band' => $band,
            'format' => $band === '0_5' ? 'colouring' : 'reading', 'price_minor' => Story::PRICE_MINOR, 'published_at' => now()]);
        foreach (range(1, 13) as $number) {
            $story->pages()->create(['position' => $number, 'title' => "Page {$number}", 'body' => "Text {$number}"]);
        }

        return $story;
    }

    public function test_free_preview_is_limited_and_parent_purchase_unlocks_all_children_until_refund(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $sibling = LearnerProfile::create(['family_id' => $child->family_id, 'display_name' => 'Sibling']);
        $story = $this->story();

        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', false)->assertJsonCount(12, 'data.pages');

        $purchase = StoryPurchase::create(['story_id' => $story->id, 'user_id' => $parent->id,
            'provider' => 'paystack', 'status' => 'pending', 'amount_minor' => Story::PRICE_MINOR, 'reference' => 'story_test']);
        $payment = app(PaymentService::class);
        $this->assertSame('ignored', $payment->process('paystack', 'wrong-currency', $purchase->reference,
            'success', Story::PRICE_MINOR, ['data' => ['currency' => 'USD']]));
        $this->assertSame('story_purchased', $payment->process('paystack', 'paid-story', $purchase->reference,
            'success', Story::PRICE_MINOR, ['data' => ['currency' => 'NGN']]));

        foreach ([$child, $sibling] as $learner) {
            $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$learner->id}")
                ->assertOk()->assertJsonPath('data.purchased', true)->assertJsonCount(13, 'data.pages');
        }
        $story->course->update(['is_published' => false, 'status' => 'draft']);
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', true);
        $this->assertSame('reversed', $payment->process('paystack', 'refunded-story', $purchase->reference,
            'refund', Story::PRICE_MINOR, ['data' => ['currency' => 'NGN']]));
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', false)->assertJsonCount(12, 'data.pages');
    }

    public function test_adult_stories_start_at_eighteen_and_are_hidden_from_school_profiles(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $family = Family::create(['owner_user_id' => $parent->id, 'name' => 'Family']);
        $minor = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Seventeen', 'date_of_birth' => now()->subYears(18)->addDay()]);
        $adult = LearnerProfile::create(['family_id' => $family->id, 'display_name' => 'Eighteen', 'date_of_birth' => now()->subYears(18)]);
        $story = $this->story('18_plus');

        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$minor->id}")->assertNotFound();
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$adult->id}")->assertOk();
        $school = Organization::create(['name' => 'School', 'slug' => 'story-test-school', 'type' => 'school', 'status' => 'active']);
        $adult->update(['organization_id' => $school->id]);
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$adult->id}")->assertNotFound();
    }

    public function test_story_settlement_accepts_verified_naira_from_all_three_gateways(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $story = $this->story();
        $payment = app(PaymentService::class);
        foreach (['paystack', 'flutterwave', 'monnify'] as $provider) {
            $purchase = StoryPurchase::create(['story_id' => $story->id, 'user_id' => $parent->id,
                'provider' => $provider, 'status' => 'pending', 'amount_minor' => Story::PRICE_MINOR,
                'reference' => 'story_'.$provider]);
            $raw = $provider === 'monnify'
                ? ['eventData' => ['currencyCode' => 'NGN']]
                : ['data' => ['currency' => 'NGN'] + ($provider === 'flutterwave' ? ['flw_ref' => 'flw-story-ref'] : [])];
            $this->assertSame('story_purchased', $payment->process($provider, 'event-'.$provider,
                $purchase->reference, 'success', Story::PRICE_MINOR, $raw));
            $this->assertSame('paid', $purchase->fresh()->status);
            if ($provider === 'flutterwave') {
                $this->assertSame('reversed', $payment->process('flutterwave', 'chargeback-lost', 'flw-story-ref',
                    'refund', Story::PRICE_MINOR, ['event' => 'chargeback.lost', 'data' => ['amount' => 500]]));
                $this->assertSame('refunded', $purchase->fresh()->status);
            }
        }
    }

    public function test_full_video_asset_requires_a_paid_story_even_with_a_valid_signed_link(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $learner = $this->parentWithChild($parent);
        $story = $this->story();
        $story->update(['format' => 'animated', 'age_band' => '6_12',
            'preview_video_path' => 'stories/preview.mp4', 'full_video_path' => 'stories/full.mp4']);
        Storage::fake('local');
        Storage::disk('local')->put('stories/preview.mp4', 'preview');
        Storage::disk('local')->put('stories/full.mp4', 'full');

        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$learner->id}")
            ->assertOk()->assertJsonPath('data.full_video_url', null);
        $url = URL::temporarySignedRoute('stories.asset', now()->addMinutes(5), [
            'story' => $story->id, 'viewer' => $parent->id, 'learner' => $learner->id, 'slot' => 'full',
        ], absolute: false);
        $this->get($url)->assertNotFound();

        StoryPurchase::create(['story_id' => $story->id, 'user_id' => $parent->id,
            'provider' => 'paystack', 'status' => 'paid', 'amount_minor' => Story::PRICE_MINOR, 'reference' => 'story_video']);
        $this->get($url)->assertOk();
    }
}
