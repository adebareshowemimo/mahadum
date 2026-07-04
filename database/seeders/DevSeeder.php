<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\ClassEnrollment;
use App\Models\Commission;
use App\Models\ComponentProgress;
use App\Models\Course;
use App\Models\CourseLevel;
use App\Models\DataBundlePurchase;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Heart;
use App\Models\Language;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\LearnerBadge;
use App\Models\LearnerProfile;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Plan;
use App\Models\PromoCode;
use App\Models\Referral;
use App\Models\SchoolClass;
use App\Models\SeatAllocation;
use App\Models\Streak;
use App\Models\Subscription;
use App\Models\TelcoBillingAttempt;
use App\Models\User;
use App\Models\WalletFundingTransaction;
use App\Models\XpLedger;
use App\Notifications\PayoutApproved;
use App\Notifications\SubscriptionActivated;
use App\Services\Family\WalletService;
use App\Services\Learning\PathBuilder;
use App\Services\Learning\XapiRecorder;
use App\Services\Referral\ReferralService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Large, realistic dataset for developing the React web app against live
 * endpoints. Builds a content catalogue, families + learners with progress and
 * gamification, schools with classes/seats/invoices, billing, referrals, and
 * engagement (notifications + xAPI). Run on demand:
 *
 *   php artisan db:seed --class=DevSeeder
 *
 * Idempotent guard: re-running is a no-op once seeded (delete the DB to reseed).
 * All demo logins use password "Password123!".
 */
class DevSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    private const FAMILIES = 25;

    private const SCHOOLS = 6;

    private const COURSES_PER_LANGUAGE = 2;

    public function run(): void
    {
        if (User::where('email', 'super@dev.mahadum360')->exists()) {
            $this->command?->warn('DevSeeder already run — skipping. Drop the database to reseed.');

            return;
        }

        $this->prerequisites();
        $this->knownLogins();
        $courses = $this->catalogue();
        $this->families($courses);
        $this->leaderboard();
        $this->schools();
        $this->billing();
        $this->referralsAndPayouts();
        $this->promoCodes();
        $this->engagement();

        $this->command?->info('DevSeeder complete. Logins (password "Password123!"):');
        foreach (['super@dev.mahadum360 (super_admin)', 'owner@dev.mahadum360 (content_owner)', 'admin1@dev.mahadum360 (school_admin)', 'teacher1@dev.mahadum360 (teacher)', 'parent1@dev.mahadum360 (parent)'] as $line) {
            $this->command?->line("  • {$line}");
        }
    }

    private function prerequisites(): void
    {
        if (! Role::query()->exists()) {
            $this->call(RolesAndPermissionsSeeder::class);
        }
        if (! Plan::query()->exists()) {
            $this->call(PlanSeeder::class);
        }
        if (! Language::query()->exists()) {
            $this->call(LanguageSeeder::class);
        }
        if (! Badge::query()->exists()) {
            $this->call(BadgeSeeder::class);
        }
    }

    private function user(string $email, string $role, string $first, string $last): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['first_name' => $first, 'last_name' => $last, 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now(), 'phone' => '080'.fake()->numerify('########')],
        );
        $user->assignRole($role);

        return $user;
    }

    private function knownLogins(): void
    {
        $this->user('super@dev.mahadum360', 'super_admin', 'Sade', 'Balogun');
        $this->user('owner@dev.mahadum360', 'content_owner', 'Emeka', 'Nwosu');
        for ($i = 1; $i <= self::SCHOOLS; $i++) {
            $this->user("admin{$i}@dev.mahadum360", 'school_admin', fake()->firstName(), fake()->lastName());
        }
        for ($i = 1; $i <= 12; $i++) {
            $this->user("teacher{$i}@dev.mahadum360", 'teacher', fake()->firstName(), fake()->lastName());
        }
    }

    /** @return Collection<int, Course> */
    private function catalogue(): Collection
    {
        $owner = User::where('email', 'owner@dev.mahadum360')->first();
        $courses = collect();

        foreach (Language::where('is_active', true)->get() as $language) {
            for ($c = 1; $c <= self::COURSES_PER_LANGUAGE; $c++) {
                $course = Course::create([
                    'owner_user_id' => $owner?->id,
                    'language_id' => $language->id,
                    'title' => "{$language->name} ".fake()->randomElement(['Basics', 'Everyday', 'Greetings', 'Travel', 'Family']),
                    'description' => fake()->sentence(10),
                    'level_band' => fake()->randomElement(['A1', 'A2', 'B1']),
                    'status' => 'published',
                    'is_published' => true,
                ]);

                foreach (range(1, 2) as $levelPos) {
                    $level = $course->levels()->create(['title' => "Unit {$levelPos}", 'position' => $levelPos]);
                    foreach (range(1, 3) as $lessonPos) {
                        $this->buildLesson($level, $lessonPos);
                    }
                }
                $courses->push($course);
            }
        }

        return $courses;
    }

    private function buildLesson(CourseLevel $level, int $position): Lesson
    {
        $lesson = $level->lessons()->create([
            'title' => fake()->randomElement(['Saying hello', 'Numbers 1–10', 'My family', 'At the market', 'Colours', 'Days of the week']),
            'position' => $position,
            'est_minutes' => fake()->numberBetween(4, 12),
            'published_at' => now(),
            'is_locked_by_default' => $position > 1,
        ]);

        $video = $lesson->components()->create(['type' => 'video', 'position' => 1, 'xp_value' => 5, 'title' => 'Watch']);
        $video->video()->create(['title' => 'Lesson video', 'duration_seconds' => fake()->numberBetween(40, 180), 'status' => 'ready', 'kind' => 'lesson']);

        $quizC = $lesson->components()->create(['type' => 'quiz', 'position' => 2, 'xp_value' => 10, 'title' => 'Quiz']);
        $quiz = $quizC->quiz()->create(['pass_threshold' => 0.5, 'hearts_enabled' => true]);
        foreach (range(1, 3) as $qPos) {
            $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => fake()->sentence(6).'?', 'points' => 2, 'position' => $qPos]);
            $question->options()->create(['label' => fake()->word(), 'is_correct' => true, 'position' => 1]);
            $question->options()->create(['label' => fake()->word(), 'is_correct' => false, 'position' => 2]);
            $question->options()->create(['label' => fake()->word(), 'is_correct' => false, 'position' => 3]);
        }

        // speaking step deferred to v2 (learner recording + review).

        return $lesson;
    }

    /** @param Collection<int, Course> $courses */
    private function families(Collection $courses): void
    {
        $wallets = app(WalletService::class);
        $badges = Badge::all();

        for ($f = 1; $f <= self::FAMILIES; $f++) {
            DB::transaction(function () use ($f, $courses, $wallets, $badges) {
                $parent = $this->user("parent{$f}@dev.mahadum360", 'parent', fake()->firstName(), fake()->lastName());
                $family = Family::create(['owner_user_id' => $parent->id, 'name' => fake()->lastName()."'s Family", 'child_limit' => 6]);
                FamilyMember::create(['family_id' => $family->id, 'user_id' => $parent->id, 'relationship' => 'parent', 'is_account_owner' => true]);

                $wallet = $wallets->walletFor($family);
                $wallets->creditCurrency($wallet, fake()->numberBetween(50, 500) * 100);
                $wallets->credit($wallet, fake()->numberBetween(100, 2000), 'seed');

                foreach (range(1, fake()->numberBetween(1, 3)) as $fund) {
                    WalletFundingTransaction::create([
                        'wallet_id' => $wallet->id,
                        'gateway' => fake()->randomElement(['paystack', 'flutterwave']),
                        'amount_minor' => fake()->numberBetween(50, 500) * 100,
                        'currency' => $wallet->currency,
                        'status' => fake()->randomElement(['success', 'success', 'pending']),
                        'gateway_ref' => (string) Str::uuid(),
                    ]);
                }

                $childCount = fake()->numberBetween(1, 3);
                for ($k = 0; $k < $childCount; $k++) {
                    $learner = LearnerProfile::create([
                        'family_id' => $family->id,
                        'display_name' => fake()->firstName(),
                        'date_of_birth' => now()->subYears(fake()->numberBetween(6, 14))->toDateString(),
                        'target_language_id' => Language::inRandomOrder()->value('id'),
                        'current_level' => fake()->numberBetween(1, 3),
                    ]);
                    FamilyMember::create(['family_id' => $family->id, 'learner_profile_id' => $learner->id, 'relationship' => 'child', 'is_account_owner' => false]);

                    $this->learnerActivity($learner, $courses->random(), $badges);
                }

                $this->familyChores($family);
            });
        }
    }

    /** @param Collection<int, Badge> $badges */
    private function learnerActivity(LearnerProfile $learner, Course $course, Collection $badges): void
    {
        $enrollment = Enrollment::create(['learner_profile_id' => $learner->id, 'course_id' => $course->id, 'status' => 'active', 'started_at' => now()]);
        $nodes = app(PathBuilder::class)->build($enrollment);

        // Complete a random prefix of the path.
        $completeUpTo = fake()->numberBetween(0, $nodes->count());
        foreach ($nodes->values() as $i => $node) {
            if ($i < $completeUpTo) {
                $node->update(['state' => 'completed']);
                $lesson = Lesson::with('components')->find($node->lesson_id);
                $progress = LessonProgress::create([
                    'learner_profile_id' => $learner->id, 'lesson_id' => $lesson->id,
                    'status' => 'completed', 'score' => fake()->randomFloat(2, 0.5, 1.0),
                    'components_completed' => $lesson->components->count(),
                    'started_at' => now()->subDays($i + 1), 'completed_at' => now()->subDays($i),
                ]);
                foreach ($lesson->components as $comp) {
                    ComponentProgress::create(['lesson_progress_id' => $progress->id, 'lesson_component_id' => $comp->id, 'status' => 'complete', 'score' => 1.0, 'attempts' => 1]);
                }
                XpLedger::create(['learner_profile_id' => $learner->id, 'amount' => (int) $lesson->components->sum('xp_value'), 'source' => 'lesson', 'reference_type' => Lesson::class, 'reference_id' => $lesson->id]);
            } elseif ($i === $completeUpTo) {
                $node->update(['state' => 'active']);
            }
        }

        Streak::create(['learner_profile_id' => $learner->id, 'current_count' => fake()->numberBetween(0, 30), 'longest_count' => fake()->numberBetween(30, 90), 'last_active_date' => now()->toDateString(), 'state' => 'active']);
        Heart::create(['learner_profile_id' => $learner->id, 'current' => fake()->numberBetween(0, 5)]);

        foreach ($badges->random(min(3, $badges->count())) as $badge) {
            LearnerBadge::firstOrCreate(['learner_profile_id' => $learner->id, 'badge_id' => $badge->id], ['earned_at' => now()->subDays(fake()->numberBetween(1, 60))]);
        }
    }

    private function familyChores(Family $family): void
    {
        $owner = $family->owner_user_id;
        $child = LearnerProfile::where('family_id', $family->id)->value('id');
        foreach (range(1, fake()->numberBetween(2, 5)) as $c) {
            $family->chores()->create([
                'created_by_user_id' => $owner,
                'assignee_learner_profile_id' => $child,
                'title' => fake()->randomElement(['Tidy your room', 'Read for 20 min', 'Help with dishes', 'Practice Yoruba', 'Water the plants']),
                'coin_reward' => fake()->numberBetween(10, 100),
                'status' => fake()->randomElement(['active', 'pending_review', 'approved', 'rejected']),
                'due_at' => now()->addDays(fake()->numberBetween(1, 14)),
            ]);
        }
    }

    private function leaderboard(): void
    {
        $league = League::create(['name' => 'Sapphire League', 'tier' => 3, 'week_start' => now()->startOfWeek()->toDateString()]);
        $learners = LearnerProfile::inRandomOrder()->limit(30)->get();
        $rank = 1;
        foreach ($learners->sortByDesc(fn () => fake()->numberBetween(0, 1)) as $learner) {
            LeagueMembership::create(['league_id' => $league->id, 'learner_profile_id' => $learner->id, 'weekly_xp' => fake()->numberBetween(0, 600), 'rank' => $rank++]);
        }
    }

    private function schools(): void
    {
        $plan = Plan::where('interval', 'term')->first() ?? Plan::first();

        for ($s = 1; $s <= self::SCHOOLS; $s++) {
            DB::transaction(function () use ($s, $plan) {
                $org = Organization::create([
                    'name' => fake()->company().' Academy',
                    'type' => 'school',
                    'slug' => Str::slug("dev-school-{$s}-".fake()->unique()->word()),
                    'status' => $s <= 4 ? 'active' : 'pending',
                    'contact_email' => "office{$s}@dev-school.test",
                ]);

                $admin = User::where('email', "admin{$s}@dev.mahadum360")->first();
                if ($admin) {
                    $org->members()->attach($admin->id, ['role' => 'school_admin', 'status' => 'active']);
                }

                $teachers = User::where('email', 'like', 'teacher%@dev.mahadum360')->inRandomOrder()->limit(3)->get();
                foreach ($teachers as $t) {
                    $org->members()->syncWithoutDetaching([$t->id => ['role' => 'teacher', 'status' => 'active']]);
                }

                foreach (range(1, fake()->numberBetween(2, 4)) as $c) {
                    $class = SchoolClass::create([
                        'organization_id' => $org->id,
                        'name' => 'Class '.fake()->randomElement(['JSS1', 'JSS2', 'Primary 4', 'Primary 5', 'SS1']).$c,
                        'level' => fake()->randomElement(['A1', 'A2', 'B1']),
                        'teacher_user_id' => $teachers->first()?->id,
                    ]);

                    foreach (range(1, fake()->numberBetween(10, 18)) as $st) {
                        $learner = LearnerProfile::create([
                            'organization_id' => $org->id,
                            'display_name' => fake()->name(),
                            'target_language_id' => Language::inRandomOrder()->value('id'),
                            'current_level' => fake()->numberBetween(1, 3),
                        ]);
                        ClassEnrollment::create(['school_class_id' => $class->id, 'learner_profile_id' => $learner->id]);
                    }
                }

                $purchased = fake()->numberBetween(50, 300);
                SeatAllocation::create(['organization_id' => $org->id, 'total_purchased' => $purchased, 'active_filled' => fake()->numberBetween(20, $purchased), 'term_label' => 'Term 1', 'expires_at' => now()->addMonths(4), 'auto_renew' => fake()->boolean()]);

                foreach (range(1, fake()->numberBetween(1, 3)) as $inv) {
                    $org->invoices()->create([
                        'type' => fake()->randomElement(['proforma', 'final']),
                        'amount_minor' => fake()->numberBetween(50, 500) * 1000,
                        'status' => fake()->randomElement(['unpaid', 'paid']),
                        'issued_at' => now()->subDays(fake()->numberBetween(1, 90)),
                        'paid_at' => fake()->boolean() ? now()->subDays(fake()->numberBetween(1, 30)) : null,
                    ]);
                }

                if ($admin && $plan) {
                    $sub = new Subscription(['plan_id' => $plan->id, 'method' => 'invoice', 'status' => 'active', 'started_at' => now(), 'renews_at' => now()->addMonths(4)]);
                    $sub->subscriber()->associate($org);
                    $sub->save();
                }
            });
        }
    }

    private function billing(): void
    {
        $plan = Plan::where('code', 'premium_individual')->first() ?? Plan::first();
        if (! $plan) {
            return;
        }
        $parents = User::where('email', 'like', 'parent%@dev.mahadum360')->take(12)->get();

        foreach ($parents as $i => $parent) {
            $status = fake()->randomElement(['active', 'active', 'pending', 'cancelled']);
            $sub = new Subscription([
                'plan_id' => $plan->id, 'method' => 'card', 'status' => $status,
                'started_at' => $status === 'pending' ? null : now()->subDays(fake()->numberBetween(1, 90)),
                'renews_at' => $status === 'active' ? now()->addMonth() : null,
                'cancelled_at' => $status === 'cancelled' ? now()->subDays(5) : null,
            ]);
            $sub->subscriber()->associate($parent);
            $sub->save();

            if ($i < 5) {
                $telcoSub = new Subscription(['plan_id' => $plan->id, 'method' => 'airtime', 'status' => 'active', 'started_at' => now(), 'renews_at' => now()->addMonth()]);
                $telcoSub->subscriber()->associate($parent);
                $telcoSub->save();
                $telco = $telcoSub->telco()->create([
                    'msisdn' => '080'.fake()->numerify('########'),
                    'operator' => fake()->randomElement(['mtn', 'airtel', 'glo', 't2']),
                    'daily_amount_minor' => fake()->numberBetween(50, 200),
                    'state' => fake()->randomElement(['active', 'grace']),
                    'next_attempt_at' => now()->addDay(),
                ]);
                foreach (range(1, fake()->numberBetween(3, 10)) as $a) {
                    TelcoBillingAttempt::create([
                        'telco_subscription_id' => $telco->id, 'attempted_at' => now()->subDays($a),
                        'amount_minor' => $telco->daily_amount_minor,
                        'result' => fake()->randomElement(['success', 'success', 'insufficient']),
                        'operator_ref' => fake()->uuid(),
                    ]);
                }
            }

            if (fake()->boolean(50)) {
                DataBundlePurchase::create([
                    'user_id' => $parent->id,
                    'operator' => fake()->randomElement(['mtn', 'airtel', 'glo', 't2']),
                    'bundle_mb' => fake()->randomElement([100, 500, 1024]),
                    'amount_minor' => fake()->numberBetween(100, 2000) * 100,
                    'status' => fake()->randomElement(['pending', 'success']),
                    'consent_at' => now(),
                ]);
            }
        }
    }

    private function engagement(): void
    {
        $xapi = app(XapiRecorder::class);

        foreach (Payout::where('beneficiary_type', User::class)->with('beneficiary')->take(10)->get() as $payout) {
            $payout->beneficiary?->notifyNow(new PayoutApproved($payout));
        }

        foreach (Subscription::where('subscriber_type', User::class)->where('status', 'active')->take(10)->get() as $sub) {
            $sub->subscriber?->notifyNow(new SubscriptionActivated($sub));
        }

        foreach (LearnerProfile::whereNotNull('family_id')->take(15)->get() as $learner) {
            $xapi->record($learner->id, XapiRecorder::VERB_REGISTERED, 'courses', (int) (Course::inRandomOrder()->value('id') ?? 1), 'Course', XapiRecorder::ACTIVITY_COURSE);
        }
    }

    private function referralsAndPayouts(): void
    {
        $referrals = app(ReferralService::class);
        $parents = User::where('email', 'like', 'parent%@dev.mahadum360')->get();
        $plan = Plan::where('code', 'premium_individual')->first() ?? Plan::first();

        foreach ($parents->take(15) as $referrer) {
            $code = $referrals->codeFor($referrer);

            foreach (range(1, fake()->numberBetween(0, 4)) as $r) {
                $referred = $parents->random();
                if ($referred->id === $referrer->id) {
                    continue;
                }
                $status = fake()->randomElement(['pending', 'qualified', 'qualified', 'rejected']);
                $referral = Referral::create([
                    'referral_code_id' => $code->id,
                    'referred_user_id' => $referred->id,
                    'status' => $status,
                    'device_fingerprint' => fake()->uuid(),
                    'signed_up_at' => now()->subDays(fake()->numberBetween(1, 60)),
                ]);

                if ($status === 'qualified' && $plan) {
                    $amount = (int) round($plan->price_minor * 0.20);
                    $commission = new Commission([
                        'amount_minor' => $amount,
                        'status' => fake()->randomElement(['pending_escrow', 'cleared', 'clawback_pending']),
                        'escrow_until' => now()->addDays(fake()->numberBetween(-10, 14)),
                        'cleared_at' => fake()->boolean() ? now()->subDays(2) : null,
                    ]);
                    $commission->referral()->associate($referral);
                    $commission->beneficiary()->associate($referrer);
                    $commission->save();
                }
            }

            // A payout request for some referrers.
            if (fake()->boolean(60)) {
                $payout = new Payout([
                    'amount_minor' => fake()->numberBetween(50, 100) * 1000,
                    'method' => fake()->randomElement(['bank', 'coins']),
                    'status' => fake()->randomElement(['requested', 'approved', 'paid']),
                    'requested_at' => now()->subDays(fake()->numberBetween(1, 20)),
                ]);
                $payout->beneficiary()->associate($referrer);
                $payout->save();
            }
        }
    }

    private function promoCodes(): void
    {
        foreach (['WELCOME10', 'SCHOOL25', 'DIASPORA15', 'TERM2024', 'FAMILY5'] as $i => $code) {
            PromoCode::firstOrCreate(['code' => $code], [
                'discount_type' => $i % 2 === 0 ? 'percent' : 'fixed',
                'value' => fake()->numberBetween(5, 30),
                'applicable_tier' => fake()->randomElement([null, 'premium_individual', 'school_term']),
                'valid_from' => now()->subMonth(),
                'valid_to' => now()->addMonths(3),
                'max_redemptions' => fake()->numberBetween(50, 1000),
                'status' => 'active',
            ]);
        }
    }
}
