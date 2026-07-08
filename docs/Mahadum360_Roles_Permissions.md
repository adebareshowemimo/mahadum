# MAHADUM.360 — Roles & Permissions

Materialises the RBAC decision from *Backend Architecture* §2. Built on
**spatie/laravel-permission**. Roles are **global capabilities** — a role answers
*"what can this actor do"*, not *"in which org"*. Tenant scope is layered on
separately (see §1), which keeps global roles (`super_admin`, `content_owner`) and
family roles (`parent`, `student`) expressible alongside org roles.

- **Migration:** [`2026_01_01_000070_create_permission_tables.php`](database/migrations/2026_01_01_000070_create_permission_tables.php)
- **Seeder:** [`RolesAndPermissionsSeeder.php`](database/seeders/RolesAndPermissionsSeeder.php) (run via `DatabaseSeeder`)

---

## 1. Why not spatie "teams"

spatie's teams mode makes the role/permission **pivot** team column part of the
primary key and **non-nullable** — so it cannot store a global grant (null team).
That breaks `super_admin`/`content_owner` (global) and `parent`/`student` (family,
not org-scoped). So `teams` is **off** (`config/permission.php`) and tenant
isolation is enforced by three other layers that already exist:

1. **`IdentifyTenant` middleware** — validates the caller's `organization_user`
   membership and rejects cross-tenant access (403); binds `currentTenantId`.
2. **`BelongsToTenant` global scope** — filters tenant-scoped models to
   `currentTenantId` automatically.
3. **Policies** — `sameTenant()` / ownership checks on the specific record (§5).

So a `teacher` *can* `schools.assignments.create` everywhere as a capability, but
only ever sees/acts on **their org's** rows because of layers 1–3.

---

## 2. The 7 roles

| Role | Scope | Owns |
|---|---|---|
| `super_admin` | Global | Everything; settlement, payouts, org activation, content & language control |
| `content_owner` | Global | CMS authoring + publish, badges/leagues, lesson analytics |
| `school_admin` | Organization | School dashboard, roster + CSV import, classes, seats, invoices, org users/roles, the school's own referral code & payout requests |
| `teacher` | Organization | Own classes, assignments, learner progress, commissions & payout requests |
| `supervisor` | Organization | Read-only oversight across the org |
| `parent` | Family | Wallet, chores, review queue, enrollments, own subscriptions/telco, payouts |
| `student` | Family / Org | Own learning path — governed by **policies**, no global permissions |

> **super_admin bypass.** Assigning every permission already covers it, but a
> `Gate::before` (in [`AuthServiceProvider`](app/Providers/AuthServiceProvider.php))
> also auto-includes any permission added later:
> ```php
> Gate::before(fn ($user) => $user->hasRole('super_admin') ? true : null);
> ```

---

## 3. Permission catalogue (by admin aspect)

Naming convention: `aspect.subject.action`.

| Aspect | Permissions |
|---|---|
| **Content / CMS** | `content.languages.manage` · `content.courses.{view,create,update,delete,publish}` · `content.lessons.manage` · `content.quizzes.manage` · `content.media.upload` · `content.cultural.manage` |
| **Learning oversight** | `learning.enrollments.manage` · `learning.progress.view` · `learning.submissions.review` |
| **Gamification** | `gamification.badges.manage` · `gamification.leagues.manage` |
| **Family economy** | `family.manage` · `family.wallet.{view,fund}` · `family.chores.{manage,review}` · `family.reviews.handle` |
| **Billing** | `billing.plans.manage` · `billing.subscriptions.{view,manage}` · `billing.telco.{view,manage}` · `billing.databundles.manage` · `billing.invoices.{view,manage}` · `billing.health.view` · `billing.webhooks.view` |
| **Finance / settlement** | `finance.wallets.{view,adjust}` · `finance.ledger.view` · `commissions.{view,manage}` · `payouts.{view,request,approve}` · `settlements.{view,manage}` |
| **Referrals** | `referrals.{view,manage}` · `referrals.fraud.review` · `promocodes.manage` |
| **School ops** | `schools.dashboard.view` · `schools.roster.{view,import}` · `schools.classes.{view,manage}` · `schools.seats.{view,purchase}` · `schools.assignments.{create,review}` · `schools.analytics.view` |
| **Organizations** | `organizations.{view,manage,activate}` |
| **Users & access** | `users.{view,manage}` · `roles.{view,assign}` |
| **Analytics** | `analytics.{platform,lesson}.view` |
| **System** | `audit.view` · `support.handle` · `system.settings.manage` |

---

## 4. Role → permission matrix

`●` = granted. `super_admin` holds all (plus `Gate::before`).

| Permission group | content_owner | school_admin | teacher | supervisor | parent |
|---|:--:|:--:|:--:|:--:|:--:|
| content.* | ● | | | | |
| gamification.* | ● | | | | |
| schools.* | | ● | partial | partial | |
| schools.classes.view | | ● | ● | ● | |
| schools.assignments.{create,review} | | | ● | | |
| schools.roster.{view,import} | | ● | | view | |
| schools.seats.{view,purchase} | | ● | | | |
| schools.analytics.view | | ● | ● | ● | |
| organizations.view | | ● | | ● | |
| organizations.{manage,activate} | | | | | |
| users.{view,manage} / roles.{view,assign} | | ● | | | |
| billing.subscriptions.view | | ● | | | ● |
| billing.subscriptions.manage | | | | | ● |
| billing.invoices.view | | ● | | | |
| billing.telco.* / billing.databundles.manage | | | | | ● |
| billing.plans.manage / billing.health.view | | | | | |
| finance.* / settlements.* | | | | | |
| commissions.view | | | ● | | |
| payouts.view | | | ● | | ● |
| payouts.request | | ● | ● | | ● |
| payouts.approve | | | | | |
| referrals.view | | ● | ● | | ● |
| referrals.manage / fraud.review / promocodes | | | | | |
| family.* | | | | | ● |
| learning.enrollments.manage | | | | | ● |
| learning.progress.view | ● | ● | ● | ● | ● |
| learning.submissions.review | | | ● | | ● |
| analytics.lesson.view | ● | | | | |
| analytics.platform.view | | | | | |
| audit.view / system.settings.manage / support.handle | | | | | |

Blank cells under every listed role (e.g. `organizations.activate`, `finance.*`,
`settlements.*`, `billing.plans.manage`, `analytics.platform.view`,
`referrals.fraud.review`, `system.settings.manage`) are **super_admin-only** by design.

`analytics.class.view` was removed (2026-07-04): it duplicated `schools.analytics.view`
exactly (same 3 roles) and was never checked by any route — `GET
/classes/{class}/analytics` now explicitly enforces `schools.analytics.view` (in
addition to the `SchoolClassPolicy::view` ownership check), so the class-analytics
permission story has one source of truth instead of two.

---

## 5. Wiring (assignment is simple; org comes from membership)

Assigning a role is a one-liner — no team context to juggle:

```php
$user->assignRole('teacher');         // capability
$org->users()->attach($user->id, ['role' => 'teacher', 'status' => 'active']); // membership = scope
```

Which org a request acts in is resolved per-request by
[`IdentifyTenant`](app/Http/Middleware/IdentifyTenant.php) (Architecture §1.2): it
validates the membership above, rejects cross-tenant access (403), and binds
`currentTenantId` for the `BelongsToTenant` scope. `super_admin` runs unscoped.

Route guards then read naturally:

```php
Route::post('schools/{org}/students/import', [RosterController::class, 'import'])
    ->middleware('can:schools.roster.import');

Route::post('admin/payouts/{payout}/approve', [PayoutController::class, 'approve'])
    ->can('approve', 'payout'); // policy: permission + not-own-beneficiary
```

**Two enforcement layers.** A `can:<permission>` guard answers *"may this role do X
at all?"*. A **policy** (`can:<ability>,<binding>`) additionally answers *"on THIS
record?"* — combining the permission with ownership/tenant. Scaffolded policies:

| Policy | Capability + scope it adds |
|---|---|
| [`CoursePolicy`](app/Policies/CoursePolicy.php) | `content.*` + draft-vs-published; only owner (or publisher) edits |
| [`SchoolClassPolicy`](app/Policies/SchoolClassPolicy.php) | `schools.classes.*` + same-tenant; teacher always sees own class |
| [`PayoutPolicy`](app/Policies/PayoutPolicy.php) | `payouts.*` + beneficiary ownership; approver ≠ requester (SoD) |
| [`LearnerProfilePolicy`](app/Policies/LearnerProfilePolicy.php) | self / parent-owner / same-tenant staff — this is `student`'s self-access |

Registered in [`AuthServiceProvider`](app/Providers/AuthServiceProvider.php) (where the
`super_admin` `Gate::before` short-circuits every policy too).

---

## 6. Adding a permission later

1. Add the string to the right group in `RolesAndPermissionsSeeder::$groups`.
2. Add it to the roles that should hold it in `rolePermissionMap()`.
3. Re-run `php artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder`
   (idempotent — uses `findOrCreate` + `syncPermissions`).

No migration needed; permissions are data, not schema.
