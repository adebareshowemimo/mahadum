<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telco_billing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telco_subscription_id')->constrained('telco_subscriptions')->cascadeOnDelete();
            $table->dateTime('attempted_at')->nullable();
            $table->bigInteger('amount_minor')->default(0);
            $table->string('result')->nullable();
            $table->string('operator_ref')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telco_billing_attempts');
    }
};
