> September 7 implementation update: the product owner confirmed the feedback learning restrictions in this task. See [current access decision](Mahadum360_Learning_Access_Decision.md). Server access gates, four-answer heart cadence, twelve-hour lock, one-XP quiz scoring, resume totals, content-completion badges, practice-video links, and pricing/terms alignment are implemented. Existing referral/profile/import/export fixes remain included. Production cleanup and FluentCRM/SES configuration await the target environment and client configuration; the new `feedback:cleanup-inventory` command is read-only. Historical statuses below are retained for context and are superseded by this update.

> Deployment update (September 7): application commit `2338b90` is live at https://mahadum360.com. All three migrations completed; public pages, entry-point assets, database columns, phone uniqueness, and runtime services passed deployment checks. All 52 user accounts remain. Account cleanup and FluentCRM/SES configuration remain pending; deployment does not establish live email or payment-provider verification.

> Referral tracking follow-up (September 7, local implementation): `/referrals` now includes pending sign-ups, both contact fields, explicit activation status/date, and pagination. Dashboard summaries and admin profiles include activity across all personally owned codes. Both views load recent-login data for active/inactive status and fall back to profile contacts when invitation values are missing. Regression checks cover ownership isolation, legacy codes, pending records, contacts, recent-login status, and pagination. This follow-up has not yet been deployed.

# MAHADUM.360 — Pending Feedback (September 4) — Line-by-line Review

**Reviewed:** 2026-09-06
**Source doc:** `Mahadum Feedback Pending Items september 4.docx`
**Implemented in this pass (2026-09-06), all test suites green:**
- §4c — streak wording: `0 Day Streak` (singular) — `web/src/lib/gamification/format.ts` + test.
- §6.8 — leaderboard now shows each learner's **cumulative lifetime XP total** alongside the
  weekly competitive figure; "Level N · Name" label already in place — `web/src/pages/LeaderboardPage.tsx`.
- §8 (sign-up) — **phone uniqueness**: new `App\Support\Phone::normalize()` canonicalises every
  number to `+<country><subscriber>`; `RegisterRequest` + `GoogleAuthRequest` normalise and enforce
  `unique:users,phone`; migration `2026_09_06_000001` normalises existing rows, nulls younger
  duplicates (logged), and adds the DB unique index; **country-code dropdown** added to the
  sign-up phone field (`PhoneInput` + `RegisterPage`). Test: `AuthTest::test_register_normalizes_phone_and_rejects_a_duplicate_number`.

**Implemented in the second pass (2026-09-06) — Tranche C, all suites green:**
- §2 — admin **referral panel** on the user-detail page: `GET /api/v1/admin/users/{user}/referrals`
  (who activated through this user + activation date/status/channel/contact/commission, and the
  referral they themselves came from) + a "Referral activity" card in `UserDetailPage.tsx`.
  Tests: `AdminUserReferralsTest`.
- §3 — course preview TOC ("Course contents") now shows unit count, per-unit lesson count +
  total minutes, Draft tags, and each lesson row jumps into the unit preview —
  `CoursePreviewPage.tsx`. (A generated TOC already existed; this fleshes it out. If you want a
  TOC on a *different* surface — learner catalogue, or the builder — say which.)
- §9 — **Users & subscriptions CSV export**: `GET /api/v1/admin/reports/users/export`
  (name, email, phone, account status, plan, subscription status, start, expiry, payment status;
  UTF-8 BOM; audited) + "Export users report" button on `ReportsPage.tsx`.
  Tests: `UsersExportReportTest`.

**Implemented in the third pass (2026-09-06) — Tranche C leftovers, all suites green:**
- §4a — **Learning Tiers** section on `/components` — six rows with exact name, emoji icon
  (⭐🥉🥈🥇💎👑) and XP threshold. Mirrors `LearningLevelService::LEVELS`.
- §4b — **tier badges + award + notification + detail view**:
  - six `tier_0`…`tier_5` badge rows (`BadgeSeeder` + migration `2026_09_06_000002`);
  - `BadgeService::evaluate()` now awards a tier badge on reaching each level and sends a
    `LearningLevelUp` notification ("Congratulations! … completed Level N and earned the … badge")
    to the learner's account, or the family owner for a login-less child;
  - `/learners/{id}/badges` returns `description`, `icon`, `level`, `earned_at`; the
    `/achievements` badge grid is now clickable → a detail modal (icon, why, earned date).
  - Tests: `GamificationTest` (tier award + notification + endpoint detail fields).
- §5 — **already satisfied by BF-08**: the speaking slide already shows an "invite a trusted
  adult/teacher" flow with a 48-hour private link. The only remaining sub-point ("direct link to
  the video" for the adult recipient) is low value — the recipient has no learner context and the
  invitation already names the lesson + practice phrase. Deferred pending confirmation it's wanted.
- §8 — **game upload template + AI generation**:
  - `AddGameModal` now has a "Download CSV template" (`Game Type`, `Pairs` columns) + "Import
    completed CSV" with preview, via `parseGameCsv()`;
  - a "✨ Generate with AI" copy-prompt disclosure added to **both** the game builder and the
    flashcard builder (previously quiz-only).
  - Test: `betaFeedback.test.ts` (`parseGameCsv`).

Everything else below is unchanged from the review and still open (decision-blocked, OPS, or
not yet built).

**Prior review:** `docs/Mahadum360_Beta_Feedback_TODO.md` (Sept 3, against the Sept 1 feedback). Several
Sept 4 items are the client's response to that review and, in three places, explicitly ask for
behaviour the Sept 3 review flagged as conflicting with the locked BRD. Those are called out below
and **must not be built as written** until the product owner revises the BRD + master TODO in
writing.

## Status legend

- `[x]` Already implemented in current code — keep as a production regression check.
- `[~]` Partially implemented — the remaining work is listed.
- `[ ]` Confirmed gap — needs build.
- `[!]` Conflicts with a locked product rule (CLAUDE.md "Product rules that override convenience").
  Needs a written product decision before any code.
- `[OPS]` Production-data / infra task — needs a backup, an ID-level inventory, and an audit trail.

---

## 1. Referral activation logic — `/referrals`

**Verdict: [x] implemented, with production regressions still owed (matches BF-01).**

| Requirement | State | Evidence |
|---|---|---|
| Referral code activates only after referred user has a **paid subscription** | `[x]` | `ReferralService::maybeActivate()` — `referral.activation_requires_paid_subscription` (default true), `activePaidSubscription()` requires `plan.price_minor > 0` |
| …**and 1 lesson + 1 quiz** completed | `[x]` | same method — `referral.activation_min_lessons`/`_min_quizzes` (default 1/1), counted across the whole household |
| After activation, a referred purchase pays the referrer **5% for the first month** | `[x]` | `recordReferredPurchase()` — `referral.commission_bps` default `500` (5%), gated by `referral.earning_window_days` (default 30); still subject to 14-day escrow + chargeback clawback (`reverseForSource()`) |
| Cannot refer an **already-active account**; show "account already exists" prompt | `[x]` | `invite()` throws `ReferralAccountExistsException` → `ReferralController::invite()` returns `error.code = account_exists`, 422. Frontend surfaces it in `ReferralsPage.tsx` |
| Referrer dashboard lists everyone who activated, **searchable by email or phone** | `[x]` | `ReferralController::activations()` — `search` matches `contact_value` + referred user's `email`/`phone`; `ReferralsPage.tsx` has the search box (line ~224) |

**Still owed (from BF-01, unchanged):**

- [ ] Production regression: open a fresh `/r/{code}` link in a private window; confirm the code
  survives both email registration and Google registration.
- [ ] Production regression: activation in both event orders (pay→learn, learn→pay).
- [ ] Production regression: one qualifying payment → exactly one 5% commission with the expected
  escrow-release date.
- [ ] The doc says "could not be fully tested because a paid subscription is not available for
  testing." **Blocker is environmental, not code** — stand up a test plan + sandbox gateway (or a
  seeded active paid `Subscription`) so QA can run the end-to-end path.

---

## 2. Referral activity on **each user profile** (admin) — `/referrals`, `/admin/users/{id}`

**Verdict: [ ] gap.**

The *referrer-facing* dashboard is done (item 1). What is missing is a **referral panel on the
admin user-detail page**: `web/src/pages/UserDetailPage.tsx` has no referral section, and there is
no admin endpoint returning a user's referral activity.

- [ ] Backend: `GET /api/v1/admin/users/{user}/referrals` (guard `users.view` / `referrals.view`),
  returning, for referrals **this user made** and the referral **this user came from**: activation
  date, activation status (active/inactive via `ReferralService::isReferredUserActive`), invited
  channel, email, phone, and commission state.
- [ ] Frontend: a "Referral activity" card in `UserDetailPage.tsx` — `adminApi` method +
  `lib/admin/queries.ts` hook + `lib/api/types.ts` type (admin-portal convention).
- [ ] Tests: user with outbound activations, user who was referred, user with neither.

---

## 3. Course table of contents — `/courses/{id}/lessons/{id}`

**Verdict: [ ] gap (matches BF-03.3).**

`web/src/pages/content/CoursePreviewPage.tsx` renders title + units only; there is no TOC.

- [ ] Generate a TOC from the existing level → lesson hierarchy: ordered levels, ordered lessons,
  per-lesson activity/quiz counts, estimated duration where available.
- [ ] Make entries navigate to the lesson preview where permissions allow.
- [?] Product: "table of contents" = the generated hierarchy above, or a separately authored
  rich-text TOC? **Recommend generated** (no new content model, cannot drift from real structure).
- [ ] Tests: empty course, multi-level course, ordering, draft visibility (author preview only).

---

## 3 (second "3."). Pricing page copy — `/pricing`

**Verdict: [!] the requested wording implies a paywall that the BRD forbids — see item 6.9 / §"BRD conflicts".**

- Current Free-plan copy lives in `web/src/lib/billing/planFeatures.ts` →
  `FREE_PLAN_FEATURES[0] = 'Every lesson and quiz'`; `PricingPage.tsx:75` also says
  "Every lesson is free."
- The doc asks to change it to **"Free lessons and quiz"**, i.e. only *some* lessons are free.
  That is only correct if Lesson 1+ is actually paywalled (the Free-tier item under §6), which
  **contradicts the locked rule** *"never gate/lock learning behind hearts or paywall (Free = full
  learning + ads)"*.

- [!] Do **not** change this string until the Free-tier lesson-lock decision is made and the BRD +
  `docs/Mahadum360_Implementation_TODO.md` are updated together.
- [x] If the BRD is **not** changed: the current wording is correct; no action. (Optionally
  normalise "quiz" → "quizzes" for grammar.)
- [ ] If the BRD **is** changed: this becomes a coordinated change across pricing copy, billing
  page, entitlements, catalogue lock state, and public terms — not a one-line edit.
- [ ] Independent of the above: add a parity test so `/pricing` and `/billing` present the same
  benefit set (BF-05.2 still open).

---

## 4. Badges, learning tiers, streak wording

### 4a. Learning tiers in the design system — `/components`

**Verdict: [~] tier engine done; design-system documentation of the full ladder is missing.**

- `[x]` Tier thresholds + names already implemented and approved:
  `app/Services/Gamification/LearningLevelService::LEVELS` —
  0 Star Starter (0 XP) · 1 Bronze (100) · 2 Silver (500) · 3 Gold (1,500) · 4 Platinum (4,000) ·
  5 Culture Master (10,000). Returned by learner + leaderboard resources.
- `[ ]` `web/src/pages/ComponentsPage.tsx` only shows ad-hoc badges ("Culture Master",
  "Family Hero", …). Add a **Learning Tiers** section rendering all six rows with the exact
  name, icon (⭐ / 🥉 / 🥈 / 🥇 / 💎 / 👑), and "Level N Completed" achievement criterion from
  the doc's table.
- `[?]` The doc's icons are emoji; confirm whether design wants emoji or the existing
  `CulturalBadgeCard` art treatment. The "Gilded Adire" design language memo says never a
  Duolingo-clone — pick icon art deliberately.
- `[ ]` Reconcile naming: the doc calls these "Learning Tiers / badges"; code calls them
  "learning levels" and has a *separate* `badges` table (`first_lesson`, `streak_7`,
  `sharp_shooter`, `family_hero`). Decide whether each tier also mints a row in `badges` /
  `learner_badges` (needed for 4b's "click a badge" detail view) or stays a computed level.
  **Recommend** adding six tier badges so they have earned-dates and detail copy.

### 4b. Badge award + notification logic — `/achievements`

**Verdict: [ ] gap.**

- `BadgeService::evaluate()` awards badges idempotently and returns newly-earned codes in the
  lesson-completion response, but:
  - `[ ]` there is **no tier badge** in `conditions()` — completing Level 0/1/… does not award a
    "Star Starter" / "Bronze" badge;
  - `[ ]` there is **no notification** on level-up or badge-earn. Need a `Notification`
    ("Congratulations! You have completed Level 0 and earned the Star Starter badge.") fired from
    `LearningLevelService::forLearner()` when `current_level` increases, and from `BadgeService`
    when a badge is granted.
- `[ ]` Badge detail view: clicking a badge on `/achievements` must show name, level completed,
  date earned, and a one-line "why". Needs the badge row to carry `description` + `earned_at`
  (present on `learner_badges`) and a detail component/route.
- `[?]` The doc also lists "Streak: days of uninterrupted learning" and "Family Hero: highest
  score for the day" as badge-detail copy — Family Hero already exists
  (`AwardFamilyHeroes` command + `FamilyHeroAward`); just needs the detail string.

### 4c. Streak wording — "0 Day Streaks" → "0 Day Streak"

**Verdict: [~] one-line fix.**

`web/src/lib/gamification/format.ts`:
```ts
return `${count.toLocaleString()} Day Streak${count === 1 ? '' : 's'}`
```
Zero currently renders "0 Day Streak**s**". Change the plural test to `count === 1 || count === 0`
(or `Math.abs(count) === 1`). Update `betaFeedback.test.ts` / add a case for `0`.
Note this refines the BF-07.1 decision ("{n} Day Streaks" for plural) — 0 is now singular.

---

## 5. Tone practice — "Invite to Practice" — `/learn`

**Verdict: [~] invitation backend exists (BF-08); the "no partner available" entry point + video deep-link are the gap.**

- `[x]` `TonePracticeInvitationController` + `TonePracticeInvitation` model + queued
  `TonePracticeInvitationNotification` + `/tone-practice/invitations` route + hashed 48h token,
  guardian-controlled, no child PII in payload.
- `[ ]` Player affordance: when a learner finishes a language video and there is **no one to
  practise the tone with**, show an **"Invite to practice"** action (currently the invite flow
  isn't surfaced from that dead-end state). Wire it in `web/src/components/learning/player/`.
- `[ ]` Invitation payload/email must include a **direct link to the specific language video /
  speaking activity** (the model already ties to one published speaking activity — expose the
  deep link in `TonePracticeInvitationPage.tsx`).
- `[ ]` Invitation copy: confirm it matches the doc's "clear invitation message" and stays
  pronoun-safe.

---

## 6. Quiz & lesson experience — `/learn/lessons/*`

### 6.1 XP on repeated lesson completion

**Verdict: [!] direct reversal of BF-10.1 — needs product sign-off + anti-farming design.**

- Current: `LessonCompletionController::complete()` awards `xp_total` **once** (`! $alreadyDone`),
  0 on every replay. This was the *fix* delivered for the Sept 1 feedback (BF-10.1).
- The Sept 4 doc now asks for the **opposite**: "award the configured XP each time the learner
  successfully completes the lesson, including repeated attempts."
- [!] Awarding full XP on every replay lets a learner farm the leaderboard by re-completing one
  lesson repeatedly. Do not ship as written.
- [?] Product decision needed. If replays must grant XP, **recommend** a bounded rule: award once
  per lesson per rolling 24h (or a small "review XP" < first-completion XP, capped daily), and
  keep the competitive leaderboard on first-completion XP only.

### 6.2 Quiz XP — 1 XP per correct answer, max = question count

**Verdict: [~] engine is per-question already; the reported "9 correct = 5 XP" needs live repro, likely one of the causes below.**

- `AnswerController::store()` awards `(int) $question->points` per correct answer, **once per
  learner per question** (`$alreadyEarned` guard), and only when `! practice_mode`. New
  authored/imported questions default to `points = 1`.
- Candidate causes of the wrong total the tester saw — verify with a **fresh** learner:
  1. `[ ]` Legacy `questions.points` rows ≠ 1 (BF-10.4 flagged this; the Aug migration didn't
     rewrite `questions.points`). Audit production for non-1 values; add a migration or clamp on
     import/author if product wants exactly 1 universally.
  2. `[ ]` The learner was in **practice mode** (hearts exhausted, §6.4) → correct answers award
     0 XP silently. This is expected under current rules but is confusing; the result screen must
     say so.
  3. `[ ]` Re-attempt of an already-answered question → 0 XP by the anti-farm guard (see §6.9 /
     leaderboard).
  4. `[ ]` The number the tester read ("5 XP") may be the **quiz-attempt score ratio** or league
     weekly XP, not per-question XP — the result screen is conflating them (see §6.9).
- [ ] Acceptance test: 10 first-time correct answers on a fresh learner → exactly 10 XP; replay →
  0 additional.

### 6.3 Free-plan heart deduction — "every 4 questions = 1 heart"

**Verdict: [ ] gap — current cadence is 1 heart per wrong answer; the doc now defines the rule so it can be built.**

- Current: `AnswerController` — `heartsLost = (!unlimited && !correct && quiz->hearts_enabled) ? 1 : 0`,
  i.e. one heart **per incorrect answer**, all plans with `hearts_enabled`.
- Doc's rule: "a failed quiz should deduct hearts. Every 4 questions = 1 heart" and "For the Free
  Plan".
- [?] Still ambiguous: 1 heart per **4 incorrect answers**, per **4 answered questions**, or per
  **failed quiz** of ≤4 questions? Confirm before coding. **Recommend**: 1 heart per 4 incorrect
  answers within an attempt, floored at 0.
- [ ] Apply only to plans **without** `unlimited_hearts` (Free). `EntitlementResolver::forLearner`
  already yields `unlimited_hearts`.
- [ ] Make the running "incorrect since last heart" counter transaction-safe (extend
  `PracticeModeService`/`Heart`).
- [ ] Return enough state for an immediate UI update; tests for the cadence boundary, retries,
  duplicate requests, zero-floor.

### 6.4 Lock the quiz/lesson for 12 hours when hearts hit zero

**Verdict: [!] conflicts with the locked BRD rule. Currently implemented as "practice mode", by design.**

- Locked rule: *"never gate/lock learning behind hearts or paywall (Free = full learning + ads)."*
- Current behaviour (`PracticeModeService`, commit `5439b24` "approved learning product rules"):
  at 0 hearts, lessons/quizzes stay **playable**, but new XP + competitive score pause for 12h
  (`PAUSE_HOURS = 12`), with rewarded-ad / coin refill and paid unlimited hearts as the way out.
- [!] A hard 12-hour **lock** on learning is exactly what the BRD forbids and what the Sept 3
  review (BF-10.8) said to keep blocked. Do **not** implement it unless the product owner formally
  changes the BRD, `Mahadum360_Implementation_TODO.md`, and the public terms — together.
- [x] If the rule stands: no code change; the 12h competitive pause already exists.

### 6.5 Message when hearts are exhausted

**Verdict: [~] depends on 6.4; copy needs updating either way.**

- [ ] Show a clear message at 0 hearts. The doc's wording — *"See you in 12 hours, or upgrade for
  unlimited hearts."* — implies a lock; if the BRD stands, use non-blocking wording that still
  names the 12h competitive pause and the upgrade/refill options (BF-10.8 approved supportive
  copy that says *learning remains available*).
- [ ] Surface `competitive_paused_until` (already in the answer/heart API responses) in the
  player + a dismissible banner.

### 6.6 Paid / upgraded learners = unlimited hearts

**Verdict: [x] implemented (BF-10.7 closed).**

- `AnswerController` reads `EntitlementResolver::forLearner($learner)['unlimited_hearts']`; when
  true, no deduction, `hearts_remaining = null`, `unlimited_hearts = true`, no practice-mode
  pause.
- [ ] Still owed: tests for Individual, Family, and school/org entitlement inheritance, plus
  cancelled/grace subscriptions falling back to Free.

### 6.7 Quiz score / completion display after submit

**Verdict: [~] per-quiz result screen was built (BF-10.3); the "score is wrong" report needs live repro.**

- `syncQuizProgress()` writes `ComponentProgress.data = {answered, total, correct}` and, on
  completion, `QuizAttempt.score = correct/total` + `passed`.
- [ ] Verify the result screen shows **correct / total**, percentage, XP earned *for that quiz
  attempt*, and pass/fail — and that "number answered" and "score" agree with the learner's
  actual responses (the doc says they don't). Likely a frontend mapping bug in
  `web/src/components/learning/player/` reading the wrong field (ratio vs count vs XP).
- [ ] Confirm resume-after-refresh starts at the right question / summary (BF-10.3 still open).

### 6.8 Leaderboard — one cumulative XP total per learner — `/leaderboard`

**Verdict: [~] lifetime XP is computed but the leaderboard ranks/show weekly XP, not the cumulative total.**

- `LeaderboardController` ranks by `weekly_xp` (per-league, resets weekly). It *does* include
  `learning_level.lifetime_xp` (sum of all `xp_entries`) in the payload, but the board doesn't
  rank or prominently show it.
- The doc's example — Kamsi 40 + 35 + 37 → **112 total** — wants a single accumulated lifetime
  figure shown per learner.
- [ ] Add the learner's **cumulative lifetime XP** to the leaderboard row/UI
  (`web/src/pages/LeaderboardPage.tsx`), sourced from `learning_level.lifetime_xp`.
- [?] Decide whether ranking stays weekly (recommended, keeps it a live competition) with lifetime
  total shown alongside, or moves to all-time. Do not remove the per-question anti-farm guard to
  make repeated attempts add up (BF-12.2). The "112" only materialises if those attempts were
  first-time-correct answers that legitimately created ledger entries.
- [ ] Relabel "Tier" → "Level" in the leaderboard UI (BF-12.2 still open) now that tier semantics
  are approved (§4a).

### 6.9 Free tier — only Lesson 0 accessible, Lesson 1+ locked until paid

**Verdict: [!] conflicts with the locked BRD rule (same as BF-13.1). Do not build as written.**

- Locked rule: *"Free = full learning + ads"*; the Sept 3 review explicitly blocked this.
- Current: `EntitlementResolver` gates conveniences (ads, unlimited hearts, family dashboard,
  analytics) — **not** lesson access. No `Lesson`/`Level` entitlement metadata exists.
- [!] Building a Lesson-1 paywall is a cross-cutting change (catalogue lock state, play-API
  authorization, deep-link enforcement, existing enrolments, schools + telco, refunds,
  analytics, accessibility, pricing copy §3, public terms). It needs a formal BRD revision +
  a dedicated design doc **before** any code.
- [x] If the BRD stands: no change; keep every published lesson open to Free users.

---

## 7. Dummy-data & user cleanup

**Verdict: [OPS] production-data task — do not run from a feature branch. Needs backup + ID-level inventory + sign-off.**

Retain **only** these accounts (correct as noted):

| Name | Email | Phone | Correction |
|---|---|---|---|
| Ifeoma Okafor-Obi | mahadum360@gmail.com | 08022224495 | rename the existing **Emeka Nwosu** profile → Ifeoma Okafor-Obi; set this email + phone |
| Val Amadi | vcamadi@yahoo.co.uk | — | — |
| Adebare Showemimo | adebareshowemimo2023@gmail.com | — | — |
| Kamsy Obi | kamsiobi76@gmail.com | +234 817 866 9330 | rename existing **Lucy Okafor** → Kamsy Obi; update phone |

⚠️ The retain list has a **conflict**: both "Ifeoma Okafor-Obi" and "Emeka Nwosu" rows cite
`mahadum360@gmail.com`, and one line also gives Ifeoma's email as `mahadum360@gmail.com` while the
first bullet implies she already exists. Emails must be unique (item 8b). **Get the client to
disambiguate before touching data.**

- [OPS] Take + verify a restorable production DB backup.
- [OPS] Produce a dry-run inventory keyed by user ID / email / ownership / seeder source — with
  every dependent row (learner profiles, lesson/quiz progress, XP ledger, wallets, referrals,
  commissions, subscriptions, invoices, media). Names alone are not a safe delete key.
- [OPS] Client signs off the exact ID list.
- [OPS] Delete in a transaction (or approved anonymisation), respecting financial/audit retention.
- [OPS] Apply the two profile renames + phone/email updates via the admin portal (audited).
- [OPS] Reconcile leaderboard, family counts, seats, referrals, invoices, analytics afterwards.
- [ ] Add a guard test/config assertion that `DevSeeder`/`DemoSeeder` cannot run in production
  (BF-12.3 still open).

---

## 8. Game / Flashcard upload template + AI generation — `/courses/{id}/lessons/{id}`

**Verdict: [~] flashcards already have a CSV template; games have none; AI generation is quiz-only.**

- `[x]` Flashcards: `LessonBuilderPage.tsx` → `downloadFlashcardTemplate()` /
  `parseFlashcardCsv()` — CSV with `Front (Word)` / `Back (Meaning)`, import + preview.
- `[x]` AI question generation: `LessonBuilderPage.tsx:1366` — "✨ Generate questions with AI
  (ChatGPT / Claude)" **only under Add Quiz**.
- `[ ]` **Games**: `AddGameModal` is manual (pairs typed in). Add a downloadable template with
  the doc's columns — **`Pairs`** and **`Game Type`** — plus an importer/preview mirroring the
  flashcard flow.
- `[ ]` Extend the **"Generate with AI"** helper to **Games** and **Flashcards** (same
  copy-paste-prompt pattern already used for quiz).
- [ ] Tests: game CSV parse (valid / missing column / blank rows / duplicate pairs / accented
  text), flashcard AI helper, game AI helper.

---

## 8 (second "8."). Sign-up page — country code + phone/email uniqueness

**Verdict: [ ] gap.**

- `app/Http/Requests/Auth/RegisterRequest.php:21` — `phone` is only `['required','string','max:20']`;
  **no `unique:users,phone`**. Email uniqueness: confirm the rule is present (it should be) — the
  doc says duplicates are currently possible, so verify.
- `web/src/pages/RegisterPage.tsx` — plain text phone field, **no country / country-code
  dropdown**.
- [ ] Frontend: add a country / dialing-code selector next to the phone field; store E.164.
  Default to Nigeria (`+234`) given the audience but allow diaspora codes.
- [ ] Backend: normalise phone to E.164 on registration; add `unique:users,phone` (and confirm
  `unique:users,email`). Apply the same to the Google sign-up path and any admin "create user"
  form (BF-02).
- [ ] Migration: production may already hold duplicate/again non-E.164 phones — dedupe/normalise
  before adding the unique index.
- [ ] Tests: duplicate email, duplicate phone (various formats of the same number), missing
  country code, valid diaspora number.

---

## 9. Users & Subscription report export — `/admin/reports`

**Verdict: [ ] gap.**

`app/Http/Controllers/Admin/ReportController.php` only produces **aggregate** JSON (income,
growth, subscriptions funnel, org activity, referrals, renewals). There is **no per-user export**
and no CSV/Excel output anywhere in the reports feature.

- [ ] Backend: `GET /api/v1/admin/reports/users/export` (guard `reports.view` / `users.view`),
  streaming CSV (and/or XLSX) with: name, email, phone, account status, subscription plan,
  subscription status, subscription start date, expiry date, payment status. Paginate/stream for
  large tenants; audit the export action.
- [ ] Frontend: an "Export users" button on `web/src/pages/ReportsPage.tsx` (or a new
  `UsersReportPage.tsx`) — note the app cannot trigger browser downloads for the user in some
  contexts; use a normal authenticated link/blob.
- [ ] Respect tenancy: super_admin gets all; a school admin export is scoped to their org.
- [ ] Tests: column set, scoping, large dataset, unauthorised caller, audit row.

---

## 10. Telco integration — default Individual account + Level 1 only

**Verdict: [~] plan restriction is enforced; "Individual account by default" needs confirming; "Level 1 only" is [!].**

- `[x]` `TelcoController::subscribe()` already hard-restricts telco enrolment to
  `audience === 'individual' && code === 'premium_individual'` and `interval === 'month'`,
  validated **before** consuming the OTP. Family/non-monthly are rejected.
- `[~]` "Default to an Individual account": telco enrolment attaches a `Subscription` to the
  existing `User` — it does **not** set an account/organization type. Confirm what "Individual
  account" means here: since the plan is already `premium_individual` and telco users are
  direct consumers (`organization_id = NULL`), this may already be satisfied. If a telco signup
  should also **provision a learner profile** automatically, that's a [ ] gap — verify.
- `[!]` "Telco subscription provides access to **Level 1 only** for each language" — this is a
  content paywall, the same BRD conflict as §6.9 / BF-15. Do **not** enforce a per-channel
  learning restriction until lesson/level entitlement metadata is designed and the BRD is
  revised. If approved, it must be enforced in catalogue + play API + deep links + offline
  cache, not just the UI.
- Note: `TelcoController` also currently sits behind `feature.telco_billing` (default **off**) and
  its docblock calls new airtime enrolment "deprecated platform-wide" — reconcile that with the
  client's intent to actively use telco.

---

## 11. Email marketing — FluentCRM + Amazon SES

**Verdict: [~] SES-over-SMTP works today; FluentCRM integration not started (matches BF-16). Blocked on client details.**

- `[x]` Admin SMTP config + test screen (`EmailConfigurationPage.tsx`,
  `app/Services/MailConfiguration.php`); contact lists, suppression-aware campaigns, branded
  templates, delivery logs, queue. Amazon SES can be used through SMTP now.
- [ ] SES production readiness: verified identity/domain, DKIM/SPF/DMARC, out-of-sandbox,
  region endpoint, approved From address, queue worker monitored, bounce/complaint feedback
  wired to suppression.
- [ ] FluentCRM: needs the client's FluentCRM URL, API/auth method, list/tag map, field mapping,
  and lawful-basis/consent rules **before** build. **Recommend** MAHADUM stays the system of
  record for identity/consent; one-way sync of eligible adult contacts + lifecycle events to
  FluentCRM; ingest unsubscribe/bounce back. Never sync child learner profiles.
- [ ] Verify password-reset and phone-update/verification emails actually deliver end-to-end once
  SES creds are in.
- The doc says "Once she provides details you can proceed" — **this item is waiting on the
  client.**

---

## Product decisions — RESOLVED 2026-09-06 (product owner, in session)

1. **Learning access / paywall — BRD IS BEING CHANGED.** The product owner has authorised
   building the paywalls: Free tier = **Lesson 0 only** per language, Lesson 1+ locked until paid;
   **12-hour hard lock** on the lesson/quiz when Free hearts hit zero; **Telco = Level 1 only**
   per language; pricing copy → **"Free lessons and quiz"**. → The BRD (`docs/` BRD source),
   `docs/Mahadum360_Implementation_TODO.md`, and the CLAUDE.md "Product rules" note MUST be
   rewritten to match **as part of this workstream** (they currently say the opposite).
2. **§6.1 repeat-lesson XP — KEEP ONE-TIME.** No change; first completion awards XP, replays 0.
   (Item §6.1 is now closed as "won't do".)
3. **§6.3 heart cadence — 1 heart per 4 *answered* questions** (regardless of correctness),
   Free plan only; paid = unlimited hearts, never deducted.
4. **§6.8 leaderboard — weekly rank, lifetime total shown alongside.** Implemented this pass.

### Remaining build tranche (with the decisions applied)

These are the still-open items, now unblocked. Recommended order:

**Tranche A — hearts + lock (do together; they share `PracticeModeService` + `GamificationTest`):**
- §6.3 heart cadence → 1 per 4 answered questions (`AnswerController` + rewrite the heart-loss
  assertions in `GamificationTest` to answer 4 distinct questions; `publishedLesson()` fixture
  may need a 4-question quiz).
- §6.4/§6.5 → replace "practice mode" with a real 12-hour lock for **Free** learners: at 0 hearts,
  `POST /components/{c}/answer` and `/lessons/{id}/complete` return `423`/`403` with
  `locked_until` + message *"See you in 12 hours, or upgrade for unlimited hearts."*; paid
  learners and rewarded-ad/coin refill bypass. Update `HeartController`, the player
  (`web/src/components/learning/player/`), `LessonPlayerPage`, and all related tests.

**Tranche B — Free-tier lesson entitlement (largest):**
- New metadata: a `free` flag (or `min_tier`) on `lessons` (or on `levels` for the telco
  "Level 1 only" case — needs both a lesson-index and level-index gate). Seed Lesson 0 / Level 0
  as free.
- Enforce server-side in: enrollment/path builder, `GET /lessons/{id}/play`,
  `LessonCompletionController`, `AnswerController`, catalogue/course-preview API, deep-link
  resolution. UI: lock badges in `CoursePreviewPage` + catalogue, upsell CTA.
- Telco: `TelcoController` / entitlement → Level 1 only for airtime subscribers.
- Pricing copy: `planFeatures.ts` `FREE_PLAN_FEATURES[0]` → "Free lessons and quiz";
  `PricingPage.tsx:75` "Every lesson is free." → align. Ship copy **with** the feature, not before.
- Tests: Free vs paid lesson access, deep link, existing enrolments, school seats, telco level
  gate, refund → re-lock.
- Docs: rewrite BRD + implementation TODO + CLAUDE.md product-rules note.

**Tranche C — independent features (any order):**
- ✅ §2 admin referral panel — DONE.
- ✅ §3 course TOC — DONE (generated hierarchy fleshed out; confirm surface if more wanted).
- ✅ §4a — Learning Tiers design-system section — DONE.
- ✅ §4b — tier badges + level-up notification + badge-detail view — DONE.
- ✅ §5 — invite-to-practice — already implemented (BF-08); video deep-link sub-point deferred.
- §6.7 — reproduce the "score is wrong" report with a fresh learner; fix the field mapping in the
  quiz result screen. **Needs a running app + data to reproduce — not doable from static review.**
- ✅ §8 — game template + AI helper for games/flashcards — DONE.
- ✅ §9 — Users & subscriptions CSV export — DONE.
- §11 — SES production hardening (identity/DKIM/SPF/DMARC, out-of-sandbox, bounce feedback);
  FluentCRM still blocked on the client's config details. **Ops task.**

**Tranche D — OPS (needs the product owner + a backup):**
- §7 dummy-data cleanup. ⚠️ The retain list has a **conflict** (two people share
  `mahadum360@gmail.com`; emails must be unique). Resolve before any deletion. Then: backup →
  ID-level dry-run inventory → sign-off → transactional delete → the two profile renames →
  reconcile.

## (Original) product decisions section — superseded by the resolutions above

1. **BRD conflict — learning access.** Items §3, §6.4, §6.5, §6.9, §10 all ask to gate learning
   behind hearts/paywall/channel. This contradicts the locked rule *"Free = full learning +
   ads; never gate learning behind hearts or paywall."* Either:
   (a) keep the rule → items §6.4/§6.9/§10-Level-1 are **won't-do**, §3 keeps current copy, §6.5
   gets non-blocking copy; or
   (b) formally revise the BRD + `Mahadum360_Implementation_TODO.md` + public terms, then a
   dedicated design doc for lesson/level entitlements precedes implementation.
2. **§6.1** — should repeated lesson completion re-award XP? If yes, agree the anti-farming cap.
3. **§6.3** — exact heart cadence: per 4 incorrect answers / per 4 answered / per failed quiz.
4. **§6.8** — leaderboard = weekly competition with lifetime total shown, or all-time ranking?
5. **§4a** — tier icons: emoji or bespoke art; and do tiers also mint badge rows.
6. **§7** — resolve the duplicate `mahadum360@gmail.com` / Emeka↔Ifeoma email conflict in the
   retain list before any production deletion.
7. **§10** — is "Telco account" a billing method (current) or a distinct provisioning path; and
   reconcile with the `feature.telco_billing` "deprecated" state.
8. **§11** — FluentCRM system-of-record boundary + the config details from the client.

## Buildable now without a product decision

- §2 admin referral panel · §3 generated course TOC · §4a tier design-system section ·
  §4b badge/level notifications + detail view · §4c "0 Day Streak" fix · §5 invite-to-practice
  entry point + video deep link · §6.6 entitlement tests · §6.7 quiz result-screen field-mapping
  fix · §6.8 show lifetime XP + "Level" relabel · §8 game template + AI helper for games/flashcards ·
  §8b country-code dropdown + phone/email uniqueness · §9 user/subscription CSV export ·
  §11 SES production hardening.
