<?php

namespace App\Services\Messaging;

use App\Models\User;
use App\Models\WebPushSubscription;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function configured(): bool
    {
        return (bool) config('services.messaging.live')
            && filled(config('services.messaging.web_push.public_key'))
            && filled(config('services.messaging.web_push.private_key'))
            && filled(config('services.messaging.web_push.subject'));
    }

    public function eligible(User $user): bool
    {
        return $user->status === 'active' && $user->hasVerifiedEmail()
            && ($user->hasAnyRole(['parent', 'teacher', 'school_admin', 'super_admin', 'content_owner', 'supervisor'])
                || ($user->date_of_birth && $user->date_of_birth->age >= (int) config('compliance.minor_age', 13)));
    }

    public function validEndpoint(string $endpoint): bool
    {
        $url = parse_url($endpoint);
        if (! $url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])) {
            return false;
        }
        $host = strtolower($url['host'] ?? '');

        return $host === 'fcm.googleapis.com' || $host === 'web.push.apple.com'
            || $host === 'updates.push.services.mozilla.com' || str_ends_with($host, '.push.services.mozilla.com')
            || str_ends_with($host, '.notify.windows.com');
    }

    public function client(): WebPush
    {
        return new WebPush(['VAPID' => [
            'subject' => config('services.messaging.web_push.subject'),
            'publicKey' => config('services.messaging.web_push.public_key'),
            'privateKey' => config('services.messaging.web_push.private_key'),
        ]], ['TTL' => 3600], new Client(['timeout' => 10, 'allow_redirects' => false]));
    }

    /** @param array{title: string, body: string, data?: array<string, mixed>} $payload */
    public function send(User $user, array $payload): void
    {
        if (! $this->configured() || ! $this->eligible($user)) {
            return;
        }
        $subscriptions = WebPushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }
        $client = $this->client();
        foreach ($subscriptions as $subscription) {
            if (! $this->validEndpoint($subscription->endpoint)) {
                continue;
            }
            // Lock-screen text is deliberately generic; details remain behind login.
            $report = $client->sendOneNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_key],
            ]), json_encode(['title' => 'MAHADUM.360', 'body' => 'You have a new update.', 'data' => ['url' => '/notifications']], JSON_THROW_ON_ERROR));
            if ($report->isSubscriptionExpired()) {
                $subscription->delete();
            } elseif (! $report->isSuccess()) {
                throw new \RuntimeException('Browser notification delivery failed.');
            }
        }
    }
}
