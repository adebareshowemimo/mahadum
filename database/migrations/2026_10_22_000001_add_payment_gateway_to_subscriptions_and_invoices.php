<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_gateway', 30)->nullable()->after('gateway_txn_ref');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_gateway', 30)->nullable()->after('gateway_txn_ref');
            $table->text('checkout_url')->nullable()->after('payment_gateway');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn(['payment_gateway', 'checkout_url']));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('payment_gateway'));
    }
};
