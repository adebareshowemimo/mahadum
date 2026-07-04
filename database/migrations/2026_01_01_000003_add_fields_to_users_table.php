<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('username')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('google_id')->nullable();
            $table->string('locale')->default('en');
            $table->string('status')->default('active');
            $table->dateTime('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // drop added columns if rolling back
        });
    }
};
