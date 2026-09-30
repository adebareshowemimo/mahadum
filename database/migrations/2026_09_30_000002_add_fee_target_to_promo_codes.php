<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing codes retain their original eligibility and whole-total discount.
        Schema::table('promo_codes', fn (Blueprint $table) => $table->string('target')->default('all'));
    }

    public function down(): void
    {
        Schema::table('promo_codes', fn (Blueprint $table) => $table->dropColumn('target'));
    }
};
