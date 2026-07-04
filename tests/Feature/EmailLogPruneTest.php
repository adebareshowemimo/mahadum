<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailLogPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_prunes_rows_past_the_retention_window(): void
    {
        Setting::updateOrCreate(['key' => 'email.log_retention_days'], ['value' => '30']);

        $old = EmailLog::create(['to_email' => 'old@t.test', 'status' => 'sent']);
        $old->forceFill(['created_at' => now()->subDays(60)])->save();
        EmailLog::create(['to_email' => 'recent@t.test', 'status' => 'sent']); // now

        $this->artisan('emails:prune-log')->assertSuccessful();

        $this->assertDatabaseMissing('email_logs', ['to_email' => 'old@t.test']);
        $this->assertDatabaseHas('email_logs', ['to_email' => 'recent@t.test']);
    }

    public function test_retention_of_zero_disables_pruning(): void
    {
        Setting::updateOrCreate(['key' => 'email.log_retention_days'], ['value' => '0']);
        $old = EmailLog::create(['to_email' => 'old@t.test', 'status' => 'sent']);
        $old->forceFill(['created_at' => now()->subYears(5)])->save();

        $this->artisan('emails:prune-log')->assertSuccessful();

        $this->assertDatabaseHas('email_logs', ['to_email' => 'old@t.test']);
    }
}
