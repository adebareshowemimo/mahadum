<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_join_links', function (Blueprint $table) {
            $table->text('token_ciphertext')->change();
        });
    }

    public function down(): void
    {
        // Keeping the wider column avoids truncating encrypted links on rollback.
    }
};
