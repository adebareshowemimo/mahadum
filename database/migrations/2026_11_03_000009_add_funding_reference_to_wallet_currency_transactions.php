<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('wallet_currency_transactions', 'wallet_funding_transaction_id')) {
            Schema::table('wallet_currency_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('wallet_funding_transaction_id')->nullable();
            });
        }
        Schema::table('wallet_currency_transactions', function (Blueprint $table) {
            $table->foreign('wallet_funding_transaction_id', 'wallet_currency_funding_fk')
                ->references('id')->on('wallet_funding_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_currency_transactions', function (Blueprint $table) {
            $table->dropForeign('wallet_currency_funding_fk');
            $table->dropColumn('wallet_funding_transaction_id');
        });
    }
};
