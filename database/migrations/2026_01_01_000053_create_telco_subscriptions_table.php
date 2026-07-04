<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telco_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('msisdn');
            $table->string('operator');
            $table->bigInteger('daily_amount_minor')->default(0);
            $table->string('state')->default('active');
            $table->dateTime('grace_until')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telco_subscriptions');
    }
};
