<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OAuth accounts have no password. AuthController@google creates a user with
 * only a google_id, which the original NOT NULL column rejected — every
 * first-time Google sign-up failed. Password-less rows are authenticatable
 * only via their identity provider (AuthController@login refuses a null
 * password outright), and such a user can still set one via password reset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
