# MAHADUM.360 — Implementation TODO

A phased, trackable build plan for the MVP (web-first), stitched from the BRD,
backend architecture, content model, DB layer, and UI designs already produced.

**Legend:** ✅ done · 🟡 in progress / partial · ⬜ not started
**Tags:** `[MVP]` ships for launch · `[POST]` deferred · `[BLOCK]` decision needed first

> **Status snapshot (2026-06-27).** The **backend API for milestones 1–9 is feature-complete, tested
> (57 feature tests / 223 assertions), and statically clean** (Pint + PHPStan level 5, enforced in CI).
> Hardening already landed: payout separation-of-duties, signed + idempotent payment **and** telco
> webhooks, refund/chargeback reversal with referral-commission clawback, telco OTP enrolment, web-SPA
> stateful auth, COPPA/NDPA parental-consent records, audit logging on sensitive actions, and an
> OpenAPI 3.1 spec (`docs/openapi.yaml`). **Per milestone, "Backend `[MVP]`" is ✅ except the
> vendor/integration seams noted inline (video pipeline, LRS push, ad network).** What remains is
> **frontend (kick-off — Vite SPA scaffold + master-brand design system + API/auth client landed;
> screens largely pending), integrations, content production, compliance paperwork, analytics, and
> launch hardening (M10).**
>
> **Frontend update (2026-06-27).** `web/` SPA scaffolded (Vite + React Router + TanStack Query +
> axios). **API client + auth layer landed:** typed axios client (bearer-token auth, `X-Organization-Id`
> tenant header, device id, normalized `ApiError` for both custom + 422 dialects, 401 auto-logout),
> `AuthProvider`/`useAuth` backed by `GET /me`, route guards, and wired **login / register (age-gate
> account-type) / forgot-password** screens on the master-brand design system. `npm run build` green.

---

## Milestone map (suggested order)

🟡 below = **backend done, frontend pending** (the gating work for each is now the React app, not the API).

| # | Milestone | Depends on | Status |
|---|---|---|---|
| 0 | Foundations & DevOps | — | 🟡 (DB/models/seeders/CI ✅; Docker/CD/Sentry/frontend scaffold ⬜) |
| 1 | Identity · Tenancy · Auth | 0 | 🟡 backend ✅ · frontend ⬜ |
| 2 | Content & CMS | 1 | 🟡 backend ✅ (managed video pipeline ⬜) · frontend ✅ (course/level/lesson/quiz builder + publish + **local file upload & playback**) |
| 3 | Learning loop (Learner app) | 2 | 🟡 backend ✅ (xAPI stored locally; LRS push ⬜) · frontend ✅ core (tree/enroll/player/grading/completion) |
| 4 | Gamification & retention | 3 | 🟡 backend ✅ · frontend ✅ core (streak/hearts/badges/leaderboard) |
| 5 | Family economy (wallet · chores) | 1, 3 | 🟡 backend ✅ · frontend ✅ core (hub/wallet/chores) |
| 6 | Monetisation & billing (cards + telco) | 5 | 🟡 backend ✅ (ad network ⬜) · frontend ✅ (paywall/entitlements/subs/telco-opt-in/data-bundles) · ad screen ⬜ |
| 7 | Referrals & commissions | 6 | ✅ backend + frontend complete (hub/share/payouts/fraud-alert + school-org hub) |
| 8 | School operations (admin + teacher) | 2, 4 | ✅ backend + frontend complete (classes/assignments/analytics/compensation — see `Mahadum360_Teacher_Portal_TODO.md`) |
| 9 | Super Admin | 6, 7, 8 | 🟡 backend ✅ · frontend ✅ (overview/settlements/orgs/promos) |
| 10 | Hardening & launch | all | 🟡 (security hardening substantially ✅; pen-test/load/WCAG/legal/beta ⬜) |

Ongoing in parallel: **Design**, **Content production**, **Integrations**, **Compliance/Security**, **Analytics**, **QA**, **DevOps**.

---

## ⚠️ Decisions to resolve first (unblock work) `[BLOCK]`

- [x] ✅ **Subscription matrix** — RESOLVED (2026-06-27): family features (dashboard/chores/monitoring) live in **Premium (Family)** tier (+ School); **Free = full learning + ads** (no content gate). Entitlements now exposed via `/me`; family-economy pages gated by `family_dashboard`.
- [ ] **Zero-hearts behaviour** — exact UX when hearts hit 0 (practice / rewarded ad / wait), respecting Rule 4 (never lock learning). *(blocks M4)*
- [x] ✅ **Canonical tagline** — **"Learn the language. Live the culture. Connect the generations."** (locked 2026-06-27; single source: `web/src/lib/brand.ts` → `TAGLINE`).
- [ ] **Wordmark** — `MAHADUM.360` (with dot) confirmed canonical over `Mahadum360`.
- [ ] **Max child profiles** — 5 vs 6 (BRD conflicts; `child_limit` defaults to 6).
- [ ] **LRS vendor** — Learning Locker / Veracity / SCORM Cloud (integrate, don't build). *(blocks M3 analytics)*
- [ ] **Managed video vendor** — Cloudflare Stream / Mux / Bunny. *(blocks M2)*
- [ ] **Streak Shield** — coins vs premium-only.
- [x] ✅ **Teacher commission payout** — RESOLVED (2026-07-04): cash to bank, monthly accrual per currently-enrolled paying student (`compensation:accrue-teachers`). See `Mahadum360_Teacher_Portal_TODO.md` §3.
- [ ] **Diaspora telco VAS** — Nigeria-only for MVP?
- [x] ✅ **Quiz attempt cap** — RESOLVED: schema supports both (`quizzes.max_attempts` null = unlimited). Enforced in `AnswerController`: replays past the cap are graded for practice (learner still sees the answer + explanation — never dead-ended, Rule 4) but nothing is scored and XP is never re-farmed. Covered by `QuizAttemptCapTest`.
- [ ] **Spaced-repetition algorithm** for flashcards (SM-2 vs Leitner).

---

## Phase 0 — Foundations & DevOps 🟡

**Repos & scaffolding**
- [ ] 🟡 Decide repo strategy (recommend: `api` Laravel + `learner-web` React + `console-web` React + shared `design-system` package).
- [x] ✅ Scaffold Laravel 13 API; PHP 8.3; configure timezone/locale.
- [x] ✅ `composer require` stancl/tenancy, spatie/laravel-permission, laravel/sanctum, laravel/socialite.
- [x] ✅ DB migrations + Eloquent models (generated) → copied in, package migrations published, `migrate` confirmed.
- [x] ✅ Wire `BelongsToTenant::currentTenantId()` to tenant context (`IdentifyTenant` middleware).
- [x] 🟡 Scaffold React app (Vite + Router + TanStack Query/axios + auth client). *(first SPA `web/` ✅ incl. **API client + bearer-token auth client**; second app + role-aware shells ⬜)*
- [x] 🟡 Shared design-system package (tokens + components) — refresh to **master brand** (rainbow logo, blue/navy/orange, Fredoka). *(in-app component library + tokens landed on master brand; extraction to a shared package ⬜)*

**Environments & pipeline**
- [ ] Local dev (Docker/Laravel Sail): MySQL/Postgres + Redis + queue worker + scheduler + Mailpit.
- [ ] Secrets management (.env per env; vault for prod keys).
- [x] ✅ CI: Pint (style), Larastan/PHPStan level 5 (static), PHPUnit feature suite on every push/PR.
- [ ] CD: staging + production; zero-downtime deploy; migration gate.
- [ ] Error tracking (Sentry), structured logging, uptime monitor.
- [x] ✅ Seeders: roles/permissions, plans, languages, badges + **demo org/family/learner + sample course** (`DemoSeeder`, local-only).

---

## Phase 1 — Identity · Tenancy · Auth 🟡

**Backend `[MVP]`**
- [x] ✅ Tenant resolution middleware (super-admin bypass · `X-Organization-Id` · derived from membership · direct-consumer).
- [x] ✅ Seed roles + permissions (super_admin, content_owner, teacher, supervisor, school_admin, parent, student) + Gate policies.
- [x] ✅ Registration with **age-gate** → branch: adult self-serve vs under-13 child-under-parent (COPPA/NDPA consent record).
- [x] ✅ Login (username/email + password); Google login (Socialite); password reset; **email verification** (verify + resend).
- [x] ✅ Sanctum: cookie auth (web SPA, stateful) + bearer tokens (mobile) with abilities.
- [x] ✅ Profiles: family + learner_profiles; **child profile switch** with parental PIN.
- [x] ✅ Device fingerprint capture (fraud groundwork); audit logging on sensitive actions (`AuditLogger`).
- [x] ✅ Tenant-isolation policies on every org-scoped resource.

**Frontend `[MVP]`**
- [x] 🟡 Landing page (hero) — public marketing page at `/`: tagline hero (3 rainbow clauses), feature grid, families/schools strip, CTAs → register/login, footer. Routing: `/` public (signed-in → `/home`), auth dashboard moved to `/home`. *(copy/illustration polish + real screenshots ⬜)*
- [x] ✅ Login — wired to API (`useAuth().login` → `/auth/login`, field + form errors, redirect-to-intended) **+ "Continue with Google"**.
- [x] 🟡 Sign-up flow + age gate + parent/child branch (COPPA). *(multi-step interactive wizard: **DOB age-gate** → digital-age check from `GET /config` (`digital_age` = admin `COMPLIANCE_MINOR_AGE`); **under-age → guardian-setup branch** w/ parental-consent checkbox; adults go straight to the form. Everyone registers as the family/account owner (`account_type: parent`) — self-learners vs children are handled later via learner profiles. Google sign-up on the form. Remaining: actual child-profile creation from dashboard + server-side consent record for the unauth under-age path ⬜)*
- [x] ✅ Forgot/reset password. *(forgot-password → `/auth/password/forgot`; **reset-with-token screen** `/reset-password?token&email` → `/auth/password/reset`, with invalid-link + invalid-token states. Backend `ResetPassword::createUrlUsing` now points reset emails at the SPA via `config('app.frontend_url')`.)*
- [x] 🟡 Console app shell (role-aware nav) + Learner app shell. *(single adaptive **`AppLayout`** shell landed: role-filtered sidebar (`visibleSections`), responsive mobile drawer, sticky topbar w/ **org switcher**, theme toggle, user menu (sign-out); authenticated routes nested inside; nav destinations not yet built resolve to a `ComingSoon` placeholder. Verified live across parent + school_admin roles. Bespoke per-surface polish ⬜)*
- [x] ✅ Profile switcher (PIN). *(topbar switcher lists learner profiles across families; unprotected switch immediately, **pin-protected open a PIN modal** (`CodeInput`) → `POST /profiles/{learner}/switch`; wrong PIN → 403 surfaced, correct → active profile persisted (`ActiveProfileProvider`); "exit to parent" clears it. Verified live.)*

**QA**
- [ ] Auth flows (register/login/google/reset) feature tests.
- [ ] **Tenant isolation tests** (School A cannot read School B).

---

## Phase 2 — Content & CMS 🟡

**Backend `[MVP]`**
- [x] ✅ CRUD: languages, courses, course_levels, lessons, lesson_components.
- [x] ✅ Quiz builder: quizzes → questions → options (all question types).
- [x] ✅ Speaking/exercise/game/assignment component endpoints.
- [x] 🟡 Media upload — **simple local-disk upload landed** (`POST /media/upload` → `public` disk + `MediaAsset`, served via `storage:link`; absolute URL). Video component stores `source_asset_id`; lesson-play exposes `video.src`. *(Managed **video pipeline** — transcode 240/360/720 HLS, poster, captions — still ⬜, vendor TBD; swap behind the same MediaAsset contract.)*
- [x] ✅ Publish rules (≥1 video + ≥1 quiz + ≥1 speaking; assets ready) + content **versioning** (draft/published).
- [ ] 🟡 Cultural content (proverbs/folktales/festivals). *(schema ready; content production)*

**Frontend — Console (CMS) `[MVP]`**
- [x] ✅ Course → Level → Lesson builder — `/courses` (list + create), `/courses/:id` (levels + lessons), `/courses/:id/lessons/:id` (component steps). Verified live (content_owner: course→level→lesson→publish).
- [x] ✅ Quiz builder UI — `QuizBuilderModal`: multi-question, **per-type editors for all 9 question types** (single/multi/true-false/fill-blank/listen-respond/complete-chat option editors + word-bank + match-pairs + type-the-answer, with audio-prompt picker) and correct-answer config; sends nested questions/options to `POST /lessons/{id}/components`.
- [x] ✅ **Bulk question import (CSV / Excel)** — "Import CSV/Excel" + "Template" in the quiz builder → `POST /quiz-imports/parse` (native, dependency-free: CSV via `fgetcsv`, `.xlsx` via `ZipArchive`+`SimpleXML`) parses to structured questions **for review in the builder** (no DB writes); per-row errors surfaced, then saved through the normal create path. Covers all 9 types via `type,prompt,options,correct,explanation,points` columns. Backend `SpreadsheetReader` + `QuizImportParser`, covered by `QuizImportTest`.
- [x] ✅ Video step — **file upload to local storage** (drag/choose a file in the Add-video modal → `POST /media/upload` → attached as the video source) + presenter/quality; learner player streams it via `<video src>`. Speaking step too. Verified live (upload → component → play `src`). *(Captions + processing status ⬜ — with the vendor pipeline.)*
- [x] ✅ Publish flow with validation feedback — `POST /lessons/{id}/publish`; 422 failures (e.g. "needs ≥1 video/quiz/speaking", "quiz has no questions") surfaced inline via `ApiError.details`. Verified live (fails empty → passes once complete).
- [x] ✅ Media library — `/media` (Content → Media): browse assets, **upload** (video/audio/image), preview, **copy URL**, **delete** (removes the local file). Backend `GET /media` + `DELETE /media/{asset}`. The Add-video modal also offers **"From library"** to reuse an existing asset (no re-upload). Verified live (upload → list → delete; reuse → wired to play `src`).
- [x] ✅ Lesson **Preview** — `Preview` button in the lesson builder opens a read-only learner-style walkthrough (video plays, quiz questions with correct answers ✓ for review, speaking prompt) from the authoring detail. Verified live.
- [x] ✅ Content nav visibility — **Courses** shown to `super_admin` + `content_owner` (full manage) and `school_admin` (**read-only** browse + preview; write actions hidden); **Media** to super_admin + content_owner. Verified live across all three roles.

**QA**
- [ ] Publish-rule enforcement; versioning doesn't disrupt in-progress learners.

---

## Phase 3 — Learning loop (Learner app) ⬜

**Backend `[MVP]`**
- [x] ✅ Placement assessment → result level.
- [x] ✅ Enrollment + learner path generation (locked/active/completed nodes).
- [x] ✅ `GET /lessons/{id}/play` (signed media, questions **without** answers).
- [x] ✅ Progress: component_progress + lesson finalisation (all components) + score formula.
- [x] ✅ **Server-side grading** for all quiz types; question_responses ledger.
- [x] ✅ Speaking submission (store audio; `needs_review`; AI score column kept — **Option B deferred**).
- [x] ✅ Assignment submission (→ parent review).
- [x] ✅ xAPI emit (enrol/video/quiz/speaking/lesson/placement events) → `xapi_statements` via `XapiRecorder`. *(LRS push deferred — rows stored unsynced)*

**Frontend — Learner `[MVP]`**
- [x] ✅ Learning tree — `/learn`: per-active-learner path (`GET /learners/{id}/path`) with unit grouping + locked/active/completed node states; **enroll empty-state** (lists published courses → `POST /enrollments`). Verified live (enroll → path builds).
- [x] ✅ Lesson player — `/learn/lessons/:id`: **all component types have real interactions** — video → `POST progress`; quiz (9 types); speaking → `POST speaking-submissions`; **assignment → `POST assignment-submissions`** (record/upload a clip, coins escrowed until parent approval); **exercise → flip-through flashcard deck** (front/back/mnemonic/audio); **game → memory-match** engine (config-driven pairs). Exercise/game complete via `POST progress`. Progress bar + hearts throughout.
  - [x] ✅ **Assignment → coin economy** shipped: authoring step (prompt + media type + coin reward) → learner records a clip → parent's `/reviews` queue shows the clip with Approve/Reject → **approval releases escrowed coins** to the learner's wallet via `WalletService::credit` (append-only ledger), audited (`assignment.reviewed`), with separation-of-duties (reviewer is a family parent, never the beneficiary) + idempotent single-review. Learning is never gated on approval (submit completes the step immediately). Covered by `AssignmentFlowTest`.
- [x] ✅ Quiz UIs — **all question types shipped**: option families (mcq_single, mcq_multi, true_false, fill_blank, listen_and_respond w/ audio prompt, complete_the_chat) + bespoke **word_bank** (tile arrange, order-graded) + **match_pairs** (assign from a shuffled pool, pairing kept server-side) + free-text (type-what-you-hear, accent-tolerant). Server grading (`POST components/{id}/answer`) branches per type; authoring editors + prompt-audio picker in the quiz builder. XP + hearts. Covered by grader/adapter/play-payload tests. *(`pronounce` bridge to speaking ⬜.)*
- [x] ✅ Slide-up feedback bar (correct → 🎉 +XP / almost → 💡 with the correct answer — never "wrong/fail"). Verified both paths + heart decrement.
- [x] ✅ Completion/reward screen (score %, XP, streak, badges) → `POST lessons/{id}/complete`; tree refreshes with next node unlocked. Verified live (92% score, streak 🔥1, next unlocked).
- [ ] Offline lesson download (premium; ≤5).
- [ ] Low-bandwidth video (360p default, adaptive, manual override) — *(blocked on video vendor; play payload exposes `hls`/`poster` placeholders).*

**QA**
- [ ] Grading correctness across all types; progress only finalises when complete; **hearts never lock learning**.

---

## Phase 4 — Gamification & retention ⬜

**Backend `[MVP]`**
- [x] ✅ Streaks + at-risk/reset states; **grace** (48h telco) + **shield**.
- [x] ✅ XP ledger; hearts (refill timer; Rule 4); badges + award logic.
- [x] ✅ Leagues (30-user) + weekly leaderboard; rank computation.
- [x] ✅ Jobs: `EvaluateStreaks`, league rollover.

**Frontend `[MVP]`**
- [x] ✅ Streak UI framed as **protected 🛡️** (not punitive); XP, hearts, badges — `/achievements`: streak card + **arm shield** (`POST /streak/shield`), hearts card + **refill** (ad/coins, `POST /hearts/refill`), weekly XP/league rank, badges grid (earned + locked). Compact 🔥/❤️ stats bar on the learning tree. Verified live.
- [x] ✅ Leaderboard / league screens (warmth over pressure) — `/leaderboard`: league header (name/tier/your rank+XP) + ranked list with self-highlight, encouraging copy. Verified live.
- *(Learner nav surfaced to parents/supervisors so a parent driving a child profile reaches Learn/Achievements/Leaderboard.)*

**QA**
- [ ] Streak grace/shield logic; leaderboard fairness.

---

## Phase 5 — Family economy (wallet · chores) ⬜

**Backend `[MVP]`**
- [x] ✅ Wallets (family) + **append-only coin ledger**; balance reconciliation.
- [x] ✅ Wallet funding via gateway; coin transfer (optimistic, revert on failure).
- [x] ✅ Chore lifecycle: create → submit evidence → review (approve/reject/more) → coins on approval only (Rule 8).
- [x] ✅ Parent notifications: DB + mail + **push + SMS/WhatsApp transports** (swappable `MessagingManager`, live opt-in) + retrieval API.

**Frontend — Parent (Console) `[MVP]`**
- [x] ✅ Family hub — `/family`: overview (name, profile usage, wallet summary, PIN status), **add-a-child** modal with COPPA/NDPA consent gate (`exists` language picker via `/config`) + child list + **Set/Change PIN**. Verified live (consent record = `coppa_parental` for under-13).
- [x] ✅ Wallet deposit + transfer; coin balance — `/wallet`: balance card, **transfer coins to child** (insufficient-coins guard), **fund** via Paystack/Flutterwave (idempotency-keyed → pending checkout). Verified live.
- [x] ✅ Chore management + **review queue** — `/reviews`: pending queue (chores + speaking/assignment counts), **new chore** modal, approve/reject/ask-for-more → coins released on approval only (Rule 8; verified child wallet credited).
- [x] ✅ Empty states (no children / all-caught-up review queue).
- *(Backend exposes `id` on `LanguageResource` for the language picker. Speaking/assignment submissions shown as counts — in-lesson review UI lands with the Learner app.)*

**QA**
- [ ] Ledger integrity; no coin auto-release without approval.

---

## Phase 6 — Monetisation & billing ⬜

**Backend `[MVP]`**
- [x] ✅ Plans + subscriptions (user/family/org polymorphic).
- [x] ✅ **Flutterwave + Paystack**: **signed, idempotent webhooks** (incl. refund/chargeback reversal) + **outbound hosted-checkout** via swappable `PaymentGatewayManager` (live calls opt-in per env; NullGateway otherwise).
- [x] ✅ **Telco SDP VAS** daily billing engine (02:00) + lifecycle (active→grace→soft_downgrade→reactivation) + signed DLR webhook + OTP enrolment + **swappable `TelcoGatewayManager`** (outbound charge + OTP SMS; live calls opt-in per env).
- [x] ✅ Jobs: `RunDailyTelcoBilling`, `ExpireGracePeriods`, `RetryFailedBilling`.
- [x] ✅ Data bundle top-up (one-tap carrier billing + consent).
- [ ] 🟡 Ad-supported free tier: post-lesson + rewarded-heart ads; **COPPA/NDPA ad filters**. *(data model ✅; ad-network integration ⬜)*
- [x] ✅ Receipts: `SubscriptionActivated` notification on activation, delivered over email + push + SMS/WhatsApp.

**Frontend `[MVP]`**
- [x] ✅ Paywall / upgrade — `/billing` plan comparison (Free/Premium/Family) + **entitlements gate** (`useEntitlements` from `/me`) + `PaywallGate` soft-gating family-economy pages (`/family`, `/wallet`, `/reviews`) for non-entitled users. Verified live (free→locked, active Family sub→unlocked, cancel→re-locked).
- [x] ✅ Subscription management + billing history; grace banners — current plan, **subscribe** (card→checkout via gateway webhook; idempotency-keyed) + **cancel**, history list, grace/pending banners. Backend: `id` on plans, `subscription`+`entitlements` on `/me`, new `GET /subscriptions`.
- [x] ✅ Telco opt-in (**phone OTP**) + cancellation copy — `TelcoOptInModal` on `/billing` ("or pay with airtime" per paid plan): msisdn+operator → OTP (`/telco/otp/request`) → 6-digit verify (`/telco/otp/verify`) → `/telco/subscribe` (daily airtime billing) + "text STOP to 3600" copy + grace/low-balance banner (`/telco/status`). Verified live (OTP→subscribe→entitlements premium).
- [ ] Ad screen (skip timer, remove-ads upsell, fail fallback). *(ad-network integration deferred)*
- [x] ✅ Data-bundle store modal — `DataBundleModal` (`/data-bundles` catalogue → operator + consent + one-tap `/data-bundles/purchase`, idempotency-keyed). Verified live.

**QA**
- [ ] Billing lifecycle states; webhook idempotency; **concurrency across operators**; ads never interrupt active lesson.

---

## Phase 7 — Referrals & commissions ⬜

**Backend `[MVP]`**
- [x] ✅ Referral codes (user + school); referral tracking.
- [x] ✅ Fraud: device/IP block (FR-7.1), velocity freeze >15/24h (FR-7.5), verified-payment gate (FR-7.2).
- [x] ✅ Commissions in **14-day escrow** + chargeback clawback (Rule 9); payouts (floor ₦5k, cap ₦50k; approver≠beneficiary SoD).
- [x] ✅ Promo codes (single-use per institution, no retro, no stacking).
- [x] ✅ Jobs: `ClearEscrowedCommissions`, `FlagReferralVelocity`.

**Frontend `[MVP]`**
- [x] ✅ Referral hub: code, **share (copy / WhatsApp / SMS)**, referral + commission counters by status — `/referrals` (`GET /referral-code`, `/referrals/summary`). Verified live.
- [x] ✅ **School-org referral hub** (2026-07-07) — a school's own referral code (kind `org`, distinct from a staff member's personal code) so commission from families referred by the school accrues to the organization, not an individual. `ReferralService::codeFor()` widened to accept any polymorphic owner (`User|Organization`); new `GET /schools/{org}/referrals/summary` + `POST /schools/{org}/referrals/payouts/request` (`SchoolReferralController`), `school_admin` granted `payouts.request`. Frontend: `/school/referrals` (`SchoolReferralsPage`), reachable from the School nav section. Verified live end-to-end (code issuance → qualified referral → cleared commission → payout request).
- [x] ✅ Payout request; earnings (pending vs cleared) — request modal (floor ₦5,000 client+server, bank/coins, idempotency-keyed `POST /payouts/request`) + payouts list (`GET /payouts`). Verified live (floor block + valid request).
- [x] ✅ Fraud-review alert UI (amber, support CTA) — `ReferralStatusAlert` on `/referrals` + `/earnings`: when the referral code is `flagged`/`frozen` (velocity guard FR-7.5), shows an amber "account under review" notice + mailto support CTA. Unit-tested + verified live.

**QA**
- [ ] Escrow/chargeback; fraud triggers; payout floor/cap.

---

## Phase 8 — School operations ⬜

**Backend `[MVP]`**
- [x] ✅ School registration + Super-Admin activation (CAC/domain verification) — audited.
- [x] ✅ **CSV roster import** (template, row validation, inline errors).
- [x] ✅ Classes + enrollments; seat allocation + tier discounts + inactivity review (≥40%/4wk).
- [x] ✅ Invoices (proforma/final) + **PDF rendering** (dompdf → private media asset, streamed download); term subscription.
- [x] ✅ School dashboard analytics; school referral commissions.

**Frontend — Console `[MVP]`**
- [x] ✅ School admin dashboard (KPIs, classes) — `/school`: org KPIs (classes, students, seats filled, unpaid invoices) + class list + **new class** modal. Verified live.
- [x] ✅ Roster + CSV import; classes; seats; invoices — `/roster` (**CSV upload** → per-row import w/ error report), `/seats` (filled gauge + **buy seats** with volume discount + proforma invoice), `/invoices` (list + **PDF download**). Verified live (60 seats → 10% off → invoice; CSV 2 imported + bad row flagged; PDF stream).
- [x] 🟡 Teacher portal: classes + earnings + referral hub — `/classes` (teacher's classes → student roster modal, `GET /classes` + `/classes/{id}`), `/earnings` (cleared vs escrow commissions + payouts + request payout), referral hub reused at `/referrals`. Verified live (teacher Angelica: 2 classes, 11/18 students). **Teacher analytics ✅ (2026-07-03):** `GET /classes/{class}/analytics` (`SchoolClassController@analytics`, `can:view,class`) — per-student lessons-completed, avg lesson score, quiz accuracy, speaking count; surfaced as a **Students / Analytics** tab in the class modal (`ClassesPage`). Verified live. *(Class-level assignment creation ⬜ — the `assignments` table is content-component-level, not teacher→class; needs a new model + product decision on "class assignment". `/assignments` still a placeholder.)*

**QA**
- [ ] CSV edge cases; seat accounting; org-scoped analytics correctness.

---

## Phase 9 — Super Admin 🟡

**Backend `[MVP]`**
- [x] ✅ Platform metrics (revenue, users, language analytics, billing rates).
- [x] ✅ **Settlement panel**: commissions/payouts/telco revenue + clawback-pending total; billing health.
- [x] ✅ Promo codes; org activation; content/language control. *(fraud review queue surfaced via flags; dedicated UI ⬜)*

**Frontend `[MVP]`**
- [x] ✅ Admin dashboard + settlement/payout/billing-health/promo panels — `/admin` (platform KPIs: users, revenue, orgs/subs by status, telco success rate, funding health), `/admin/settlements` (commissions/payouts by status + telco revenue + clawback-pending), `/admin/orgs` (list + **activate** pending orgs), `/admin/promos` (**create promo code**). Verified live as super_admin.

---

## Phase 10 — Hardening & launch 🟡

- [x] 🟡 Security hardening landed: payout SoD, signed+idempotent webhooks (payment **and** telco), refund/chargeback reversal + commission clawback, telco OTP, SPA CSRF auth, COPPA/NDPA consent, audit logging, rate limiting on auth/OTP. **External penetration test + formal authz audit still ⬜.**
- [ ] Load test billing concurrency + leaderboard; performance budget (<3s on 3G).
- [x] 🟡 **WCAG 2.1 AA** — automated **axe** checks in CI (`npm test`) over login/register/forgot/dashboard + modal dialog semantics (labels, names, ARIA, roles) — all clean. *(Manual audit still ⬜: color-contrast, tap targets ≥44px, screen-reader walkthrough, 200% zoom, captions — need a browser-based audit.)*
- [ ] Low-end Android testing (2GB RAM, Android 8, 360px).
- [ ] Legal: Terms, Privacy, COPPA parental-consent, NDPA, refund/cancellation copy.
- [ ] Telco commercial contracts signed (MTN/Airtel/Glo/T2); live gateway keys.
- [ ] Backups + restore drill; runbooks; on-call/alerts.
- [ ] Seed launch content (initial lessons across 4 languages, native-speaker reviewed).
- [ ] **Closed beta** with real families + 1–2 pilot schools; fix-list; go/no-go.

---

## Ongoing workstreams (parallel)

### Design 🟡
- [ ] `[BLOCK]` Lock tagline + wordmark; finalise master brand.
- [ ] 🟡 Refresh design-system file + handoff doc to master brand (currently green/gold era).
- [ ] Commission **character/illustration art** (Igbo/Hausa/Yoruba cast + scenes) → replace placeholder slots.
- [ ] Hi-fi all screens (learner, parent, teacher, school admin, super admin) in Figma.
- [ ] Interaction/motion specs (celebrations, transitions, feedback bar).

### Content production ⬜
- [ ] Native-speaker audio + video recording per language; tone-mark review of all in-app copy.
- [ ] Caption authoring (en + target); cultural content (proverbs/folktales).
- [ ] Curriculum sequencing (levels/lessons) for Yoruba, Igbo, Hausa, Pidgin.

### Integrations ⬜
- [ ] Flutterwave + Paystack (cards/transfers/webhooks).
- [ ] Telco SDP APIs — MTN, Airtel, Glo, T2 (commercial + technical, USSD/SMS gateway).
- [ ] Managed video (Cloudflare Stream / Mux / Bunny).
- [ ] LRS (xAPI).
- [ ] Ad network (COPPA/NDPA-safe).
- [ ] Notifications: FCM push, SMS, WhatsApp Business, email.
- [ ] Google OAuth; Sentry; object storage + CDN.

### Compliance & Security ⬜
- [ ] COPPA: parental consent flow + records; under-13 data handling.
- [ ] NDPA (Nigeria) + GDPR/CCPA (diaspora); data retention + deletion.
- [ ] PCI-DSS via gateway tokenisation (never store card data).
- [ ] Content moderation + child-safe ad filtering; audit logs.
- [ ] Privacy policy / Terms / cookie + consent.

### Analytics ⬜
- [x] 🟡 xAPI statements + LRS; lesson **drop-off** analytics. *(Lesson **drop-off funnel + per-question accuracy** shipped — `GET /lessons/{id}/analytics` (`can:analytics.lesson.view`) → authoring "Insights" modal, aggregated from the progress ledgers; covered by `LessonAnalyticsTest`. LRS push still deferred.)*
- [ ] KPI dashboards: DAU/MAU, D7/D30 retention, freemium conversion, wallet funding velocity, ARPU by channel, lesson completion, speaking improvement, K-factor, CAC, daily billing renewal.

### DevOps ⬜
- [ ] Infra (app servers, managed DB, Redis), queue workers, scheduler/cron.
- [ ] Autoscaling, backups, monitoring/alerting, log retention.

### QA (cross-cutting) 🟡
- [x] ✅ Backend feature suite (PHPUnit, 57+ tests) — green in CI.
- [x] 🟡 **Web unit/component suite (Vitest + Testing Library)** — **31 tests / 9 files**: error normalization, money formatting, role-based nav filtering, paywall gate, register age-gate branching, **login submit + error**, **Protected/Guest route guards**, **entitlements hook**, and the **fraud-review alert**. Wired into CI (`web` job: `npm test` + typecheck/build). *(True E2E/Playwright across the live stack ⬜.)*
- [x] 🟡 Accessibility automated checks (axe over key screens, in CI). *(Load/concurrency tests for billing still ⬜.)*

---

## Post-MVP / deferred `[POST]`

- [ ] **Mobile apps** (iOS + Android) — native or RN, reusing the API.
- [ ] **AI Pronunciation Coach** — plug into kept `ai_score` (Option B → live).
- [ ] AI Conversation Mode; AI Storytelling generation.
- [ ] Live human tutors (Preply-style marketplace).
- [ ] Pan-African languages (Swahili, Amharic, Zulu, Xhosa, Twi, Ewe, Wolof, Lingala).
- [ ] RTL / Ajami script support (architecture already RTL-ready).
- [ ] Dark mode (token set already structured for it).
- [ ] Third-party content-creator marketplace.
- [ ] DB-per-tenant graduation for very large school districts (stancl path).

---

## How to track
Treat each milestone as an epic; each `[ ]` as a ticket. Keep the **Decisions** block as a standing blocker list — most stalls will trace back to one of those. Update the milestone-map statuses weekly.
