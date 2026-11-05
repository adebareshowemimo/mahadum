<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_coin_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_key', 100);
            $table->unsignedInteger('coins');
            $table->unsignedInteger('price_minor');
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->unique(['family_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_coin_purchases');
    }
};
