<?php

use Database\Seeders\GuideLibrarySeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('production')) {
            (new GuideLibrarySeeder)->seedInitialContent();
        }
    }

    public function down(): void
    {
        // Guide entries may have been edited after publication. Never remove them on rollback.
    }
};
