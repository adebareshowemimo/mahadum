# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**MAHADUM.360** — a web-first platform for learning Nigerian languages (Yoruba, Igbo, Hausa, Pidgin) built for both **B2C families/diaspora** and **B2B schools**. Tagline (locked, single source `web/src/lib/brand.ts`): *"Learn the language. Live the culture. Connect the generations."*

Two apps in one repo:
- **Laravel 13 API** (PHP 8.3) at the repo root — `app/`, `routes/api.php`, `database/`.
- **React SPA** in `web/` (Vite + React Router + TanStack Query + axios + Tailwind v4).

The API is feature-complete for milestones 1–9; the active work is finishing the SPA. `docs/` holds the source-of-truth planning docs (see **Goal & guardrails** below) and a generated OpenAPI spec (`docs/openapi.yaml`). Note: `docs/app`, `docs/config`, etc. are a scaffold *mirror*, excluded from PHPStan — the real code is at the repo root.

## Goal & guardrails (do not derail)

Track work against these — before starting a feature, confirm it maps to one of them:
- `docs/Mahadum360_Implementation_TODO.md` — master phased plan (milestones 0–10), the definitive "what ships for MVP" list.
- `docs/Mahadum360_Admin_Portal_TODO.md` — the current focus: building out the **super_admin portal** (§0 hardening + §1 Organizations shipped; §2 Users + permission matrix is next). Follow its per-page "Definition of done".
- `docs/Mahadum360_Backend_Architecture.md`, `docs/Mahadum360_Roles_Permissions.md`, `docs/Mahadum360_Content_Model.md` — architecture/RBAC/content decisions. Prefer the decision recorded here over inventing a new one.

Product rules that override convenience (from the BRD): never gate/lock *learning* behind hearts or paywall (Free = full learning + ads); chore/assignment coins release **only** on parent approval; commissions sit in 14-day escrow with chargeback clawback; payouts enforce approver ≠ beneficiary (separation of duties).

## Commands

**Backend** (repo root):
```bash
composer dev            # all-in-one: serve + queue + pail logs + vite (concurrently)
php artisan serve --port=8000        # API only (the SPA proxies /api → :8000)
php artisan test                     # PHPUnit (sqlite :memory:)
php artisan test --filter=OrgTest    # single test / class
vendor/bin/pint                      # auto-fix code style;  pint --test to check only
vendor/bin/phpstan analyse --level=5 --memory-limit=512M   # Larastan (level 5)
composer ci             # pint --test + phpstan + test  (mirrors the CI "quality" job)
php artisan db:seed --class=Database\\Seeders\\DevSeeder    # rich local demo data
```

**Frontend** (`web/`):
```bash
npm run dev             # Vite dev server on :5173 (proxies /api + /sanctum to :8000)
npm run typecheck       # tsc --noEmit
npm test                # Vitest (incl. axe WCAG checks); npm run test:watch to watch
npm run build           # typecheck + vite build (mirrors the CI "web" job)
```

Both a Laravel server (`:8000`) **and** the Vite server (`:5173`) must run to use the SPA. CI (`.github/workflows/ci.yml`) blocks on: Pint, PHPUnit, PHPStan L5 (backend) and Vitest + `npm run build` (web) — keep all green.

## Architecture — the big picture

### Multi-tenancy (row-level, single DB)
- **Tenant = an `organizations` row** (school/institution). **Direct consumers** (families, diaspora adults) have `organization_id = NULL`. **Content tables carry no tenant column** — courses/lessons/quizzes/badges/plans are global and shared.
- Tenant-scoped models use the `BelongsToTenant` trait (global scope filters by current org + auto-fills `organization_id` on create).
- `IdentifyTenant` middleware (after `auth:sanctum`) resolves the tenant per request, precedence: **super_admin token → runs unscoped**; else `X-Organization-Id` header (validated against membership); else derived from the user's single org membership; else direct-consumer (own family). Cross-tenant access → 403.
- stancl/tenancy runs in **single-database mode**; spatie "teams" mode is **OFF** (it can't express global `super_admin`/`content_owner` or family `parent`/`student` grants) — isolation is done by the three layers above, not by spatie.

### RBAC (spatie/laravel-permission)
- 7 global-capability roles: `super_admin, content_owner, teacher, supervisor, school_admin, parent, student`. Permissions are named `aspect.subject.action` (e.g. `organizations.activate`, `content.courses.publish`).
- `super_admin` bypasses everything via `Gate::before` in `app/Providers/AuthServiceProvider.php` (also auto-covers permissions added later). Anything blank for all roles in `Roles_Permissions.md` §4 is super-admin-only by design.
- Two enforcement layers: `->middleware('can:<permission>')` ("may this role at all?") + Policies ("on THIS record?", adding ownership/same-tenant/SoD).

### Identity split (COPPA-aware)
`users` (authenticatable accounts) vs `learner_profiles` (the learner entity — a child under 13 has a profile with **no login**, operated by the parent) vs `families` (household owned by a parent user) vs `organizations` (tenant). An adult learner has both a `user` and a `learner_profile`.

### Money & sensitive ops
Currency is stored in **minor units** (kobo) as integers everywhere (`*_minor` columns/fields); format with `web/src/lib/format.ts#formatMoney`. Wallet/coin ledgers are append-only. Payment (Paystack/Flutterwave) and telco (SDP) gateways are behind swappable managers (`PaymentGatewayManager`/`TelcoGatewayManager`, env-opt-in, NullGateway otherwise) with **signed, idempotent webhooks**. Sensitive actions (activation, payouts, role/seat changes, org CRUD) call `AuditLogger::record(action, subject, before, after, orgId)`.

### Frontend structure
- **API layer** `web/src/lib/api/`: `client.ts` (axios; bearer token, `X-Organization-Id` tenant header, 401 auto-logout), `endpoints.ts` (per-domain objects: `authApi`, `adminApi`, `schoolApi`, …), `types.ts` (mirror Laravel resources — keep in sync), `errors.ts` (`ApiError` normalizes both custom + 422 dialects; use `err.fieldErrors` for per-field messages).
- **Auth**: `AuthProvider`/`useAuth` (backed by `GET /me`); `hasRole(...)`; guards in `components/auth/ProtectedRoute.tsx` — `ProtectedRoute`, `GuestRoute`, and `RoleRoute`/`AdminRoute` (super_admin-only).
- **Data fetching**: TanStack Query hooks live in `web/src/lib/<domain>/queries.ts` (e.g. `lib/admin/queries.ts`), keyed via a `<domain>Keys` object; mutations invalidate those keys.
- **Shell & nav**: one adaptive `AppLayout`; nav is role-filtered via `web/src/lib/nav/navigation.ts` (`visibleSections`). In `App.tsx`, destinations not yet built resolve to a `ComingSoon` placeholder — a route is "real" only when listed in the `REAL_PAGES` set.
- **Entitlements**: `useEntitlements` (from `/me`) + `PaywallGate` soft-gate family-economy pages by feature flag.

### Admin portal conventions (when extending it — this is the active work)
- Guard `/admin/*` routes with `<AdminRoute>` in `App.tsx` and add the path to `REAL_PAGES`.
- Reuse shared primitives in `web/src/components/admin/`: `AdminPageHeader`, `DataTable` (generic), `AdminToolbar` + `FilterSelect` (list pages filter client-side with `useMemo`).
- Backend: controller in `app/Http/Controllers/Admin/`, FormRequest in `app/Http/Requests/Admin/`, route in the `admin/` group in `routes/api.php` guarded by `can:<permission>`, audited. Frontend: add a method to `adminApi`, a hook in `lib/admin/queries.ts`, and a type in `lib/api/types.ts`.
- Read org-membership pivot data via the `OrganizationUser` model, **not** `$user->pivot` (PHPStan L5 flags the latter).

## Environment notes

- Windows host; the Bash tool is Git Bash (POSIX). `php artisan`, `composer`, `vendor/bin/*` work from the repo root; `npm` runs in `web/`.
- Local demo logins (password `Password123!`) from `DevSeeder`: `super@dev.mahadum360` (super_admin), `owner@dev…` (content_owner), `admin1@dev…` (school_admin), `teacher1@dev…`, `parent1@dev…`. `DemoSeeder` is a lighter set.
- **Verifying SPA forms**: React controlled inputs don't pick up programmatic DOM `.value` sets, so a scripted form-fill leaves submit disabled. To prove a backend flow end-to-end, hit the API directly with a super-admin bearer token (create one via `php artisan tinker` → `createToken`) rather than driving the form.
