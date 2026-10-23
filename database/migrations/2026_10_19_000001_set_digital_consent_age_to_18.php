<?php

use App\Services\Settings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Update existing overrides as well as the new-install default.
        app(Settings::class)->set(['compliance.minor_age' => 18]);
    }

    public function down(): void
    {
        // Rolling back code must not lower the approved consent age.
    }
};
