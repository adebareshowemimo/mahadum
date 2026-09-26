<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_template_overrides', function (Blueprint $table) {
            $table->boolean('include_branding')->default(true)->after('content_mode');
        });
    }

    public function down(): void
    {
        Schema::table('email_template_overrides', function (Blueprint $table) {
            $table->dropColumn('include_branding');
        });
    }
};
