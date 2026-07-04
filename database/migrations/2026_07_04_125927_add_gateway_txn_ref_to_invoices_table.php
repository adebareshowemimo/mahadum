<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Correlates gateway webhooks that don't echo our `invoice_<id>` reference
            // (e.g. Monnify refunds), same pattern as subscriptions/wallet fundings.
            $table->string('gateway_txn_ref')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('gateway_txn_ref');
        });
    }
};
