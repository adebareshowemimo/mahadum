<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('language_votes')->where('language', 'Edo')->update(['language' => 'Benin']);
    }

    public function down(): void
    {
        DB::table('language_votes')->where('language', 'Benin')->update(['language' => 'Edo']);
    }
};
