# MAHADUM.360 — Admin Portal (Super Admin) Frontend TODO

A focused build plan to **complete the global-admin portal**. Backend for the core
Super-Admin surface (Phase 9) is ✅; this doc tracks the **remaining frontend pages,
the missing backend seams they need, and portal-level hardening** so the
`super_admin` role has UI for every capability the RBAC catalogue grants it.

**Legend:** ✅ done · 🟡 in progress / partial · ⬜ not started
**Tags:** `[MVP]` ships for launch · `[POST]` deferred · `[BE]` needs a backend seam first
**⭐ = raised in the live portal review (2026-07-01).**

> **Scope.** Everything the `super_admin` role owns per
> [`Roles_Permissions.md`](Mahadum360_Roles_Permissions.md) §4 — the permissions that
> are *blank for every other role*. Reuses the adaptive `AppLayout` shell; all routes
> live under `/admin/*` and appear in the **Admin** nav section
> (`web/src/lib/nav/navigation.ts`).

---

## Status snapshot (2026-07-01)

**Landed (✅):** the 4 core Phase-9 pages, all wired to `adminApi`
(`web/src/lib/api/endpoints.ts`) via `web/src/lib/admin/queries.ts`:

| Page | Route | Component | Backend |
|---|---|---|---|
| Overview | `/admin` | `AdminOverviewPage` | `GET /admin/metrics` + `GET /admin/billing/health` ✅ |
| Settlements | `/admin/settlements` | `SettlementsPage` | `GET /admin/settlements` ✅ (read-only tables) |
| Organizations | `/admin/orgs` | `OrganizationsPage` | `GET /admin/organizations` + `POST …/activate` ✅ (**list + activate only**) |
| Promo codes | `/admin/promos` | `PromoCodesPage` | `POST /admin/promo-codes` ✅ |

**Live-review gaps (⭐, 2026-07-01):** organizations need full CRUD + drill-down + admin
create (§1); a global users directory with filters (§2); a permission-matrix view (§2);
an admin course list with filters + publish control (§3); income reporting (§5); a
reports/exports hub (§5); payment-gateway setup (§6). Plus the earlier gaps: payout
approval UI (§4), fraud review (§7), audit log (§8), system settings (§9), billing
plans (§10), and **portal hardening** — `/admin/*` has **no per-route role guard** today
(nav hides links, but routes are directly reachable).

---

## 0. Portal shell & hardening `[MVP]` — do first (unblocks everything)

- [x] ✅ **`AdminRoute` role guard.** Added `RoleRoute` + `AdminRoute` in
  `web/src/components/auth/ProtectedRoute.tsx`; `/admin/*` routes wrapped in `App.tsx`.
  A non-`super_admin` hitting `/admin` is redirected to `/home` (loading/unauth cases
  guarded too). *(Verified live: pages render for super_admin.)*
- [x] ✅ **Admin nav + sub-nav** — `/admin/orgs/new` + `/admin/orgs/:id` in `REAL_PAGES`,
  and a **secondary tab bar** (`AdminSubNav` + `AdminLayout`) grouping Overview · Orgs ·
  Users · Content · Finance · Reports · System, wrapping all `/admin/*` routes. Active group
  resolved by longest-prefix match (`/admin/settings/gateways` → Finance, not System).
  *(Verified live across routes; 16 regression tests. Uses plain `Link` — `NavLink`'s
  auto-`aria-current` mis-marked Overview on every sub-route.)*
- [x] ✅ **Drop shipped routes from the `ComingSoon` fallback** (`REAL_PAGES` in `App.tsx`).
- [x] 🟡 **Shared admin primitives** — `web/src/components/admin/`: `AdminPageHeader`
  (title + back link + actions), `DataTable` (generic, loading skeletons, empty state,
  row-click), `AdminToolbar` + `FilterSelect` (search + dropdown filters). *(Pagination +
  CSV export + detail `Drawer` deferred until a list needs them.)*
- [x] ✅ **A11y + tests** — dedicated Vitest tests for the `AdminRoute` guard
  (`ProtectedRoute.test.tsx`: super_admin admitted, all 6 other roles → `/home`, loading +
  unauth cases), table filters (`DataTable.test.tsx`: rows/empty/loading/row-click + axe,
  search + `FilterSelect` narrowing), and mutating actions (`UsersPage.test.tsx`: role
  grant/revoke, self-lockout 422, suspend/reactivate). *(Suite now green at 72 tests incl.
  axe; typecheck clean.)*

---

## 1. Organizations — full CRUD + drill-down ⭐ `[MVP]` `[BE]`  *(review #1, #2)*

Today `/admin/orgs` is **list + activate only**; the backend
[`Admin\OrganizationController`](../app/Http/Controllers/Admin/OrganizationController.php)
has just `index` + `activate`. Admin needs **in-depth access to each org and complete
CRUD**, plus the ability to **create/generate a new organization** (orgs currently only
arrive via school self-registration).

- [x] ✅ `[BE]` `GET /admin/organizations/{org}` — full detail: profile, status, members +
  roles, seats, invoices, counts (members/classes/families/learners). *(Verified live.)*
- [x] ✅ `[BE]` `POST /admin/organizations` — admin-create an org (name, type, contact,
  domain, CAC, starting status; auto-slug; audited). *(review #2; verified.)*
- [x] ✅ `[BE]` `PATCH /admin/organizations/{org}` — edit profile (audited).
- [x] ✅ `[BE]` `POST /admin/organizations/{org}/status` — reversible lifecycle
  (active/suspended/inactive/pending, audited). `activate` kept as a dedicated verb.
  **No hard delete** (orgs own tenant data; soft-delete only via model).
- [x] ✅ `adminApi` methods (`organization`, `createOrg`, `updateOrg`, `setOrgStatus`) +
  hooks (`useAdminOrganization`, `useCreateOrg`, `useUpdateOrg`, `useSetOrgStatus`).
- [x] ✅ **Org list** — filterable `DataTable`: search by name, filter by type + status;
  row → detail. *(Verified live.)*
- [x] ✅ **Org detail page** (`/admin/orgs/:id`) — drill-down: stat cards, profile,
  inline **Edit** modal, **Activate/Suspend**, and a **tabbed layout** (Overview · Members ·
  Classes · Invoices · Referrals · Audit) deep-linked via `?tab=`. `show()` extended to
  return classes (w/ student counts), org-owned referral codes, and the org's recent audit
  trail. *(BE + web typecheck/build green; feature tests cover the payload.)*
- [x] ✅ **Invite first admin** — `POST /admin/organizations/{org}/invite-admin` creates a
  `school_admin` account bound to the org (unusable password until reset), grants the role +
  membership, emails a set-password link (the invite), audited (`organization.admin_invited`).
  Rejects an already-registered email (422 → use the Users directory). **Invite admin** button
  + modal on the detail page. *(Feature tests: creates role+membership+audit; duplicate-email 422.)*
- [x] 🟡 **Create-org flow** (`/admin/orgs/new`) — full form. *(Invite-admin now available from
  the org detail page for any org; an at-creation invite step could still be added here.)*

---

## 2. Users directory + permission matrix ⭐ `[MVP]` `[BE]`  *(review #3, #4)*

No global user endpoint or UI exists today (`users.manage` / `roles.assign` are
super-admin-only). Admin needs **a list of all users with filters** and a **permission
matrix** view.

**Users directory (review #3)** — ✅ shipped 2026-07-01
- [x] ✅ `[BE]` `GET /admin/users` — paginated + **filterable** (search name/email/phone/
  username, role, status, organization). `Admin\UserController@index`.
- [x] ✅ `[BE]` `POST /admin/users/{user}/roles` (assign/revoke, `can:roles.assign`) +
  `POST /admin/users/{user}/status` (suspend/reactivate, `can:users.manage`). Both audited;
  **self-lockout guards** (can't revoke own super_admin / suspend self → 422). *(Verified.)*
- [x] ✅ `adminApi` (`users`, `assignUserRole`, `setUserStatus`) + hooks
  (`useAdminUsers` w/ `placeholderData`, `useAssignUserRole`, `useSetUserStatus`).
- [x] ✅ **Users page** (`/admin/users`) — filterable/paginated `DataTable` (debounced
  search + role/status filters + prev/next), row → user modal (profile, role
  grant/revoke chips, suspend/reactivate). Now with an **Organizations column** + membership
  list (role/status) and **joined/last-seen activity** in the modal, fed by
  `UserController@index` (org memberships prefetched via `OrganizationUser`, no N+1). *(7
  Vitest cases incl. membership rendering.)*

**Permission matrix (review #4)** — ✅ shipped 2026-07-01
- [x] ✅ `[BE]` `GET /admin/roles` (`Admin\RoleController`) — roles, permissions grouped
  by aspect, and the grant matrix (from spatie).
- [x] ✅ **Permission matrix page** (`/admin/roles`) — role→permission grid (roles as
  columns, permissions grouped by aspect as rows, ● granted), sticky first column.
  **View-only** (editing grants deliberately not exposed). *(Verified live.)*

---

## 3. Courses — admin list, filters & publish control ⭐ `[MVP]` `[BE-partial]`  *(review #8)*

Course CRUD + an `is_published` flag already exist
([`Content\CourseController`](../app/Http/Controllers/Content/CourseController.php);
lesson publish via `POST /lessons/{id}/publish`). The current `/courses` screen is the
**content-owner builder**, not an admin oversight list. Admin wants **a course list with
filters and complete control over which courses are published**.

- [x] ✅ `[BE]` **course-level publish toggle** — `POST /courses/{course}/publish` +
  `/unpublish` guarded by `content.courses.publish`. Publish-rule enforced: a course
  needs ≥1 **published lesson** (`lessons.published_at`) or 422. *(Verified.)*
- [x] ✅ `[BE]` `GET /courses` enhanced — `status` + `q` filters, and `owner` +
  `levels_count` on `CourseResource` (paginated; already returns drafts to CMS roles).
- [x] ✅ `contentApi` (`coursesPaged`, `publishCourse`, `unpublishCourse`) + hooks
  (`useAdminCourses`, `useSetCoursePublished`).
- [x] ✅ **Admin courses page** (`/admin/courses`) — filterable/paginated `DataTable`
  (search + language + status), owner + levels columns, per-row **publish/unpublish**.
  *(Verified live: toggle draft↔published works; publish-rule 422 surfaced inline.)*
  *(Distinct from Content › Courses, which stays the authoring builder.)*

---

## 4. Finance — payout approval ⭐-adjacent `[MVP]` — ✅ shipped 2026-07-01

- [x] ✅ `[BE]` `GET /admin/payouts?status=` (`Referral\PayoutController@adminIndex`) —
  every payout with a resolved **beneficiary label** (user/org name) + status filter.
- [x] ✅ `adminApi.payouts` / `approvePayout` + hooks (`useAdminPayouts`,
  `useApprovePayout` — invalidates payouts + settlements).
- [x] ✅ **Payout approval queue** (`/admin/payouts`) — status filter (defaults to
  *awaiting approval*), beneficiary + amount + method + requested date, per-row
  **Approve**. Floor ₦5k / cap ₦50k copy in the header. Surfaces the **SoD 403**
  ("cannot approve a payout you would receive") and the **409** ("only a requested
  payout can be approved") inline. *(Verified live: approve removes the row; backend
  SoD/409 guards confirmed.)* *(Reject/hold ⬜ — backend has no such transition yet.)*

---

## 5. Income reporting & Reports hub ⭐ `[MVP]` `[BE]`  *(review #5, #7)*

Only aggregates exist today (`metrics.revenue_minor`, settlements telco revenue). Admin
wants an **income reporting table** and a general **reports** area.

**Income report (review #5)** — ✅ shipped 2026-07-01
- [x] ✅ `[BE]` `GET /admin/reports/income?from&to` (`Admin\ReportController@income`) —
  revenue by **channel** (card/wallet funding · telco VAS · school invoices) × **month**,
  gross vs refunds vs net, DB-agnostic (grouped in PHP), default trailing-6-months window.
  Guarded by `analytics.platform.view`.
- [x] ✅ `adminApi.incomeReport(params)` + `useIncomeReport` hook.
- [x] ✅ **Income page** (`/admin/reports/income`) — date-range inputs, Gross/Refunds/Net
  summary cards, channel × month matrix with totals row, **CSV export**. *(Verified live:
  numbers reconcile with source data; net ₦20,787.30 across 3 channels.)*

**Reports hub (review #7)** — ✅ shipped 2026-07-01
- [x] ✅ `[BE]` `GET /admin/reports/growth` (new users + orgs by month + totals) and
  `GET /admin/reports/subscriptions` (new subs by month + status mix + active count),
  sharing a `window()`/`countByMonth()`/`series()` helper set with income. Guard
  `analytics.platform.view`.
- [x] ✅ `adminApi` (`growthReport`, `subscriptionsReport`) + hooks; reusable
  `MonthlyTable` component (`components/admin`).
- [x] ✅ **Reports hub** (`/admin/reports`) — card index → Income / Growth /
  Subscriptions. Nav item "Income" replaced by "Reports" → the hub. *(Verified live:
  growth 48 users/8 orgs, subscriptions 23 total/18 active — both reconcile with source
  data.)*
- [x] ✅ **Referrals & commissions report** (2026-07-02) — `GET /admin/reports/referrals`
  (new referrals by month + referral status mix + commissions by status with ₦ totals);
  `/admin/reports/referrals` page + hub card (4th report). *(Verified: 40 referrals,
  status mix, commission totals reconcile.)*
- [x] ✅ **Billing-renewal report** (2026-07-03) — `GET /admin/reports/renewals`
  (forward-looking window): active subscriptions due to renew bucketed by month, expected
  renewal revenue (each sub's plan price), payment-method mix, and reminder coverage.
  `/admin/reports/renewals` page (count + revenue `MonthlyTable`s, summary cards) + hub card
  (6th report). *(2 feature tests; verified live: 18 renewals / ₦36,000 expected / 1-of-18
  reminded reconcile.)*

---

## 6. Payment gateway setup ⭐ `[MVP]` — ✅ shipped 2026-07-01  *(review #6)*

> **Decision (resolved):** secrets stay **env-based** (PCI-safe — never in the app DB,
> never returned to the client). The **Paystack + Flutterwave integration itself was
> already complete**: hosted-checkout drivers (`PaystackGateway`/`FlutterwaveGateway`),
> `PaymentGatewayManager` (live opt-in via `PAYMENT_GATEWAY_LIVE`, else `NullGateway`),
> signature-verified idempotent webhooks (charge success + refunds), wired into
> `SubscriptionController` + `WalletController`. §6 adds the **admin console** over it.

- [x] ✅ `[BE]` `GET /admin/payment-gateways` (`Admin\GatewayController`) — per-provider
  status: configured?, live mode, default, webhook URL, per-env-var requirement checklist.
  Secrets never returned.
- [x] ✅ `[BE]` `POST /admin/payment-gateways/{provider}/test` — **live credential ping**
  (harmless authenticated read; moves no money) → `{ ok, message }`. Guard
  `system.settings.manage`.
- [x] ✅ `adminApi` (`paymentGateways`, `testGateway`) + hooks.
- [x] ✅ **Gateway console** (`/admin/settings/gateways`) — live-mode banner, per-provider
  cards (configured/default badges, env-var checklist, copyable webhook URL, **Test
  connection** with inline result). *(Verified live: test hit the real Paystack API and
  reported HTTP 401 for the placeholder dev key.)*

---

## 7. Referral fraud review `[MVP]` — ✅ shipped 2026-07-01

- [x] ✅ `[BE]` `GET /admin/referrals/flagged` (`Admin\FraudController`) — flagged/frozen
  codes with owner + total & 24h sign-up counts. `POST …/{clear|freeze}` transitions
  (clear → active, freeze → frozen), audited (`referral.cleared`/`referral.frozen`).
  Guard `referrals.fraud.review`.
- [x] ✅ `adminApi` (`flaggedReferrals`, `clearReferral`, `freezeReferral`) + hooks.
- [x] ✅ **Fraud queue** (`/admin/fraud`) — table (code/kind, owner, 24h/total velocity,
  status) + **Confirm fraud** (freeze) / **Clear** (release) actions + empty state.
  *(Verified live: cleared a flagged code → it left the queue; freeze/clear transitions
  confirmed via API; 39 web tests green.)*

---

## 8. Audit log viewer `[MVP]` — ✅ shipped 2026-07-01

- [x] ✅ `[BE]` `GET /admin/audit-logs` (`Admin\AuditController`) — paginated (25/pg) +
  filterable (action, free-text over actor/action/IP, date range) + distinct-actions list
  for the dropdown. Guard `audit.view`.
- [x] ✅ `adminApi.auditLogs` + `useAuditLogs` hook.
- [x] ✅ **Audit page** (`/admin/audit`) — search + action dropdown + date range,
  paginated table (when/action/actor/subject/IP), row → detail **modal** with actor,
  IP, subject, and **before/after JSON**. *(Verified live: every admin action from the
  session captured; modal shows payout before `{status:requested}` → after
  `{status:approved, amount_minor:1500000}`.)*

---

## 9. System settings `[MVP]` — ✅ shipped 2026-07-01

Added a **DB-backed settings store** (`settings` table + `Setting` model + `Settings`
service) that overrides config defaults from a whitelist (`config/settings.php`). Reads
are wired into the real call sites so edits take effect with no redeploy.

- [x] ✅ `[BE]` `settings` migration + `Settings` service (typed get/set, cached,
  whitelist-only writes) + `config/settings.php` schema (compliance, referral floor/cap,
  feature flags).
- [x] ✅ `[BE]` `GET /admin/settings` + `PATCH /admin/settings` (`Admin\SettingsController`)
  — per-key validation (type/min/max; dotted keys validated individually to avoid
  Laravel dot-notation collisions), audited (`system.settings_updated`). Guard
  `system.settings.manage`.
- [x] ✅ **Wired reads** — `ConfigController` (digital_age + feature_flags),
  `FamilyController` (consent age gate), `RequestPayoutRequest` (floor),
  `PayoutController` (monthly cap) now read via `Settings::get` with config fallback.
- [x] ✅ `adminApi` (`settings`, `updateSettings`) + hooks (invalidates `config`).
- [x] ✅ **Settings page** (`/admin/settings`) — grouped form (Compliance, Referrals &
  payouts, Feature flags), typed inputs + switches, save + field errors. *(Verified live:
  toggled a flag → saved → propagated to public `/config`; 85 backend + 39 web tests green.)*

---

## 10. Billing plans management `[MVP]` `[BE]`

`billing.plans.manage` — plans are **seeded only** (`GET /plans` reads them); no admin
CRUD. Prices/entitlements shouldn't need a redeploy.

- [ ] `[BE]` `POST/PATCH /admin/plans` (+ entitlement editing).
- [ ] **Plans page** (`/admin/plans`) — list, edit price/interval/entitlements,
  create/retire; warn on changes affecting active subscribers.

---

## 11. Content-language control & support

- [x] ✅ **Language control** (2026-07-01) — `Admin\LanguageController`:
  `GET /admin/languages` (all incl. inactive + total/published course counts) +
  `PATCH /admin/languages/{language}` (toggle `is_active`, audited). Guard
  `content.languages.manage`. **Languages panel** (`/admin/languages`) — per-language
  Switch, Live/Hidden badge, script/RTL + course readiness. *(Verified live: toggling a
  language off drops it from the public `/config`; restores on re-enable.)*
- [x] ✅ **Ordering** (2026-07-03) — added a `position` column (migration seeds a
  deterministic alphabetical order); `LanguageController@index` orders by it and
  `POST /admin/languages/reorder` persists a new order (audited). The public `/config`
  honours it. `LanguagesPage` gains up/down chevrons per row. *(Verified live: moving a
  language up posts the new order and it persists across reload + into `/config`.)*
- [x] ✅ **Support triage queue** (2026-07-03) — extended the existing `support_tickets`
  scaffold (added email/category/message/response/resolved_at). Consumer: `/support`
  contact form + own-tickets list with admin replies (`POST/GET /support/tickets`).
  Admin: `/admin/support` (`Admin\SupportController`, `can:support.handle`) — paginated
  queue + status filter + search + open-count, detail modal to set status/priority +
  write a response, audited. *(Verified live: user raises → admin resolves w/ reply →
  queue clears; user sees the reply.)*
- [x] ✅ **Multi-message thread + assignee picker** (2026-07-03) — new
  `support_ticket_messages` table (+ `SupportTicketMessage` model, `messages()` relation;
  migration backfills the legacy single `response` into the thread). Admin modal now shows
  the full conversation + a **reply composer** (`POST /admin/support-tickets/{t}/messages`,
  auto-advances `open`→`in_progress`, audited) and an **assignee dropdown** fed by
  support-handler staff (`assignees` in the index payload). Requester side: threaded view +
  reply on own tickets (`POST /support/tickets/{t}/messages`, reopens a resolved ticket;
  403 on a foreign ticket). *(4 feature tests: staff reply, requester reply/reopen, SoD 403.)*

---

## 12. Media library scalability ⭐ `[MVP]` — ✅ shipped 2026-07-01

The Content › Media page (`/media`) and `GET /media` capped at the **latest 100 assets**
and the SPA loaded them all at once — unusable at 10k+ assets. Now paginated + filterable
+ searchable, server-side.

- [x] ✅ `[BE]` Paginated `GET /media` (clamped `per_page`, default 24, meta) + `type`
  filter + **filename search**. Added nullable `original_name` column (+ `type` index);
  `upload` stores `getClientOriginalName()`.
- [x] ✅ `contentApi.mediaLibrary(params)` (paginated) + `useMediaLibrary`; kept
  `media(params)` for the picker (passes `type`/`per_page`). Shared `['content','media']`
  key prefix so upload/delete refresh both.
- [x] ✅ **Media page** — server-side paginated grid + type filter + debounced filename
  search + prev/next + total count; upload/copy/delete preserved; lazy `<img>` +
  `preload="none"` video previews.
- [x] ✅ Video picker (`AddVideoModal`) requests `type=video&per_page=100`.
  *(Verified live with 251 seeded assets: 24/page × 11 pages, type=video → 1, filename
  search → exact hit. Backend Pint+PHPStan clean; 39 web tests green.)*
- [x] ✅ **Infinite-scroll grid** (2026-07-03) — `useMediaLibraryInfinite`
  (`useInfiniteQuery`, page cursor) + an `IntersectionObserver` sentinel (`rootMargin:400px`)
  that auto-loads the next page as it nears the viewport; prev/next removed, terminal
  "All N assets loaded" state. *(Verified live: single-page renders + sentinel; fixed a
  React-18 callback-ref warning caught in the live console — refs must not return a cleanup.)*
- [x] ✅ **Orphan cleanup** (2026-07-03) — no vendor needed: `MediaController` maps the 11
  media-referencing FK columns (videos, questions, question_options, speaking_prompts,
  flashcards, cultural_contents, submissions, invoices, competition_entries), computes the
  referenced-id union, and exposes `GET /media/orphans` (paginated unreferenced assets +
  count) and `POST /media/orphans/purge` (bulk delete, re-checks each id against live refs,
  deletes files + rows, audited `media.orphans_purged`). Media page gains an "N unused
  assets" banner → **Clean up** modal (checklist + select-all + delete). *(3 feature tests;
  verified live: 3 orphans → purge → 0, referenced assets untouched, 6→3 total.)*
- [ ] *(Genuinely blocked: video **thumbnail/poster generation** — needs ffmpeg or a managed
  video vendor's transcode API, which isn't wired into this repo. Images already self-preview;
  audio/video show placeholder previews.)*

### Scalability sweep (2026-07-01)
Audited every `index` endpoint for the same unbounded-load problem. Most are naturally
bounded (per-org / per-family lists, or tiny lookup tables: roles, languages, plans,
gateways, settings). The one platform-wide risk — **`GET /admin/organizations`** (loaded
*all* orgs, filtered client-side) — is now ✅ **server-side paginated** (20/pg) with
`q`/`type`/`status` filters + distinct-types dropdown; `OrganizationsPage` converted to
server-side pagination. *(Verified live: filters/paginate hit the API; 85 BE + 39 web
tests green.)*
- [x] ✅ Admin payout queue (`/admin/payouts`) also paginated (20/pg, status filter +
  meta); `PayoutsPage` converted to server-side pagination. *(Verified live.)*

**Every growable list in the app now paginates server-side** — media, organizations,
users, courses, audit, payouts, and all reports. Remaining unbounded reads are all
naturally small (per-org / per-family lists, lookup tables).

## 13. Billing gaps closed (pricing review follow-up) ✅ 2026-07-01

Surfaced by the pricing/subscription walkthrough — the three things that existed as
schema/permission but had no working flow:

- [x] ✅ **Renewal reminders** — `SubscriptionRenewalReminder` notification (email/push/SMS)
  + `subscriptions:remind` daily command (09:00) that nudges card/invoice subs renewing
  within N days (telco auto-bills, skipped). `renewal_reminded_at` column guards against
  re-sending within a cycle. *(Verified: 1 sent for a due card sub; re-run → 0.)*
- [x] ✅ **Plan pricing admin** — `Admin\PlanController` (list + `PATCH` price/interval/
  max_profiles/feature-flags), guarded `billing.plans.manage`, audited. **`/admin/plans`**
  page: per-tier price (₦) + profile cap + feature toggles, save. *(Verified live.)*
  Replaces the code-only `PlanSeeder` for changing prices.
- [x] ✅ **Promo redemption at checkout** — `PromoService` (validate: active/window/tier/
  cap/**single-use per user**; discount percent|fixed) + `POST /subscriptions/promo-preview`
  + `promo_code` on subscribe (charges the discounted amount, records `PromoRedemption`
  with `user_id`/`subscription_id`, bumps `redeemed_count`). Paywall gains a promo field
  that previews the discount per applicable tier. *(Verified: 20% code → ₦1,500→₦1,200,
  tier-restricted, reuse blocked.)*
- Also fixed 6 **pre-existing** `nullsafe.neverNull` PHPStan errors in `MeController`/
  `SubscriptionController` (full `phpstan analyse` was actually red before; now 0 errors).
- [x] ✅ **Custom plan creation + cadences** (2026-07-02) — admins can now *create* new
  tiers, not just edit the fixed 5. `POST /admin/plans` (code/name/price/interval/
  audience/max_profiles/features, audited). Added a **`quarter`** interval (renewal
  helpers in `SubscriptionController` + `PaymentService` → +3 months) alongside month/
  year/term/week, and an **`audience`** column (individual/family/teacher/school/any) so
  a plan can target parents, teachers, or schools. `/admin/plans` gains a **New plan**
  form (cadence + audience dropdowns) and cadence/audience editing on each card; the
  consumer paywall excludes `school`/`teacher`-audience tiers. *(Verified: created a
  quarterly family plan → +3-month renewal; duplicate code → 422; UI lists it.)*

## 14. Admin polish (2026-07-03)

- [x] ✅ **Payout reject** — `POST /admin/payouts/{payout}/reject` (`PayoutController@reject`,
  requested→rejected, audited, `can:payouts.approve`). `PayoutsPage` queue gains a
  **Reject** button beside Approve; 409 on a non-pending payout. *(Verified live: reject
  removes the row; DB rejected count advances.)*
- [x] ✅ **Org/school activity report** — 5th reports-hub card. `GET /admin/reports/org-activity`
  (new orgs by month + status mix + org/class/student totals) → `/admin/reports/org-activity`.
  *(Verified: 8 orgs / 16 classes / 239 students, reconciles.)*

Reports hub now: Income · Growth · Subscriptions · Referrals · Organizations & schools.

## Suggested order

1. **§0 portal hardening** (guard + shared `DataTable`) — unblocks everything.
2. **§1 Organizations CRUD** + **§2 Users/matrix** + **§3 Courses** — the review's core asks.
3. **§4 payout approval** (BE route already exists) → **§5 income/reports**.
4. **§6 gateway setup** (resolve the secrets `[BLOCK]` first) + **§9 settings** + **§10 plans**.
5. **§7 fraud** + **§8 audit**, then **§11 languages / support** `[POST]`.

## Definition of done (per page)
Guarded by `AdminRoute` · typed `adminApi` method · filterable/paginated where it's a
list · loading / empty / error states · mutating actions confirmed + audited +
query-invalidated · secrets never returned to the client · axe-clean · Vitest coverage
on the guard, filters, and each action · removed from the `ComingSoon` fallback and added
to the Admin nav.
