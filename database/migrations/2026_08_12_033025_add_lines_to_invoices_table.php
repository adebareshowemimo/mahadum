<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Itemized breakdown (e.g. student school fees / registration / VAT), each
            // {description, amount_minor}. Nullable so pre-existing invoices fall back
            // to the legacy single "{type} — Mahadum.360 school licence" line on the PDF.
            $table->json('lines')->nullable()->after('amount_minor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('lines');
        });
    }
};
