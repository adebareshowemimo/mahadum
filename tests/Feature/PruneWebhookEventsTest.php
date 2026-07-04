<?php

namespace Tests\Feature;

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PruneWebhookEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_drops_only_old_processed_events(): void
    {
        WebhookEvent::create(['source' => 'paystack', 'event' => 'old', 'status' => 'processed', 'processed_at' => now()->subDays(100)]);
        WebhookEvent::create(['source' => 'paystack', 'event' => 'recent', 'status' => 'processed', 'processed_at' => now()->subDays(10)]);
        WebhookEvent::create(['source' => 'telco', 'event' => 'inflight', 'status' => 'received', 'processed_at' => null]);

        Artisan::call('webhooks:prune');

        $this->assertDatabaseMissing('webhook_events', ['event' => 'old']);
        $this->assertDatabaseHas('webhook_events', ['event' => 'recent']);
        $this->assertDatabaseHas('webhook_events', ['event' => 'inflight']); // unprocessed never pruned
    }

    public function test_retention_window_is_configurable(): void
    {
        WebhookEvent::create(['source' => 'paystack', 'event' => 'd20', 'status' => 'processed', 'processed_at' => now()->subDays(20)]);

        Artisan::call('webhooks:prune', ['--days' => 7]);

        $this->assertDatabaseMissing('webhook_events', ['event' => 'd20']);
    }
}
