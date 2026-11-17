<?php

use App\Models\Family;
use App\Models\Wallet;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Family::withTrashed()->cursor() as $family) {
            Wallet::firstOrCreate(
                ['owner_type' => $family->getMorphClass(), 'owner_id' => $family->id],
                ['coin_balance' => 0, 'currency_balance_minor' => 0, 'currency' => 'NGN'],
            );
        }
    }

    public function down(): void
    {
        // Wallets and their append-only activity must never be removed on rollback.
    }
};
