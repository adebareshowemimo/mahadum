<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_course_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['school_class_id', 'course_id']);
        });

        // Existing production roles are not re-seeded during deployment.
        // Apply the newly approved teacher capability as part of the schema
        // rollout so the UI and API become usable immediately after migrate.
        $roleId = DB::table('roles')->where('name', 'teacher')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'schools.classes.manage')->value('id');
        if ($roleId && $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('name', 'teacher')->value('id');
        $permissionId = DB::table('permissions')->where('name', 'schools.classes.manage')->value('id');
        if ($roleId && $permissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Schema::dropIfExists('class_course_assignments');
    }
};
