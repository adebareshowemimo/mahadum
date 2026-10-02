<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_bundle_purchases', function (Blueprint $table) {
            $table->unsignedInteger('bundle_mb')->nullable()->change();
            $table->string('biller_code')->nullable();
            $table->string('product_code')->nullable();
            $table->string('product_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('payment_reference')->nullable()->unique();
            $table->string('vend_reference')->nullable()->unique();
            $table->string('checkout_url', 2048)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('vend_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('data_bundle_purchases', function (Blueprint $table) {
            $table->dropColumn(['biller_code', 'product_code', 'product_name', 'phone_number', 'payment_reference',
                'vend_reference', 'checkout_url', 'paid_at', 'vend_started_at']);
        });
    }
};
