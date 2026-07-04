# MAHADUM.360 — Teacher Portal Frontend/Backend TODO

A focused build plan to **complete the `teacher` role's surface** — the next
milestone after Super Admin (Milestone 8, "School operations", in
`Mahadum360_Implementation_TODO.md`). Grounded in a full code audit
(2026-07-04): `teacher` is seeded with 12 permissions in
[`RolesAndPermissionsSeeder.php`](../database/seeders/RolesAndPermissionsSeeder.php:225-238),
but three of them (`schools.assignments.create`, `schools.assignments.review`,
`learning.submissions.review`) are **orphaned — granted but wired to zero
backend routes**, and one (`commissions.view`) has no controller reference at
all.

**Legend:** ✅ done · 🟡 in progress / partial · ⬜ not started
**Tags:** `[MVP]` ships for launch · `[POST]` deferred · `[BE]` needs a backend seam first

---

## Status snapshot (2026-07-04, updated same day after §0+§1 shipped)

**Landed (✅):**

| Page | Route | Component | Backend |
|---|---|---|---|
| Classes | `/classes` | `ClassesPage` | `GET /classes`, `GET /classes/{id}`, `GET /classes/{id}/analytics` ✅ (view-only: teacher lacks `schools.classes.manage`, correctly so — class creation is `school_admin`'s job) |
| Assignments | `/assignments` | `AssignmentsPage` | `GET/POST /classes/{class}/assignments`, `GET /classes/{class}/assignments/{assignment}`, `POST /class-assignments/{assignment}/submissions`, `POST …/submissions/{submission}/grade` ✅ — teacher-owned, tenant-scoped, coin release on pass. *(§1 shipped 2026-07-04; verified live incl. cross-teacher 403 and full create→submit→grade→wallet-credit flow.)* |
| Earnings | `/earnings` | `EarningsPage` | `GET /payouts`, `POST /payouts/request` ✅ — **but this is the referral-commission system**, not teaching pay (see §3, still blocked) |

**Orphaned permissions — now resolved:**
- `schools.assignments.create` / `.review` — wired to the new `ClassAssignmentController` routes (§1). ✅
- `learning.submissions.review` — still unused (grading is gated via `schools.assignments.review` + explicit ownership check instead, matching the `schools.*` permission naming); harmless duplicate grant, not blocking.
- `commissions.view` — still orphaned, tracked in §3 (blocked on the teacher-commission-payout product decision).

**Existing `Assignment`/submission models are the wrong shape for this.** The
current `Assignment` model (`app/Models/Assignment.php`) is a **CMS
lesson-component config** authored by `content_owner` (prompt, expected
media, coin reward) — not something a teacher assigns to a class. The two
submission-review flows that exist (`ChoreController`, `ReviewController` in
`app/Http/Controllers/Family/`) are **parent-review, family-scoped** (gated by
`family.reviews.handle`), not teacher-graded. A teacher-facing "assignment"
needs a **new model pair**, not a re-skin of the family flow.

---

## 0. Portal shell & hardening `[MVP]` — do first

- [x] ✅ **`TeacherRoute` guard.** Added `TeacherRoute` (`RoleRoute
  roles={['teacher']}`) in `components/auth/ProtectedRoute.tsx`, mirroring
  `AdminRoute`. `/classes`, `/assignments`, `/earnings` wrapped in `App.tsx`.
  24 Vitest cases incl. every non-teacher role redirecting to `/home`.
- [ ] **Decide `analytics.class.view` vs `schools.classes.view` gating.**
  `SchoolClassController::analytics` is currently gated by the `view` class
  policy ability, which only checks `schools.classes.view` + ownership — the
  distinct `analytics.class.view` permission (also granted to teacher,
  `school_admin`, `supervisor`) is never independently enforced. Either wire
  it explicitly or drop it from the matrix as redundant — don't leave a
  permission that looks enforced but isn't. *(Not touched by §1 — the new
  assignment routes reuse the same `view`-ability pattern for reads.)*
- [x] ✅ **`/assignments` shipped** (§1) — no longer a nav-only stub.

---

## 1. Class assignments — new model + teacher authoring `[MVP]` `[BE]` — ✅ shipped 2026-07-04

- [x] ✅ `[BE]` **`ClassAssignment`** model (`app/Models/ClassAssignment.php`,
  tenant-scoped via `BelongsToTenant` like `SchoolClass`) —
  `school_class_id`, `title`, `instructions`, `due_at`, `coin_reward`,
  `created_by`. Migration: `2026_07_04_140000_create_class_assignments_table.php`.
  *(Skipped the optional lesson/course link from the original plan — no
  product ask for it yet; easy to add a nullable FK later.)*
- [x] ✅ `[BE]` **`ClassAssignmentSubmission`** model
  (`app/Models/ClassAssignmentSubmission.php`) — `class_assignment_id`,
  `learner_profile_id`, `media_asset_id`, `status` (`submitted`/`graded`),
  `passed` (bool — see decision below), `score` (0–100, optional detail),
  `feedback`, `graded_by`, `graded_at`. Migration:
  `2026_07_04_140100_create_class_assignment_submissions_table.php` (unique
  index per learner per assignment).
- [x] ✅ `[BE]` `GET/POST /classes/{class}/assignments`,
  `GET /classes/{class}/assignments/{assignment}` — `App\Http\Controllers\School\ClassAssignmentController`.
  Reads gated by the existing `view`-on-`SchoolClass` ability; `store` gated
  by `can:schools.assignments.create` **plus** an explicit
  `$class->teacher_user_id === $user->id` ownership check (only `teacher`
  holds this permission per the matrix, so this is the sole owner check
  needed — no separate policy class).
- [x] ✅ `[BE]` `POST /classes/{class}/assignments/{assignment}/submissions/{submission}/grade`
  — coins release **only on pass**, inside `DB::transaction`, via the same
  `WalletService::credit` used by `ReviewController::review`; audited
  (`class_assignment.graded`); fires `ClassAssignmentGraded` notification
  after commit. Guard `can:schools.assignments.review` + ownership check.
  Re-grading an already-graded submission → 422.
- [x] ✅ `[BE]` `POST /class-assignments/{assignment}/submissions` — learner
  (or parent/same-tenant staff per `LearnerProfilePolicy::view`) submits;
  enrollment in the assignment's class is required (403 otherwise); reuses
  `ResolvesLearner` + the same media-upload pattern as
  `AssignmentSubmissionController`.
- [x] ✅ `schoolApi` (`classAssignments`, `classAssignmentDetail`,
  `createClassAssignment`, `gradeSubmission`) + hooks in
  `web/src/lib/school/queries.ts` (`useClassAssignments`,
  `useClassAssignmentDetail`, `useCreateClassAssignment`,
  `useGradeSubmission`) — extends the existing `schoolKeys` pattern.
- [x] ✅ **Assignments page** (`web/src/pages/AssignmentsPage.tsx`,
  `/assignments`) — class tabs (when a teacher has >1 class) → assignment
  card grid (title, coin badge, due date, `submitted/total` + `graded`
  counts) → **New assignment** modal → assignment detail modal with a full
  roster (every enrolled learner, submission status) and an inline
  **Grade** form (score, feedback, Mark passed/not passed) per submitted
  row. Added to `REAL_PAGES`.
- [x] ✅ **Product decision made:** pass/fail (boolean `passed`) is the
  release gate, with an optional 0–100 `score` for informational detail —
  mirrors the existing approve/reject decision model used for chores and
  lesson-assignment reviews, rather than introducing a new numeric-threshold
  concept.
- [x] ✅ **Tests**: `tests/Feature/ClassAssignmentTest.php` (2 cases — full
  create→submit→grade-pass→coins-released→re-grade-422 flow, and
  non-owning-teacher 403 on create). Full backend suite green (180 tests).
  Verified live end-to-end via browser preview as the seeded `teacher1` demo
  account: create → submission via direct API call (per this repo's
  documented React-controlled-input caveat) → grade → wallet credited (50
  coins, confirmed in DB) → audit log row confirmed.

---

## 2. Class roster & analytics — polish, not new `[MVP]`

Already ✅ shipped and "verified live" per the master TODO. Only polish
items surfaced by this audit:

- [ ] Confirm `analytics.class.view` gate (see §0) doesn't silently diverge
  from `schools.classes.view` as new class-scoped features (assignments)
  are added — decide once, apply consistently to every `/classes/{class}/*`
  route.
- [ ] Once §1 ships, surface **assignment completion rate** as a column in
  the existing class analytics tab (`ClassesPage.tsx` analytics table)
  alongside lessons/quiz/speaking — reuse the same table, don't build a
  parallel view.

---

## 3. Earnings — resolve the referral-vs-teaching-pay conflation `[BE]` `[BLOCK]`

`EarningsPage` at `/earnings` is fully wired but is **entirely the referral
commission system** (`Commission`/`Referral`/`Payout` models) — a teacher
sees "earnings" that are actually referral-code payouts, not compensation
for teaching classes. `commissions.view` is seeded for `teacher` but never
checked by any controller (orphaned).

- [ ] **Product decision (blocks this section):** *"Teacher commission
  payout — cash to bank vs platform coins"* is explicitly listed as
  unresolved in `Mahadum360_Implementation_TODO.md` line 60. Until this is
  resolved, don't build new schema — but do:
- [ ] **Relabel or split the UI honestly.** Either (a) confirm product intent
  is that teacher "earnings" *are* the referral program (a teacher earns by
  referring students/schools, same as a parent) and the current reuse is
  correct — in which case just rename any teacher-facing copy so it doesn't
  imply salary; or (b) once the decision above resolves, add a distinct
  "Teaching compensation" section/tab fed by a new backend concept, and stop
  routing `commissions.view` through the referral summary alone.
- [ ] `[BE]` If (b): wire `commissions.view` to something concrete —
  currently the permission exists with no controller check, which is a
  silent RBAC no-op.

---

## 4. Notifications `[MVP]` — ✅ shipped 2026-07-04 (paired with §1)

- [x] ✅ `ClassAssignmentGraded` notification (`app/Notifications/ClassAssignmentGraded.php`,
  registry entry in `config/email_templates.php`) — sent to the learner's
  account after grading, coins-released copy varies on pass/fail.
- [ ] Optional (deferred): notify the teacher when a new submission lands on
  one of their class assignments (digest or per-submission — default to
  per-submission to match the existing parent-review-queue pattern in
  `ReviewController::pending`). Not needed for MVP — the teacher's own
  Assignments page already shows live submitted/graded counts.

---

## Suggested order

1. ~~§0 portal hardening~~ — ✅ done.
2. ~~§1 class assignments~~ — ✅ done (paired with §4 notifications).
3. **§2 analytics polish** — small, next up now that §1's data exists.
4. **§3 earnings resolution** — needs the product decision first; don't
   build backend for it speculatively.

## Definition of done (per page/endpoint)
Guarded by `TeacherRoute` + ownership policy (teacher only sees/acts on
*their own* class) · typed API client method · filterable/paginated where
it's a list · loading/empty/error states · mutating actions (create
assignment, grade submission) audited where they touch money (coin reward)
and query-invalidated · wallet credit wrapped in `DB::transaction` like the
existing chore/assignment-review pattern · Vitest coverage on the guard and
each action · removed from the `ComingSoon` fallback and confirmed present
in `REAL_PAGES`.
