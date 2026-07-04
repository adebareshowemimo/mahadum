<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteOrgAdminRequest;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationRequest;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\ReferralCode;
use App\Models\User;
use App\Notifications\OrganizationSeatAssigned;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = Organization::withCount(['members', 'schoolClasses'])->latest();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('name', 'like', "%{$q}%");
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $page = $query->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Organization $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'type' => $o->type,
                'status' => $o->status,
                'members' => $o->members_count,
                'classes' => $o->school_classes_count,
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            // Distinct types for the filter dropdown (cheap; small cardinality).
            'types' => Organization::query()->distinct()->orderBy('type')->pluck('type'),
        ]);
    }

    /**
     * Full drill-down for a single organization: profile, membership, and the
     * counts that make up its footprint (classes, seats, invoices).
     */
    public function show(Organization $organization): JsonResponse
    {
        $organization->loadCount(['members', 'schoolClasses', 'families', 'learnerProfiles']);

        $members = OrganizationUser::with('user')
            ->where('organization_id', $organization->id)
            ->get()
            ->map(fn (OrganizationUser $m) => [
                'id' => $m->user?->id,
                'name' => $m->user?->name,
                'email' => $m->user?->email,
                'role' => $m->role,
                'status' => $m->status,
            ])
            ->filter(fn ($row) => $row['id'] !== null)
            ->values();

        $seats = [
            'purchased' => (int) $organization->seatAllocations()->sum('total_purchased'),
            'filled' => (int) $organization->seatAllocations()->sum('active_filled'),
        ];

        $invoices = $organization->invoices()->latest()->limit(10)->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'type' => $i->type,
                'amount_minor' => (int) $i->amount_minor,
                'status' => $i->status,
                'issued_at' => ($i->issued_at ?? $i->created_at)?->toIso8601String(),
            ]);

        $classes = $organization->schoolClasses()
            ->withCount('enrollments')
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'level' => $c->level,
                'students' => $c->enrollments_count,
            ]);

        // Referral codes the organization itself owns (polymorphic owner morph).
        $referrals = ReferralCode::where('owner_type', Organization::class)
            ->where('owner_id', $organization->id)
            ->latest()
            ->get()
            ->map(fn (ReferralCode $r) => [
                'id' => $r->id,
                'code' => $r->code,
                'kind' => $r->kind,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $audit = AuditLog::with('actorUser')
            ->where('organization_id', $organization->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AuditLog $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actor' => $a->actorUser?->name,
                'ip' => $a->ip,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'id' => $organization->id,
            'name' => $organization->name,
            'type' => $organization->type,
            'slug' => $organization->slug,
            'status' => $organization->status,
            'contact_email' => $organization->contact_email,
            'domain' => $organization->domain,
            'domain_verified_at' => $organization->domain_verified_at?->toIso8601String(),
            'cac_number' => $organization->cac_number,
            'address' => $organization->address,
            'created_at' => $organization->created_at?->toIso8601String(),
            'counts' => [
                'members' => $organization->members_count,
                'classes' => $organization->school_classes_count,
                'families' => $organization->families_count,
                'learners' => $organization->learner_profiles_count,
            ],
            'seats' => $seats,
            'members' => $members,
            'invoices' => $invoices,
            'classes' => $classes,
            'referrals' => $referrals,
            'audit' => $audit,
        ]]);
    }

    /**
     * Admin-create an organization (bypasses the school self-registration flow).
     * Defaults to "active" since a super-admin is vouching for it directly.
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['type'] ??= 'school';
        $data['status'] ??= 'active';

        $organization = Organization::create($data);

        $this->audit->record(
            'organization.created',
            $organization,
            [],
            ['name' => $organization->name, 'status' => $organization->status],
            $organization->id,
        );

        return response()->json(['data' => [
            'id' => $organization->id,
            'name' => $organization->name,
            'status' => $organization->status,
        ]], 201);
    }

    /**
     * Invite a school admin to an organization. Creates the account (unusable
     * password until they set their own), grants `school_admin`, binds the
     * membership, and emails a set-password link that doubles as the invite.
     * Rejects an email that already belongs to a user — assign the role via the
     * Users directory instead, so we never silently mutate an existing account.
     */
    public function inviteAdmin(InviteOrgAdminRequest $request, Organization $organization): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data, $organization) {
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Str::password(24), // hashed by cast; unusable until reset
                'organization_id' => $organization->id,
            ]);

            $user->assignRole('school_admin');

            OrganizationUser::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role' => 'school_admin',
                'status' => 'active',
            ]);

            return $user;
        });

        $user->notify(new OrganizationSeatAssigned($organization, 'school_admin'));

        // The set-password link is the invitation.
        Password::broker()->sendResetLink(['email' => $user->email]);

        $this->audit->record(
            'organization.admin_invited',
            $organization,
            [],
            ['user_id' => $user->id, 'email' => $user->email],
            $organization->id,
        );

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]], 201);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $before = $organization->only(['name', 'type', 'contact_email', 'domain', 'cac_number', 'address']);
        $organization->update($request->validated());

        $this->audit->record(
            'organization.updated',
            $organization,
            $before,
            $organization->only(array_keys($before)),
            $organization->id,
        );

        return response()->json(['data' => [
            'id' => $organization->id,
            'name' => $organization->name,
            'status' => $organization->status,
        ]]);
    }

    /**
     * Activate an organization (after CAC / domain verification). Kept as a
     * dedicated verb for the existing "Activate" affordance + audit trail.
     */
    public function activate(Request $request, Organization $organization): JsonResponse
    {
        return $this->transition($organization, 'active', 'organization.activated');
    }

    /**
     * Reversible lifecycle change (active / suspended / inactive). Preferred over
     * a hard delete — organizations own tenant data (classes, invoices, learners).
     */
    public function setStatus(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive', 'pending'])],
        ]);

        return $this->transition($organization, $validated['status'], 'organization.status_changed');
    }

    private function transition(Organization $organization, string $status, string $action): JsonResponse
    {
        $before = $organization->status;
        $organization->update(['status' => $status]);

        $this->audit->record(
            $action,
            $organization,
            ['status' => $before],
            ['status' => $status],
            $organization->id,
        );

        return response()->json(['data' => ['id' => $organization->id, 'status' => $organization->status]]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'org';
        $slug = $base;
        $n = 1;
        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
