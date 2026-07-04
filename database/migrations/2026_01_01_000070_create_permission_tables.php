<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission tables — Mahadum.360 (global-roles model).
 *
 * Roles are GLOBAL capabilities ("what can this role do"). We intentionally do
 * NOT use spatie "teams", because teams force every grant to carry a non-null
 * team id (the pivot team column is part of the primary key) — which can't
 * express global roles (super_admin / content_owner) or family roles
 * (parent / student) that aren't org-scoped at all.
 *
 * Tenant isolation is enforced elsewhere and does not need per-grant scoping:
 *   • IdentifyTenant validates org membership (organization_user) per request,
 *   • BelongsToTenant adds the organization_id query scope,
 *   • policies add sameTenant()/ownership checks on the specific record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');       // e.g. billing.invoices.manage
            $table->string('guard_name'); // web
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');       // super_admin, teacher, parent, ...
            $table->string('guard_name'); // web
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')->cascadeOnDelete();

            $table->primary(
                ['permission_id', 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary'
            );
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')->on('roles')->cascadeOnDelete();

            $table->primary(
                ['role_id', 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary'
            );
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')
                ->references('id')->on('roles')->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        if (app()->bound('cache')) {
            $store = config('permission.cache.store', 'default');
            app('cache')->store($store === 'default' ? null : $store)
                ->forget(config('permission.cache.key', 'spatie.permission.cache'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
