<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\Language;
use App\Models\LearnerProfile;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo fixtures for local/QA: one parent-led family with two children, one
 * active school org with an admin, and one published sample course. Idempotent
 * — safe to re-run. NOT wired into production seeding (DatabaseSeeder gates it
 * behind the local environment).
 *
 * Demo credentials (password "Password123!"):
 *   parent@demo.mahadum360   ·   admin@demo.mahadum360
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $this->demoFamily();
        $this->demoSchool();
        $this->sampleCourse();
    }

    private function demoFamily(): void
    {
        $parent = User::firstOrCreate(
            ['email' => 'parent@demo.mahadum360'],
            ['first_name' => 'Ada', 'last_name' => 'Okeke', 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now()],
        );
        $parent->assignRole('parent');

        $family = Family::firstOrCreate(
            ['owner_user_id' => $parent->id],
            ['name' => "Okeke's Family", 'child_limit' => 6],
        );

        FamilyMember::firstOrCreate(
            ['family_id' => $family->id, 'user_id' => $parent->id],
            ['relationship' => 'parent', 'is_account_owner' => true],
        );

        $yoruba = Language::where('code', 'yo')->first();

        foreach (['Chidi', 'Ngozi'] as $i => $name) {
            $learner = LearnerProfile::firstOrCreate(
                ['family_id' => $family->id, 'display_name' => $name],
                ['date_of_birth' => now()->subYears(9 + $i)->toDateString(), 'target_language_id' => $yoruba?->id, 'current_level' => 1],
            );

            FamilyMember::firstOrCreate(
                ['family_id' => $family->id, 'learner_profile_id' => $learner->id],
                ['relationship' => 'child', 'is_account_owner' => false],
            );
        }
    }

    private function demoSchool(): void
    {
        $org = Organization::firstOrCreate(
            ['slug' => 'demo-academy'],
            ['name' => 'Demo Academy', 'type' => 'school', 'status' => 'active', 'contact_email' => 'office@demo-academy.test'],
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@demo.mahadum360'],
            ['first_name' => 'Bola', 'last_name' => 'Adeyemi', 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now()],
        );
        $admin->assignRole('school_admin');

        OrganizationUser::firstOrCreate(
            ['organization_id' => $org->id, 'user_id' => $admin->id],
            ['role' => 'school_admin', 'status' => 'active'],
        );
    }

    private function sampleCourse(): void
    {
        $language = Language::where('code', 'yo')->first()
            ?? Language::create(['code' => 'yo', 'name' => 'Yoruba', 'script' => 'latin', 'is_active' => true]);

        if (Course::where('title', 'Yoruba for Beginners')->exists()) {
            return; // already seeded
        }

        $owner = User::firstOrCreate(
            ['email' => 'content@demo.mahadum360'],
            ['first_name' => 'Content', 'last_name' => 'Owner', 'password' => self::PASSWORD, 'status' => 'active', 'email_verified_at' => now()],
        );
        $owner->assignRole('content_owner');

        $course = Course::create([
            'owner_user_id' => $owner->id,
            'language_id' => $language->id,
            'title' => 'Yoruba for Beginners',
            'description' => 'Greetings, numbers and everyday phrases.',
            'level_band' => 'A1',
            'status' => 'published',
            'is_published' => true,
        ]);

        $level = $course->levels()->create(['title' => 'Unit 1 — Greetings', 'position' => 1]);
        $lesson = $level->lessons()->create(['title' => 'Saying hello', 'position' => 1, 'est_minutes' => 5]);

        $video = $lesson->components()->create(['type' => 'video', 'position' => 1, 'xp_value' => 5]);
        $video->video()->create(['title' => 'Ẹ n lẹ — Hello', 'duration_seconds' => 60, 'status' => 'ready', 'kind' => 'lesson']);

        $quizComponent = $lesson->components()->create(['type' => 'quiz', 'position' => 2, 'xp_value' => 10]);
        $quiz = $quizComponent->quiz()->create(['pass_threshold' => 0.5]);
        $question = $quiz->questions()->create(['type' => 'mcq_single', 'prompt' => 'How do you say "hello"?', 'points' => 2, 'position' => 1]);
        $question->options()->create(['label' => 'Ẹ n lẹ', 'is_correct' => true, 'position' => 1]);
        $question->options()->create(['label' => 'O dabọ', 'is_correct' => false, 'position' => 2]);

        // speaking step deferred to v2 (learner recording + review).

        $lesson->update(['published_at' => now(), 'is_locked_by_default' => false]);
    }
}
