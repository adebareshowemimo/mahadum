<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\Commission;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Referral\ReferralService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

        if ($type = $request->query('type')) {
            match ($type) {
                'school' => $query->whereHas('organizations'),
                'family' => $query->whereDoesntHave('organizations')->whereHas('ownedFamilies'),
                'single' => $query->whereDoesntHave('organizations')->whereDoesntHave('ownedFamilies'),
                default => null,
            };
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

    /**
     * Return one user with the same complete shape used by the directory.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json(['data' => $this->row($user->load('roles'))]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $organizationId = isset($data['organization_id']) ? (int) $data['organization_id'] : null;

        $user = DB::transaction(function () use ($data, $organizationId): User {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'username' => $data['username'] ?? null,
                'phone' => $data['phone'] ?? null,
                'locale' => $data['locale'] ?? 'en',
                'status' => $data['status'] ?? 'active',
                'organization_id' => $organizationId,
                'password' => Str::password(32),
            ]);

            $user->assignRole($data['role']);

            if ($organizationId !== null) {
                OrganizationUser::create([
                    'organization_id' => $organizationId,
                    'user_id' => $user->id,
                    'role' => $data['role'],
                    'status' => 'active',
                ]);
            }

            return $user;
        });

        $resetStatus = Password::broker()->sendResetLink(['email' => $user->email]);

        $this->audit->record('user.created', $user, [], [
            'email' => $user->email,
            'role' => $data['role'],
            'status' => $user->status,
            'organization_id' => $organizationId,
            'invitation_sent' => $resetStatus === Password::RESET_LINK_SENT,
        ], $organizationId);

        return response()->json([
            'data' => $this->row($user->load('roles')),
            'meta' => ['invitation_sent' => $resetStatus === Password::RESET_LINK_SENT],
        ], 201);
    }

    /**
     * Feedback (Sept 4, §2): referral activity for one user — both the codes they
     * own (who activated through them, activation date/status, channel, contact,
     * commission earned) and the referral they themselves came from.
     */
    public function referrals(User $user, ReferralService $referrals): JsonResponse
    {
        $codes = ReferralCode::where('owner_type', $user->getMorphClass())
            ->where('owner_id', $user->id)
            ->orderBy('id')->get();
        $code = $codes->first();

        $outbound = Referral::whereIn('referral_code_id', $codes->pluck('id'))
            ->with('referredUser:id,first_name,last_name,email,phone,last_login_at')
            ->orderByDesc('signed_up_at')->orderByDesc('id')->get();

        $commissionByReferral = $code
            ? Commission::whereIn('referral_id', $outbound->pluck('id'))
                ->selectRaw('referral_id, COALESCE(SUM(amount_minor),0) total')
                ->groupBy('referral_id')->pluck('total', 'referral_id')
            : collect();

        $inbound = Referral::where('referred_user_id', $user->id)
            ->with(['referralCode.owner'])
            ->orderByDesc('signed_up_at')
            ->first();

        return response()->json(['data' => [
            'as_referrer' => [
                'code' => $code?->code,
                'total_referred' => $outbound->count(),
                'total_qualified' => $outbound->where('status', 'qualified')->count(),
                'commission_cleared_minor' => $code
                    ? (int) Commission::where('beneficiary_type', $user->getMorphClass())
                        ->where('beneficiary_id', $user->id)->where('status', 'cleared')->sum('amount_minor')
                    : 0,
                'activations' => $outbound->map(fn (Referral $r) => [
                    'referred_name' => $r->referredUser?->name,
                    'email' => ($r->contact_channel === 'email' ? $r->contact_value : null) ?: $r->referredUser?->email,
                    'phone' => ($r->contact_channel === 'phone' ? $r->contact_value : null) ?: $r->referredUser?->phone,
                    'channel' => $r->contact_channel,
                    'signed_up_at' => $r->signed_up_at?->toDateString(),
                    'activated_at' => $r->activated_at?->toDateString(),
                    'status' => $r->activated_at === null
                        ? 'pending'
                        : ($referrals->isReferredUserActive($r) ? 'active' : 'inactive'),
                    'commission_minor' => (int) ($commissionByReferral[$r->id] ?? 0),
                ])->values(),
            ],
            'as_referred' => $inbound === null ? null : [
                'code' => $inbound->referralCode->code,
                'referrer_name' => $this->ownerName($inbound->referralCode->owner),
                'channel' => $inbound->contact_channel,
                'signed_up_at' => $inbound->signed_up_at?->toDateString(),
                'activated_at' => $inbound->activated_at?->toDateString(),
                'status' => $inbound->status,
            ],
        ]]);
    }

    private function ownerName(?Model $owner): ?string
    {
        if ($owner instanceof User) {
            return $owner->name;
        }

        if ($owner instanceof Organization) {
            return $owner->name;
        }

        return null;
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
