<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\WelcomeEmail;
use Illuminate\Auth\Events\Verified;

/**
 * Send the welcome email the moment a user verifies their address.
 * Auto-discovered from app/Listeners.
 */
class SendWelcomeEmail
{
    public function handle(Verified $event): void
    {
        if ($event->user instanceof User) {
            $event->user->notify(new WelcomeEmail);
        }
    }
}
