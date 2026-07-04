<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\LearnerProfile;
use App\Models\Payout;
use App\Models\SchoolClass;
use App\Policies\CoursePolicy;
use App\Policies\LearnerProfilePolicy;
use App\Policies\PayoutPolicy;
use App\Policies\SchoolClassPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Policy map (model => policy). Add per-resource policies here as they
     * are built (these enforce role + tenant ownership beyond the coarse
     * `can:permission` route guards).
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Course::class => CoursePolicy::class,
        SchoolClass::class => SchoolClassPolicy::class,
        Payout::class => PayoutPolicy::class,
        LearnerProfile::class => LearnerProfilePolicy::class,
    ];

    public function boot(): void
    {
        // super_admin holds every permission implicitly — so new permissions
        // added later are covered without re-seeding the role. Returning null
        // (not false) lets normal checks run for everyone else.
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
    }
}
