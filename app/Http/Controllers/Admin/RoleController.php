<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Permission-matrix data: every role, every permission (grouped by aspect),
     * and which role holds which permission. View-only — the source of truth is
     * RolesAndPermissionsSeeder; editing grants live-is deliberately not exposed.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')->orderBy('id')->get();
        $permissions = Permission::orderBy('name')->pluck('name');

        $matrix = $roles->mapWithKeys(fn (Role $role) => [
            $role->name => $role->permissions->pluck('name')->all(),
        ]);

        // Group permissions by their leading aspect (the segment before the first dot).
        $groups = $permissions
            ->groupBy(fn (string $name) => Str::before($name, '.'))
            ->map(fn ($names, $group) => [
                'group' => $group,
                'permissions' => $names->values()->all(),
            ])
            ->values();

        return response()->json(['data' => [
            'roles' => $roles->pluck('name'),
            'groups' => $groups,
            'matrix' => $matrix,
        ]]);
    }
}
