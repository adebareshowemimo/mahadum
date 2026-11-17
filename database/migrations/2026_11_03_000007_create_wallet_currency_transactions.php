<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_currency_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('source');
            $table->unsignedBigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->uuid('transfer_ref')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_currency_transactions');
    }
};
