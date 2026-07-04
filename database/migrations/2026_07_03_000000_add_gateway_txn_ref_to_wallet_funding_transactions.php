<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_funding_transactions', function (Blueprint $table) {
            // The gateway's OWN transaction id (e.g. Monnify's transactionReference).
            // Some gateways' refund webhooks correlate by this rather than by our
            // gateway_ref, and it's also required to initiate a refund via the API.
            $table->string('gateway_txn_ref')->nullable()->after('gateway_ref')->index();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_funding_transactions', function (Blueprint $table) {
            $table->dropColumn('gateway_txn_ref');
        });
    }
};
