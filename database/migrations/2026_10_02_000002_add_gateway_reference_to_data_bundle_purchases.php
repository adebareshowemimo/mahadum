<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_bundle_purchases', function (Blueprint $table) {
            $table->string('gateway_txn_ref')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('data_bundle_purchases', function (Blueprint $table) {
            $table->dropColumn('gateway_txn_ref');
        });
    }
};
