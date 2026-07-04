<?php

namespace App\Providers;

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
        \App\Models\Course::class         => \App\Policies\CoursePolicy::class,
        \App\Models\SchoolClass::class    => \App\Policies\SchoolClassPolicy::class,
        \App\Models\Payout::class         => \App\Policies\PayoutPolicy::class,
        \App\Models\LearnerProfile::class => \App\Policies\LearnerProfilePolicy::class,
    ];

    public function boot(): void
    {
        // super_admin holds every permission implicitly — so new permissions
        // added later are covered without re-seeding the role. Returning null
        // (not false) lets normal checks run for everyone else.
        Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
    }
}
