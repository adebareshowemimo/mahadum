<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('learner_profile_id')->nullable()->constrained('learner_profiles')->nullOnDelete();
            $table->string('type');
            $table->string('source');
            $table->integer('amount');
            $table->integer('balance_after')->nullable();
            $table->string('reference_type')->nullable();
            $table->bigInteger('reference_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_transactions');
    }
};
