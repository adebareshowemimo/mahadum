# Mahadum.360 — Database Migrations & Models

Generated from the *Backend Architecture* and *Learning Content Model* specs.
Drop into a Laravel 12/13 app: copy `database/migrations/*` and `app/Models/*`.

## Contents
- **70 migrations** (`database/migrations/`) — dependency-ordered (`2026_01_01_NNNNNN_*`),
  incl. `000070_create_permission_tables` (spatie, teams/org-scoped).
- **69 Eloquent models** (`app/Models/`) with relationships + casts.
- **`app/Models/Concerns/BelongsToTenant.php`** — row-level tenancy global scope.
- **`app/Models/User.php`** — Sanctum (`HasApiTokens`) + spatie (`HasRoles`).
- **`database/seeders/`** — `RolesAndPermissionsSeeder` (7 roles + granular,
  aspect-grouped permissions — see [Roles & Permissions](Mahadum360_Roles_Permissions.md)),
  `PlanSeeder` (billing tiers), `LanguageSeeder` (yo/ig/ha/pcm), wired via `DatabaseSeeder`.
  `DemoSeeder` (small local fixtures, auto-run on `db:seed` in local) and **`DevSeeder`**
  (large dataset for frontend dev — `php artisan db:seed --class=DevSeeder`; logins `*@dev.mahadum360`,
  password `Password123!`; idempotent guard, drop the DB to reseed).
- **`config/permission.php`** — spatie config with teams on + `organization_id` team key.
- **`app/Http/Middleware/IdentifyTenant.php`** — resolves tenant; binds it to the
  `BelongsToTenant` scope *and* the spatie team scope per request.
- **`app/Providers/AuthServiceProvider.php`** — `super_admin` Gate::before bypass + policy map.
- **`app/Policies/`** — `Course`, `SchoolClass`, `Payout`, `LearnerProfile` policies
  (permission = capability, ownership/tenant = scope; incl. payout separation-of-duties).
- **`routes/api.php`** — v1 API scaffold wired with `can:` permission + policy guards
  and `idempotency` on money POSTs (controllers are the next layer to generate).

## API reference
- **[`openapi.yaml`](openapi.yaml)** — OpenAPI 3.1 spec for the full v1 API (~80 operations,
  all tags). Validated against the 3.1 schema. Render with Swagger UI / Redoc, or import into
  Postman/Insomnia. Prose companion: [Mobile API Spec](Mahadum360_Mobile_API_Spec.md).

## Required packages (provide their OWN migrations — not duplicated here)
```bash
composer require stancl/tenancy          # tenant context (single-database mode)
composer require spatie/laravel-permission   # roles & permissions
composer require laravel/sanctum             # API auth (web cookie + mobile token)
composer require laravel/socialite           # Google login
php artisan migrate
php artisan db:seed   # roles/permissions + plans + languages (DatabaseSeeder)
```
> **Roles are global capabilities (spatie teams OFF).** This repo ships the
> permission-tables migration (`000070`) and `config/permission.php` — do **not**
> publish `--tag=permission-migrations` (it would overwrite `000070`). Tenant
> isolation is layered on separately (membership check + `BelongsToTenant` scope +
> policies), so role grants don't carry an org id. See
> [Roles & Permissions §1](Mahadum360_Roles_Permissions.md) for why teams is off.
> `users`, `personal_access_tokens` (Sanctum), `roles/permissions` (spatie), and the
> `tenants` table (stancl) come from Laravel / the packages. The included
> `*_add_fields_to_users_table` migration only **adds columns** to the shipped `users` table,
> so it must run *after* Laravel's base `users` migration (it does — it's dated 2026).

## Conventions used
- `id()` bigint PK, `timestamps()`, `softDeletes()` on user-facing records.
- Money = integer **minor units** (`*_minor`) + `currency`; coins = integers.
- Foreign keys: `constrained()->cascadeOnDelete()` (required) / `nullOnDelete()` (nullable).
- Polymorphic owners via `morphs()`: `wallets.owner`, `subscriptions.subscriber`,
  `referral_codes.owner`, `commissions/payouts.beneficiary`.
- Models use `protected $guarded = []` (mass-assignable) — tighten per policy if preferred.

## Multi-tenancy wiring
Tenant-scoped models (`SchoolClass`, `SeatAllocation`, `Invoice`, …) use `BelongsToTenant`,
which adds a global scope on `organization_id` and auto-fills it on create. Wire the current
tenant in a service provider / middleware:
```php
// after auth, e.g. from header X-Organization-Id validated against memberships,
// or stancl/tenancy's tenant():
app()->instance('currentTenantId', $organizationId);
```
Super-admin / cross-tenant queries: `Model::query()->withoutTenancy()->...`
Content tables (`languages`, `courses`, `lessons`, …) are **central / shared** — no scope.

## Not included (next layer)
Controllers, form requests, API resources, factories, and the remaining policies
(only `Course`/`SchoolClass`/`Payout`/`LearnerProfile` are scaffolded as patterns).
Auth/tenancy wiring (config, `IdentifyTenant` middleware, super-admin gate, policies),
the guarded `routes/api.php` scaffold, and the core seeders (roles/permissions, plans,
languages) **are** included. Ask and the rest can be generated next.
