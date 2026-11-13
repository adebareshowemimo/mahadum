<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    public function create(User $user): bool
    {
        return $user->can('content.courses.create');
    }

    public function view(User $user, Story $story): bool
    {
        return $user->can('content.courses.view');
    }

    public function update(User $user, Story $story): bool
    {
        return $user->can('content.courses.update')
            && ($story->owner_user_id === $user->id || $user->can('content.courses.publish'));
    }
}
