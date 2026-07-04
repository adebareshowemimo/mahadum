<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Models\OrganizationUser;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Platform-wide user directory. Paginated + filterable by search string,
     * role, status, and organization membership.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('roles:id,name');

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->whereHas('roles', fn ($r) => $r->where('name', $role));
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($orgId = $request->query('organization_id')) {
            $query->whereHas('organizations', fn ($o) => $o->where('organizations.id', $orgId));
        }

        $page = $query->latest()->paginate(20);
        $users = collect($page->items());

        // Prefetch org memberships for the whole page in one query (no N+1).
        $membershipsByUser = $this->membershipsFor($users->pluck('id')->all());

        return response()->json([
            'data' => $users->map(fn (User $u) => $this->row($u, $membershipsByUser[$u->id] ?? [])),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function assignRole(AssignRoleRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        $action = $data['action'];

        // Guard against self-lockout: a super_admin can't strip their own super_admin.
        if ($action === 'revoke' && $role === 'super_admin' && $request->user()->is($user)) {
            return response()->json([
                'error' => ['code' => 'self_lockout', 'message' => 'You cannot remove your own super_admin role.'],
            ], 422);
        }

        $before = $user->getRoleNames()->all();
        $action === 'assign' ? $user->assignRole($role) : $user->removeRole($role);

        $this->audit->record(
            'user.role_'.$action,
            $user,
            ['roles' => $before],
            ['roles' => $user->getRoleNames()->all()],
        );

        return response()->json(['data' => $this->row($user->fresh('roles'))]);
    }

    public function setStatus(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
        ]);

        if ($request->user()->is($user)) {
            return response()->json([
                'error' => ['code' => 'self_action', 'message' => 'You cannot change your own status.'],
            ], 422);
        }

        $before = $user->status;
        $user->update(['status' => $validated['status']]);

        $this->audit->record('user.status_changed', $user, ['status' => $before], ['status' => $user->status]);

        return response()->json(['data' => $this->row($user->fresh('roles'))]);
    }

    /**
     * Org memberships grouped by user id (one query for a whole page of users).
     *
     * @param  array<int, int>  $userIds
     * @return array<int, list<OrganizationUser>>
     */
    private function membershipsFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $grouped = [];
        foreach (OrganizationUser::with('organization:id,name')->whereIn('user_id', $userIds)->get() as $m) {
            $grouped[$m->user_id][] = $m;
        }

        return $grouped;
    }

    /**
     * @param  list<OrganizationUser>|null  $memberships
     * @return array<string, mixed>
     */
    private function row(User $u, ?array $memberships = null): array
    {
        $memberships ??= $this->membershipsFor([$u->id])[$u->id] ?? [];

        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'status' => $u->status,
            'roles' => $u->getRoleNames()->all(),
            'email_verified' => $u->email_verified_at !== null,
            'created_at' => $u->created_at?->toIso8601String(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            'organizations' => array_map(fn (OrganizationUser $m) => [
                'id' => $m->organization_id,
                'name' => $m->organization?->name,
                'role' => $m->role,
                'status' => $m->status,
            ], $memberships),
        ];
    }
}
