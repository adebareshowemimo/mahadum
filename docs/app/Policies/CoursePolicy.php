<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * Courses are CENTRAL (shared) content — no tenant scope. Authoring is gated by
 * the content.* permissions; super_admin is allowed via the Gate::before bypass.
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.courses.view');
    }

    public function view(User $user, Course $course): bool
    {
        // Published content is readable by learners; drafts need the CMS permission.
        return $course->is_published || $user->can('content.courses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('content.courses.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->can('content.courses.update')
            && ($course->owner_user_id === $user->id || $user->can('content.courses.publish'));
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can('content.courses.publish');
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->can('content.courses.delete');
    }
}
