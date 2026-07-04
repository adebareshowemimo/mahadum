<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('is_active')->index();
        });

        // Seed a deterministic starting order from the existing alphabetical listing
        // so the admin UI has stable positions to drag against.
        Language::query()->orderBy('name')->get()->each(function (Language $l, int $i) {
            $l->update(['position' => $i]);
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
