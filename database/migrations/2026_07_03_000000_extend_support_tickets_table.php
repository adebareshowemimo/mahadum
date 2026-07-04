<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            // Round out the scaffold so a ticket carries the actual request +
            // an admin resolution. Nullable so existing rows migrate cleanly.
            $table->string('email')->nullable()->after('user_id');
            $table->string('category')->nullable()->after('subject');
            $table->text('message')->nullable()->after('category');
            $table->text('response')->nullable()->after('assigned_to');
            $table->dateTime('resolved_at')->nullable()->after('response');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['email', 'category', 'message', 'response', 'resolved_at']);
        });
    }
};
