<?php

namespace App\Notifications;

use App\Models\Organization;
use App\Notifications\Concerns\CustomizableMail;
use App\Notifications\Concerns\TagsEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a user is granted a seat/role in a school (org invite-admin flow).
 * The set-password link that actually activates the account is a separate
 * ResetPasswordNotification dispatched right after this one — kept apart so
 * password-reset copy stays generic and reusable.
 */
class OrganizationSeatAssigned extends Notification implements ShouldQueue
{
    use CustomizableMail, Queueable, TagsEmail;

    public function __construct(
        private Organization $organization,
        private string $role,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = str($this->role)->replace('_', ' ')->title();

        $default = (new MailMessage)
            ->subject("You've been added to {$this->organization->name} on ".config('brand.name'))
            ->greeting('Welcome to the team')
            ->line("You've been granted **{$roleLabel}** access to **{$this->organization->name}** on ".config('brand.name').'.')
            ->line('A separate email with a link to set your password is on its way — use it to activate your account.')
            ->action('Visit '.config('brand.name'), config('brand.url').'/login');

        $mail = $this->applyOverride('organization_seat_assigned', [
            '{{brand_name}}' => (string) config('brand.name'),
            '{{brand_url}}' => (string) config('brand.url'),
            '{{organization_name}}' => (string) $this->organization->name,
            '{{role}}' => (string) $roleLabel,
        ], $default);

        return $this->tagEmail($mail, 'organization_seat_assigned', $notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'organization_seat_assigned',
            'organization_id' => $this->organization->id,
            'role' => $this->role,
        ];
    }
}
