<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dateTime('entries_open_at')->nullable();
            $table->dateTime('judging_starts_at')->nullable();
            $table->dateTime('judging_closes_at')->nullable();
            $table->text('rules')->nullable();
            $table->text('prizes')->nullable();
            $table->text('eligibility')->nullable();
        });

        Schema::table('competition_entries', function (Blueprint $table) {
            $table->foreignId('guardian_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('guardian_approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competition_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('guardian_approved_by');
            $table->dropColumn('guardian_approved_at');
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['entries_open_at', 'judging_starts_at', 'judging_closes_at', 'rules', 'prizes', 'eligibility']);
        });
    }
};
