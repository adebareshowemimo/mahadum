<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_bundle_purchases', function (Blueprint $table) {
            $table->string('payment_provider')->default('monnify');
            $table->string('fulfillment_provider')->default('monnify');
            $table->string('bill_tx_ref')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('data_bundle_purchases', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'fulfillment_provider', 'bill_tx_ref']);
        });
    }
};
