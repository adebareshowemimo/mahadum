<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_template_overrides', function (Blueprint $table) {
            $table->string('content_mode', 20)->default('structured')->after('subject');
            $table->longText('html_body')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('email_template_overrides', function (Blueprint $table) {
            $table->dropColumn(['content_mode', 'html_body']);
        });
    }
};
