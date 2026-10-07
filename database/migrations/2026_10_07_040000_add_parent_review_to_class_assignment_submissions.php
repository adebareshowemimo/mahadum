<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_assignment_submissions', function (Blueprint $table) {
            $table->text('text_body')->nullable();
            $table->string('parent_review_status')->nullable();
            $table->unsignedInteger('coins_locked')->default(0);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('class_assignment_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['text_body', 'parent_review_status', 'coins_locked', 'decided_at']);
        });
    }
};
