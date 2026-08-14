<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $teacherId = DB::table('users')->where('email', 'teacher1@dev.mahadum360')->value('id');
        $organizationId = DB::table('organizations')
            ->where('slug', 'like', 'dev-school-1-%')
            ->orderBy('id')
            ->value('id');

        if (! $teacherId || ! $organizationId) {
            return;
        }

        DB::table('organization_user')->updateOrInsert(
            ['organization_id' => $organizationId, 'user_id' => $teacherId],
            ['role' => 'teacher', 'status' => 'active', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        // This repairs application data and is intentionally not reversed.
    }
};
