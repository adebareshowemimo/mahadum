<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plans')
            ->whereIn('audience', ['individual', 'family'])
            ->orderBy('id')
            ->each(function (object $plan): void {
                $features = json_decode((string) $plan->features, true) ?: [];
                $features['offline_download'] = false;

                DB::table('plans')->where('id', $plan->id)->update([
                    'features' => json_encode($features, JSON_THROW_ON_ERROR),
                ]);
            });
    }

    public function down(): void
    {
        // Offline downloads were never live; rollback must not advertise them.
    }
};
