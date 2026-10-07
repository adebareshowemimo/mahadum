<?php

namespace Tests\Feature;

use App\Models\ClassEnrollment;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\LearnerProfile;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\SpeakingSubmission;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentReceipt;
use App\Models\TelcoBillingAttempt;
use App\Models\TelcoSubscription;
use App\Models\User;
use App\Services\Billing\PaymentService;
use App\Services\Settings;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MakesContent;
use Tests\TestCase;

class Batch3ReportingTest extends TestCase
{
    use MakesContent, RefreshDatabase;

    private function school(): array
    {
        $this->seedRbac();
        Notification::fake();
        $org = Organization::create(['name' => 'Reporting School', 'slug' => 'reporting', 'type' => 'school', 'status' => 'active']);
        $admin = $this->userWithRole('school_admin');
        $teacher = $this->userWithRole('teacher');
        $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
        $org->members()->attach($teacher->id, ['role' => 'teacher', 'status' => 'active']);
        $class = SchoolClass::create(['organization_id' => $org->id, 'name' => 'Alpha', 'teacher_user_id' => $teacher->id]);

        return [$org, $admin, $teacher, $class];
    }

    public function test_school_admin_and_own_teacher_create_audited_assignments_without_widening_grading(): void
    {
        [$org, $admin, $teacher, $class] = $this->school();
        $this->actingAsUser($admin);
        $this->getJson("/api/v1/classes/$class->id")->assertJsonPath('data.capabilities.create_assignment', true);
        $id = $this->postJson("/api/v1/classes/$class->id/assignments", ['title' => 'Greetings', 'instructions' => 'Write', 'coin_reward' => 50])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'class_assignment.created', 'actor_user_id' => $admin->id, 'organization_id' => $org->id]);
        $this->getJson("/api/v1/classes/$class->id/assignments/$id")->assertJsonPath('data.can_grade', false);
        $this->actingAsUser($teacher);
        $this->getJson("/api/v1/classes/$class->id/assignments/$id")->assertJsonPath('data.can_grade', true);
        $this->postJson("/api/v1/classes/$class->id/assignments", ['title' => 'Teacher work'])->assertCreated();
        $otherTeacher = $this->userWithRole('teacher');
        $org->members()->attach($otherTeacher->id, ['role' => 'teacher', 'status' => 'active']);
        $this->actingAsUser($otherTeacher);
        $this->getJson("/api/v1/classes/$class->id")->assertJsonPath('data.capabilities.create_assignment', false);
        $this->postJson("/api/v1/classes/$class->id/assignments", ['title' => 'Wrong owner'])->assertForbidden();
        $foreign = Organization::create(['name' => 'Foreign', 'slug' => 'foreign-reporting', 'type' => 'school', 'status' => 'active']);
        $foreignClass = SchoolClass::create(['organization_id' => $foreign->id, 'name' => 'Foreign']);
        $this->actingAsUser($admin);
        $this->postJson("/api/v1/classes/$foreignClass->id/assignments", ['title' => 'Foreign work'])->assertNotFound();
        $this->actingAsUser($this->userWithRole('parent'));
        $this->postJson("/api/v1/classes/$class->id/assignments", ['title' => 'Parent work'])->assertForbidden();
    }

    public function test_school_learning_is_weighted_includes_unstarted_targets_and_excludes_foreign_data(): void
    {
        [$org, $admin, , $class] = $this->school();
        $lesson = $this->publishedLesson();
        $level = $lesson->courseLevel;
        $level->lessons()->create(['title' => 'Lesson B', 'position' => 2, 'published_at' => now()]);
        $level->lessons()->create(['title' => 'Draft', 'position' => 3]);
        $ada = LearnerProfile::create(['organization_id' => $org->id, 'display_name' => 'Ada']);
        $bela = LearnerProfile::create(['organization_id' => $org->id, 'display_name' => 'Bela']);
        foreach ([$ada, $bela] as $learner) {
            Enrollment::create(['learner_profile_id' => $learner->id, 'course_id' => $level->course_id, 'status' => 'active']);
        }
        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $ada->id]);
        $beta = SchoolClass::create(['organization_id' => $org->id, 'name' => 'Beta']);
        foreach ([$ada, $bela] as $learner) {
            ClassEnrollment::create(['school_class_id' => $beta->id, 'learner_profile_id' => $learner->id]);
        }
        LessonProgress::create(['learner_profile_id' => $ada->id, 'lesson_id' => $lesson->id, 'status' => 'completed', 'score' => 80]);
        $quizId = $lesson->components->firstWhere('type', 'quiz')->quiz->id;
        foreach ([$ada->id => [0.5, 1], $bela->id => [0.9]] as $id => $scores) {
            foreach ($scores as $i => $score) {
                QuizAttempt::create(['learner_profile_id' => $id, 'quiz_id' => $quizId, 'attempt_no' => $i + 1, 'score' => $score, 'completed_at' => now()]);
            }
        }
        QuizAttempt::create(['learner_profile_id' => $ada->id, 'quiz_id' => $quizId, 'attempt_no' => 3, 'score' => 0, 'completed_at' => null]);
        QuizAttempt::create(['learner_profile_id' => $ada->id, 'quiz_id' => $quizId, 'attempt_no' => 4, 'score' => null, 'completed_at' => now()]);
        $speaking = $lesson->components->firstWhere('type', 'speaking');
        SpeakingSubmission::create(['learner_profile_id' => $ada->id, 'lesson_component_id' => $speaking->id, 'ai_score' => 30, 'status' => 'scored']);
        SpeakingSubmission::create(['learner_profile_id' => $bela->id, 'lesson_component_id' => $speaking->id, 'ai_score' => 90, 'status' => 'scored']);
        SpeakingSubmission::create(['learner_profile_id' => $ada->id, 'lesson_component_id' => $speaking->id, 'status' => 'needs_review']);
        $foreign = LearnerProfile::create(['display_name' => 'Foreign']);
        QuizAttempt::create(['learner_profile_id' => $foreign->id, 'quiz_id' => $quizId, 'attempt_no' => 1, 'score' => 1, 'completed_at' => now()]);
        SpeakingSubmission::create(['learner_profile_id' => $foreign->id, 'lesson_component_id' => $speaking->id, 'ai_score' => 200, 'status' => 'scored']);
        $this->actingAsUser($admin);
        $this->getJson("/api/v1/schools/$org->id/dashboard")->assertOk()
            ->assertJsonPath('data.students', 2)->assertJsonPath('data.learning.lesson_targets', 4)->assertJsonPath('data.learning.lessons_completed', 1)
            ->assertJsonPath('data.learning.completion_rate', 25)->assertJsonPath('data.learning.quiz_scored', 3)->assertJsonPath('data.learning.avg_quiz_score', 80)
            ->assertJsonPath('data.learning.avg_speaking_score', 60)->assertJsonPath('data.learning.speaking_scored', 2)
            ->assertJsonPath('data.learning.top_students.0.name', 'Ada')->assertJsonPath('data.learning.top_classes.0.name', 'Alpha');
        $this->getJson('/api/v1/admin/metrics')->assertForbidden();
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/admin/metrics')->assertOk()->assertJsonPath('data.language_analytics.0.learners', 2)
            ->assertJsonPath('data.language_analytics.0.lessons_completed', 1)->assertJsonPath('data.language_analytics.0.avg_quiz_score', 85)
            ->assertJsonPath('data.ai_analytics.scored', 3)->assertJsonPath('data.ai_analytics.needs_review', 1)->assertJsonPath('data.ai_analytics.scoring_status', 'deferred');
    }

    public function test_empty_metrics_do_not_invent_scores_or_full_completion(): void
    {
        [$org, $admin] = $this->school();
        $this->actingAsUser($admin);
        $this->getJson("/api/v1/schools/$org->id/dashboard")->assertOk()->assertJsonPath('data.learning.completion_rate', null)
            ->assertJsonPath('data.learning.avg_quiz_score', null)->assertJsonPath('data.learning.avg_speaking_score', null)->assertJsonCount(0, 'data.learning.top_classes');
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/admin/metrics')->assertJsonPath('data.revenue_minor', 0)->assertJsonPath('data.ai_analytics.avg_stored_score', null);
    }

    public function test_actual_subscription_receipts_and_partial_refunds_populate_revenue_once(): void
    {
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        Notification::fake();
        $buyer = $this->userWithRole('parent');
        $plan = Plan::where('code', 'premium_individual')->firstOrFail();
        $sub = Subscription::create(['subscriber_type' => User::class, 'subscriber_id' => $buyer->id, 'plan_id' => $plan->id, 'status' => 'pending', 'method' => 'card']);
        $payments = app(PaymentService::class);
        $this->assertSame('subscription_active', $payments->process('paystack', 'first', "sub_$sub->id", 'success', 12345, []));
        $this->assertSame('duplicate', $payments->process('paystack', 'first', "sub_$sub->id", 'success', 12345, []));
        $payments->process('paystack', 'renewal', "sub_$sub->id", 'success', 4567, []);
        $payments->process('paystack', 'partial-refund', "sub_$sub->id", 'refund', 1000, []);
        $payments->process('paystack', 'partial-refund', "sub_$sub->id", 'refund', 1000, []);
        $payments->process('paystack', 'unknown-amount', "sub_$sub->id", 'success', null, []);
        $payments->process('paystack', 'unmatched', 'no-account', 'success', 99999, []);
        $this->assertDatabaseCount('subscription_payment_receipts', 3);
        $this->assertDatabaseHas('subscription_payment_receipts', ['source_event' => 'paystack:partial-refund', 'direction' => 'refund', 'amount_minor' => 1000]);
        $org = Organization::create(['name' => 'Receipts', 'slug' => 'receipts', 'type' => 'school']);
        Invoice::create(['organization_id' => $org->id, 'amount_minor' => 2000, 'status' => 'paid']);
        Invoice::create(['organization_id' => $org->id, 'amount_minor' => 5000, 'status' => 'unpaid']);
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/admin/metrics')->assertJsonPath('data.revenue_channels.subscription_payments_minor', 15912)->assertJsonPath('data.revenue_minor', 17912);
        $this->assertSame(3, SubscriptionPaymentReceipt::count());
    }

    public function test_settlements_use_recorded_daily_attempts_and_existing_audited_percentage_setting(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        $this->seedRbac();
        $this->seed(PlanSeeder::class);
        $user = $this->userWithRole('parent');
        $sub = Subscription::create(['subscriber_type' => User::class, 'subscriber_id' => $user->id, 'plan_id' => Plan::first()->id, 'status' => 'active', 'method' => 'telco']);
        $telco = TelcoSubscription::create(['subscription_id' => $sub->id, 'msisdn' => '+2348012345678', 'operator' => 'mtn', 'daily_amount_minor' => 100, 'state' => 'active']);
        foreach (['success', 'success', 'error', 'insufficient'] as $result) {
            TelcoBillingAttempt::create(['telco_subscription_id' => $telco->id, 'attempted_at' => now(), 'amount_minor' => 100, 'result' => $result]);
        }
        TelcoBillingAttempt::create(['telco_subscription_id' => $telco->id, 'attempted_at' => now()->subDays(8), 'amount_minor' => 100, 'result' => 'success']);
        app(Settings::class)->set(['referral.commission_bps' => 500]);
        $this->actingAsUser($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/admin/settlements')->assertOk()->assertJsonPath('data.referral_commission_bps', 500)
            ->assertJsonPath('data.telco_revenue_minor', 300)->assertJsonPath('data.telco_share.platform_minor', null)
            ->assertJsonCount(7, 'data.daily_telco.days')->assertJsonPath('data.daily_telco.days.0.success_rate', null)
            ->assertJsonPath('data.daily_telco.days.6.attempts', 4)->assertJsonPath('data.daily_telco.days.6.success_rate', 50);
        $this->putJson('/api/v1/admin/settings', ['values' => ['referral.commission_bps' => 625]])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'system.settings_updated']);
        $this->getJson('/api/v1/admin/settlements')->assertJsonPath('data.referral_commission_bps', 625);
        $this->putJson('/api/v1/admin/settings', ['values' => ['referral.commission_bps' => 10001]])->assertUnprocessable();
        $this->actingAsUser($user);
        $this->putJson('/api/v1/admin/settings', ['values' => ['referral.commission_bps' => 100]])->assertForbidden();
        $this->getJson('/api/v1/admin/settlements')->assertForbidden();
    }
}
