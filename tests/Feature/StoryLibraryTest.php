<?php

namespace Tests\Feature;

use App\Models\Family;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\Story;
use App\Models\StoryPurchase;
use App\Services\Billing\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class StoryLibraryTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    public function test_story_authoring_uses_its_own_language_and_level(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $igbo = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $yoruba = Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);

        $id = $this->postJson('/api/v1/content/stories', [
            'title' => 'The Moon', 'language_id' => $igbo->id, 'standalone_level' => 2,
            'age_band' => '13_17', 'format' => 'reading',
        ])->assertCreated()->assertJsonPath('data.language', 'ig')->assertJsonPath('data.level', 2)->json('data.id');
        $this->assertNull(Story::findOrFail($id)->course_id);

        $this->patchJson("/api/v1/content/stories/{$id}", [
            'language_id' => $yoruba->id, 'standalone_level' => 3,
        ])->assertOk()->assertJsonPath('data.language', 'yo')->assertJsonPath('data.level', 3);
        $this->getJson('/api/v1/content/stories')->assertOk()->assertJsonPath('data.0.level', 3);
    }

    public function test_each_story_band_supports_video_text_and_server_graded_quiz(): void
    {
        $this->seedRbac();
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);

        foreach (['0_5', '6_12', '13_17', '18_plus', 'folklore'] as $band) {
            foreach (['video', 'text', 'quiz'] as $format) {
                $id = $this->postJson('/api/v1/content/stories', [
                    'title' => "{$band} {$format}", 'language_id' => $language->id,
                    'standalone_level' => 1, 'age_band' => $band, 'format' => $format,
                ])->assertCreated()->json('data.id');
                $story = Story::findOrFail($id);
                if ($format === 'video') {
                    $story->update(['preview_video_path' => 'preview.mp4', 'full_video_path' => 'full.mp4']);
                } elseif ($format === 'text') {
                    foreach ([1, 2] as $position) {
                        $story->pages()->create(['position' => $position, 'body' => "Chapter {$position}"]);
                    }
                } else {
                    foreach ([1, 2] as $position) {
                        $this->postJson("/api/v1/content/stories/{$id}/questions", [
                            'prompt' => "Question {$position}?", 'options' => ['Yes', 'No'], 'correct_index' => 0,
                        ])->assertCreated();
                    }
                }
                $this->postJson("/api/v1/content/stories/{$id}/publish")->assertOk();
            }
        }
        $this->assertSame(15, Story::whereNotNull('published_at')->count());

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $quiz = Story::where('title', '0_5 quiz')->firstOrFail();
        $payload = $this->getJson("/api/v1/learning/stories/{$quiz->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonCount(1, 'data.questions')->json('data.questions.0');
        $this->assertArrayNotHasKey('correct_index', $payload);
        $this->postJson("/api/v1/learning/stories/{$quiz->id}/answer", [
            'learner_id' => $child->id, 'question_id' => $payload['id'], 'option_index' => 0,
        ])->assertOk()->assertJsonPath('data.correct', true);
        $second = $quiz->questions()->skip(1)->firstOrFail();
        $this->postJson("/api/v1/learning/stories/{$quiz->id}/answer", [
            'learner_id' => $child->id, 'question_id' => $second->id, 'option_index' => 0,
        ])->assertForbidden();
    }

    public function test_school_admin_can_open_story_and_set_builders_read_only(): void
    {
        $this->seedRbac();
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $this->actingAsUser($this->userWithRole('content_owner'));
        $storyId = $this->postJson('/api/v1/content/stories', [
            'title' => 'School catalogue', 'language_id' => $language->id,
            'standalone_level' => 1, 'age_band' => 'folklore', 'format' => 'text',
        ])->assertCreated()->json('data.id');
        $setId = $this->postJson('/api/v1/library-lessons', [
            'title' => 'School proverb', 'kind' => 'proverb', 'language_id' => $language->id,
            'standalone_level' => 1, 'summary' => 'A saying to read.',
        ])->assertCreated()->json('data.id');

        $this->actingAsUser($this->userWithRole('school_admin'));
        $this->getJson('/api/v1/content/stories')->assertOk();
        $this->getJson("/api/v1/content/stories/{$storyId}")->assertOk();
        $this->getJson('/api/v1/library-lessons?kind=proverb')->assertOk();
        $this->getJson("/api/v1/lessons/{$setId}")->assertOk();
        $this->postJson('/api/v1/content/stories', [
            'title' => 'Not allowed', 'language_id' => $language->id,
            'standalone_level' => 1, 'age_band' => 'folklore', 'format' => 'quiz',
        ])->assertForbidden();
    }

    public function test_story_video_uploads_can_be_previewed_and_published(): void
    {
        $this->seedRbac();
        Storage::fake('local');
        $this->actingAsUser($this->userWithRole('content_owner'));
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $id = $this->postJson('/api/v1/content/stories', [
            'title' => 'Video tale', 'language_id' => $language->id,
            'standalone_level' => 1, 'age_band' => '0_5', 'format' => 'video',
        ])->assertCreated()->json('data.id');
        foreach (['preview_video', 'full_video'] as $slot) {
            $this->postJson("/api/v1/content/stories/{$id}/upload", [
                'slot' => $slot, 'file' => UploadedFile::fake()->create("{$slot}.mp4", 100, 'video/mp4'),
            ])->assertOk();
        }
        $this->postJson("/api/v1/content/stories/{$id}/publish")->assertOk();
        $this->getJson("/api/v1/content/stories/{$id}")
            ->assertOk()->assertJsonPath('data.has_preview_video', true)->assertJsonPath('data.has_full_video', true);
    }

    private function story(string $band = '0_5'): Story
    {
        $language = Language::create(['code' => 'ig', 'name' => 'Igbo', 'script' => 'latin', 'is_active' => true]);
        $story = Story::create(['course_id' => null, 'language_id' => $language->id, 'standalone_level' => 2, 'title' => 'The Moon', 'age_band' => $band,
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

        $this->getJson("/api/v1/learning/stories?learner_id={$child->id}&language=ig")
            ->assertOk()->assertJsonPath('data.0.level', 2);
        $this->getJson("/api/v1/learning/stories?learner_id={$child->id}&language=yo")
            ->assertOk()->assertJsonCount(0, 'data');

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
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', true);
        $this->assertSame('reversed', $payment->process('paystack', 'refunded-story', $purchase->reference,
            'refund', Story::PRICE_MINOR, ['data' => ['currency' => 'NGN']]));
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', false)->assertJsonCount(12, 'data.pages');
    }

    public function test_parent_can_buy_but_a_minor_learner_cannot_start_checkout(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        $story = $this->story();

        $this->actingAsUser($parent);
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.can_purchase', true);

        $student = $this->userWithRole('student');
        $studentProfile = LearnerProfile::create([
            'user_id' => $student->id, 'display_name' => 'Minor learner',
            'date_of_birth' => now()->subYears(13),
        ]);
        $this->actingAsUser($student);
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$studentProfile->id}")
            ->assertOk()->assertJsonPath('data.can_purchase', false);
        $this->postJson("/api/v1/learning/stories/{$story->id}/purchase", [
            'learner_id' => $studentProfile->id, 'provider' => 'paystack',
        ], ['Idempotency-Key' => 'minor-story-purchase'])->assertForbidden();
        $this->assertDatabaseCount('story_purchases', 0);
    }

    public function test_parent_checkout_return_verifies_payment_and_unlocks_story_for_the_family(): void
    {
        $this->seedRbac();
        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $child = $this->parentWithChild($parent);
        $story = $this->story();
        config([
            'services.payments.live' => true,
            'services.paystack.secret' => 'sk_test',
            'services.paystack.base_url' => 'https://api.paystack.co',
            'app.frontend_url' => 'https://learn.example.test',
        ]);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/story'],
            ]),
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true, 'data' => ['status' => 'success', 'amount' => Story::PRICE_MINOR, 'currency' => 'NGN'],
            ]),
        ]);

        $purchaseId = $this->postJson("/api/v1/learning/stories/{$story->id}/purchase", [
            'learner_id' => $child->id, 'provider' => 'paystack',
        ], ['Idempotency-Key' => 'parent-story-purchase'])->assertCreated()->assertJsonPath('data.checkout_url', 'https://checkout.paystack.test/story')
            ->json('data.id');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transaction/initialize')
            && $request['amount'] === Story::PRICE_MINOR
            && $request['callback_url'] === "https://learn.example.test/learn/stories/{$story->id}?purchase_id={$purchaseId}");

        $this->postJson("/api/v1/learning/story-purchases/{$purchaseId}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', true)->assertJsonCount(13, 'data.pages');
        $this->postJson("/api/v1/learning/stories/{$story->id}/purchase", [
            'learner_id' => $child->id, 'provider' => 'paystack',
        ], ['Idempotency-Key' => 'parent-story-purchase-retry'])->assertOk()->assertJsonPath('data.id', $purchaseId)->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('story_purchases', 1);
    }

    public function test_teacher_can_read_a_paid_school_learners_story_but_cannot_buy_it(): void
    {
        $this->seedRbac();
        $parent = $this->userWithRole('parent');
        $child = $this->parentWithChild($parent);
        $school = Organization::create(['name' => 'School', 'slug' => 'story-teacher-school', 'type' => 'school', 'status' => 'active']);
        $child->update(['organization_id' => $school->id]);
        $teacher = $this->userWithRole('teacher');
        $school->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $story = $this->story();
        StoryPurchase::create([
            'story_id' => $story->id, 'user_id' => $parent->id,
            'provider' => 'paystack', 'status' => 'paid',
            'amount_minor' => Story::PRICE_MINOR, 'reference' => 'story_teacher_view',
        ]);

        $this->actingAsUser($teacher);
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$child->id}")
            ->assertOk()->assertJsonPath('data.purchased', true)
            ->assertJsonPath('data.can_purchase', false)->assertJsonCount(13, 'data.pages');
        $this->postJson("/api/v1/learning/stories/{$story->id}/purchase", [
            'learner_id' => $child->id, 'provider' => 'paystack',
        ], ['Idempotency-Key' => 'teacher-story-purchase'])->assertForbidden();
    }

    public function test_adult_learner_can_purchase_for_self_at_eighteen(): void
    {
        $this->seedRbac();
        $story = $this->story();
        $adult = $this->actingAsUser($this->userWithRole('student'));
        $profile = LearnerProfile::create([
            'user_id' => $adult->id, 'display_name' => 'Adult learner',
            'date_of_birth' => now()->subYears(18),
        ]);

        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$profile->id}")
            ->assertOk()->assertJsonPath('data.can_purchase', true);
        $profile->update(['date_of_birth' => now()->subYears(18)->addDay()]);
        $this->getJson("/api/v1/learning/stories/{$story->id}?learner_id={$profile->id}")
            ->assertOk()->assertJsonPath('data.can_purchase', false);
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

    public function test_creator_can_preview_full_draft_media_without_creating_a_purchase(): void
    {
        $this->seedRbac();
        $author = $this->actingAsUser($this->userWithRole('content_owner'));
        $story = $this->story();
        $story->update(['published_at' => null]);
        $story->pages()->where('position', 13)->update(['image_path' => 'stories/draft-page.svg']);
        Storage::fake('local');
        Storage::disk('local')->put('stories/draft-page.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $response = $this->getJson("/api/v1/content/stories/{$story->id}")
            ->assertOk()->assertJsonCount(13, 'data.pages');
        $imageUrl = $response->json('data.pages.12.image_url');
        $this->assertIsString($imageUrl);
        $this->get($imageUrl)->assertOk();
        $this->assertSame(0, StoryPurchase::count());

        $parent = $this->actingAsUser($this->userWithRole('parent'));
        $this->getJson("/api/v1/content/stories/{$story->id}")->assertForbidden();
        $parentLink = URL::temporarySignedRoute('stories.authorAsset', now()->addMinutes(5), [
            'story' => $story->id, 'viewer' => $parent->id, 'slot' => 'page', 'page' => $story->pages()->first()->id,
        ], absolute: false);
        $this->get($parentLink)->assertForbidden();
        $this->assertNotEquals($author->id, $parent->id);
    }
}
