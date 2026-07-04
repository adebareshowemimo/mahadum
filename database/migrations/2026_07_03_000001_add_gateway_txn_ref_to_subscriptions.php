<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // The gateway's OWN transaction id for the card checkout (e.g. Monnify's
            // transactionReference). Some gateways' refund webhooks correlate by this
            // rather than by our `sub_<id>` reference.
            $table->string('gateway_txn_ref')->nullable()->after('method')->index();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('gateway_txn_ref');
        });
    }
};
