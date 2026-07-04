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

## Status snapshot (2026-07-04, updated after §0–§3 all shipped — plan complete)

**Landed (✅):**

| Page | Route | Component | Backend |
|---|---|---|---|
| Classes | `/classes` | `ClassesPage` | `GET /classes`, `GET /classes/{id}`, `GET /classes/{id}/analytics` ✅ (view-only: teacher lacks `schools.classes.manage`, correctly so — class creation is `school_admin`'s job) |
| Assignments | `/assignments` | `AssignmentsPage` | `GET/POST /classes/{class}/assignments`, `GET /classes/{class}/assignments/{assignment}`, `POST /class-assignments/{assignment}/submissions`, `POST …/submissions/{submission}/grade` ✅ — teacher-owned, tenant-scoped, coin release on pass. *(§1 shipped 2026-07-04; verified live incl. cross-teacher 403 and full create→submit→grade→wallet-credit flow.)* |
| Earnings | `/earnings` | `EarningsPage` | Two distinct sections: **referral earnings** (`GET /payouts`, `POST /payouts/request`, unchanged) and **teaching compensation** (`GET /teacher-compensation/summary`, `POST /teacher-compensation/payouts/request`, new, §3) — draw down separate balances via `payouts.source`. |

**Orphaned permissions — all resolved:**
- `schools.assignments.create` / `.review` — wired to the `ClassAssignmentController` routes (§1). ✅
- `learning.submissions.review` — still unused (grading is gated via `schools.assignments.review` + explicit ownership check instead, matching the `schools.*` permission naming); harmless duplicate grant, not blocking.
- `commissions.view` — wired to `GET /teacher-compensation/summary` (§3). ✅

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
- [x] ✅ **`analytics.class.view` vs `schools.classes.view` gating — resolved.**
  They were exact duplicates (identical 3-role grant, neither ever checked by
  any route). Decision: dropped `analytics.class.view` entirely (removed
  from `RolesAndPermissionsSeeder` + the `Roles_Permissions.md` matrix) and
  wired `schools.analytics.view` explicitly onto
  `GET /classes/{class}/analytics` (`->middleware('can:schools.analytics.view')`,
  alongside the existing `SchoolClassPolicy::view` ownership check) — one
  enforced permission instead of two unenforced ones.
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

## 2. Class roster & analytics — polish, not new `[MVP]` — ✅ shipped 2026-07-04

- [x] ✅ `analytics.class.view` / `schools.classes.view` gating resolved —
  see §0.
- [x] ✅ **Assignment completion surfaced in class analytics.**
  `SchoolClassController::analytics` now aggregates
  `ClassAssignmentSubmission` per learner (scoped to the current class via
  `whereHas('classAssignment', ...)`) and returns `assignments_submitted` /
  `assignments_passed` alongside the existing lesson/quiz/speaking stats.
  `ClassesPage.tsx`'s analytics tab gained an **Assignments** column
  (`passed/submitted`, or `—` when nothing's been submitted yet) — same
  table, no parallel view. Verified live: a graded pass shows `1/1`.

---

## 3. Earnings — resolve the referral-vs-teaching-pay conflation `[BE]` — ✅ shipped 2026-07-04

**Product decisions (resolved 2026-07-04):** teacher "earnings" needed a
distinct real compensation stream, paid to bank — not a relabel of the
referral program. Trigger: monthly accrual of `rate × currently-enrolled
students whose school has an active/paid seat allocation` (school students
are billed via seats, not personal subscriptions — the earlier "family has
an active subscription" framing didn't match that billing model, so this
was corrected to seat activity before building).

- [x] ✅ `[BE]` **`TeacherCompensationEntry`** ledger (tenant-scoped,
  `teacher_user_id` + `organization_id` + `period` unique) — one row per
  teacher per org per month, `paying_student_count × rate_minor =
  amount_minor`. Migration: `2026_07_04_150000_create_teacher_compensation_entries_table.php`.
- [x] ✅ `[BE]` **`compensation:accrue-teachers`** command, scheduled
  `monthlyOn(1, '03:00')` for the month just ended — per teacher, per org
  they teach in: counts distinct enrolled students across their classes in
  that org, but only if the org currently holds a non-expired
  `SeatAllocation` with `total_purchased > 0` (mirrors how school billing
  actually works: seats are an org-level pool, not per-student, so seat
  *activity* — not a specific seat-to-student link the schema doesn't have
  — is the "paying" signal). Idempotent upsert per (teacher, org, period).
- [x] ✅ `[BE]` **Settings**: `teacher_compensation.rate_per_student_minor`
  (admin-editable, `/admin/settings`, 0 disables accrual) — added to
  `config/settings.php`; the existing generic `SettingsController`/
  `SettingsPage` picked it up with zero extra frontend code.
- [x] ✅ `[BE]` **Payout draws down its own pool.** Added `source` to
  `payouts` (default `'referral'`, backward-compatible). New
  `TeacherCompensationController::requestPayout` — cash-to-bank only
  (`method` hardcoded `'bank'`, no coins option), validates the requested
  amount against `sum(accrued) − sum(existing 'teaching'-source payouts)`
  (422 `insufficient_balance` if exceeded — stricter than the referral
  flow's floor/cap-only check, since this is new code and correctness was
  cheap to get right), reuses the existing `referral.payout_*` floor/cap
  settings rather than adding a parallel admin surface. Guard:
  `commissions.view` for the summary (this **resolves that permission's
  orphaned status flagged in the original audit**), `payouts.request` for
  the payout itself (both already held by `teacher`).
- [x] ✅ `[BE]` `GET /teacher-compensation/summary` (available balance,
  accrued total, monthly breakdown) + `POST
  /teacher-compensation/payouts/request` (idempotency-guarded, like every
  other money POST). Admin's existing payout list/approval queue
  (`PayoutController::index`/`adminIndex`) now also returns `source` so a
  `teaching` payout is indistinguishable from `referral` only by that field.
- [x] ✅ **Frontend**: `EarningsPage` now has two clearly separate sections —
  "Referral earnings" (unchanged) and "Teaching compensation" (new: balance
  cards, monthly accrual table, its own payout list filtered by `source`,
  a bank-only `RequestTeachingPayoutModal`). Admin `PayoutsPage` gained a
  **Source** column badge (Referral/Teaching).
- [x] ✅ **Tests**: `tests/Feature/TeacherCompensationTest.php` (5 cases —
  accrual credits actively-seated classes, skips orgs without active seats,
  no-op at a zero rate, summary + successful payout request, and a request
  rejected for exceeding the available balance). Full backend suite green
  (185 tests). Verified live via browser preview: seeded a class + 4
  enrolled students + an active seat allocation, ran the accrual command,
  confirmed `/earnings` rendered "Available ₦800.00" / "Accrued to date
  ₦800.00" / "2026-06 · 4 students → ₦800.00", and confirmed the payout
  modal's floor validation surfaced correctly.

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
3. ~~§2 analytics polish~~ — ✅ done.
4. ~~§3 earnings resolution~~ — ✅ done.

**The Teacher Portal plan is complete.** Every section is shipped; nothing
is blocked.

## Definition of done (per page/endpoint)
Guarded by `TeacherRoute` + ownership policy (teacher only sees/acts on
*their own* class) · typed API client method · filterable/paginated where
it's a list · loading/empty/error states · mutating actions (create
assignment, grade submission) audited where they touch money (coin reward)
and query-invalidated · wallet credit wrapped in `DB::transaction` like the
existing chore/assignment-review pattern · Vitest coverage on the guard and
each action · removed from the `ComingSoon` fallback and confirmed present
in `REAL_PAGES`.
