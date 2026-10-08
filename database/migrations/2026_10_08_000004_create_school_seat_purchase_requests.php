<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_seat_purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('request_key', 64);
            $table->string('fingerprint', 64);
            $table->json('response');
            $table->timestamps();
            $table->unique(['organization_id', 'user_id', 'request_key'], 'school_seat_purchase_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_seat_purchase_requests');
    }
};
