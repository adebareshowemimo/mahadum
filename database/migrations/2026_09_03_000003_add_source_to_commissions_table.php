<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            // The purchase that generated this commission (Subscription /
            // WalletFundingTransaction) — used to unwind on refund.
            $table->nullableMorphs('source');
            // "<gateway>:<eventKey>" — one commission per settled webhook, so a
            // replayed webhook or a renewal charge is never double-counted.
            $table->string('source_event')->nullable()->unique()->after('source_id');
            // 'qualifying' = legacy one-off on the first subscription;
            // 'purchase'   = the per-purchase 5% within the earning window.
            $table->string('kind')->default('purchase')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropMorphs('source');
            $table->dropColumn(['source_event', 'kind']);
        });
    }
};
