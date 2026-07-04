<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            // Who released/declined the coins and when — the review audit trail
            // (separation of duties: the approver is a parent, never the learner).
            $table->foreignId('decided_by')->nullable()->after('coins_locked')->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn('decided_at');
        });
    }
};
