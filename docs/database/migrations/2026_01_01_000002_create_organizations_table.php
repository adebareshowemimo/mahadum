<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('school');
            $table->string('slug')->unique();
            $table->string('cac_number')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('domain')->nullable();
            $table->dateTime('domain_verified_at')->nullable();
            $table->string('status')->default('pending');
            $table->string('licence_model')->nullable();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
