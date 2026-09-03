# MAHADUM.360 Beta Feedback Remediation TODO

**Reviewed:** 2026-09-03
**Feedback source:** `MAHADUM360_Post_Deployment_Beta_Testing_Feedback updated september 1.docx`
**Scope:** Detailed code-to-feedback review and implementation backlog. This document does not itself authorize production data deletion or changes that conflict with locked product rules.

## Status legend

- `[x]` Implemented in the reviewed code; retain as a production regression check.
- `[~]` Partially implemented; remaining checks are listed in the item.
- `[ ]` Confirmed implementation gap.
- `[?]` Product/content decision required before engineering.
- `[OPS]` Operational task requiring production access, a backup, and an audit trail.

## Executive triage

The referral requirements are implemented in the current release candidate. Several screenshots in the feedback document predate later work: the avatar picker now contains both human and animal images, lesson replay no longer re-awards lesson XP, quiz questions default to 1 XP, and leaderboard totals are sourced from the cumulative XP ledger.

The largest confirmed gaps are course metadata editing/preview, per-quiz boundaries and result screens, real paid-plan unlimited hearts, sentence-tile randomization, flashcard bulk import, manual global user creation, badge/tier rules, tone-feature labelling, contextual profile adverts, telco entitlement restrictions, and FluentCRM integration.

Two requested behaviours must not be implemented as written because they conflict with the locked BRD rule that Free users receive full learning access with ads:

1. Locking a lesson or quiz for 12 hours when hearts reach zero.
2. Locking Lesson 1 and later behind payment.

Use the non-blocking alternatives in BF-10.8 and BF-13.1 unless the product owner formally changes the BRD and the master implementation plan.

## Recommended delivery order

1. **Release A — correctness and misleading claims:** BF-05, BF-06, BF-09, BF-10.1–BF-10.7, BF-10.9, BF-12.2.
2. **Release B — authoring/admin gaps:** BF-02, BF-03, BF-14.
3. **Release C — gamification and invitations:** BF-07, BF-08, BF-11.
4. **Release D — integrations/operations:** BF-12.3, BF-15, BF-16.
5. Keep BF-10.8 and BF-13.1 blocked until the product-rule decision is documented.

---

## BF-01 — Referral logic

**Review finding:** Implemented in the current code/release candidate. Keep this section as the production acceptance checklist because the feedback was originally raised against production.

- [x] Referral URLs use `/r/{code}` and resolve to the intended referral landing/registration flow.
- [x] Activation requires an active paid subscription plus at least one completed lesson and one completed quiz.
- [x] A qualifying purchase creates a 5% commission within the configured first-month attribution window; commission remains subject to the existing 14-day escrow and clawback rules.
- [x] Inviting an existing active account returns the specific `account_exists` response instead of creating a referral invitation.
- [x] Referrer dashboard supports email/phone invitations, search, activation date, channel, status, and commission history.
- [x] Super-admin referral-code dashboard reports activations, email/phone split, and active/inactive counts.
- [ ] **Production regression:** open a newly generated referral link in a logged-out/private browser and verify it preserves the code through email registration and Google registration.
- [ ] **Production regression:** exercise activation in both event orders: payment → learning and learning → payment.
- [ ] **Production regression:** confirm a qualifying payment creates exactly one commission, at 5%, with the expected escrow release date.
- [ ] **Production regression:** confirm existing-account invitations are blocked and both dashboards agree with database counts.

**Primary coverage:** `ReferralTest`, `ReferralInvitationTest`, `AdminReferralCodesTest`, `GoogleAuthTest`.

## BF-02 — Global manual user creation

**Review finding:** Confirmed gap. The super-admin user API currently lists, views, changes roles, and changes status; it has no create endpoint or create form.

- [x] Add `POST /api/v1/admin/users`, guarded by `users.manage` and the admin route group.
- [x] Add a dedicated FormRequest for first name, last name, unique email, optional username/phone, initial role, status, locale, and optional organization membership.
- [x] Generate a set-password invitation instead of accepting or emailing a plaintext password.
- [x] Use `OrganizationUser` for membership writes; do not rely on pivot access.
- [x] Audit creation, initial role grant, and membership assignment without logging credentials or invitation tokens.
- [x] Add a **Create user** action/modal or page to `/admin/users`, including field-level 422 errors.
- [x] Prevent unsafe combinations: no tenant assignment for global-only roles; validate organization and role compatibility; never silently elevate a tenant user to `super_admin`.
- [ ] Add feature tests for global user, organization member, duplicate email, unauthorized caller, invitation delivery, and audit record.
- [ ] Add Vitest/axe coverage for successful creation and validation errors.

**Acceptance:** A super admin can create an account, optionally attach it to an organization, and the recipient can securely set their password; a non-super-admin cannot use the endpoint.

## BF-03 — Course upload and course structure

### BF-03.1 Edit course header/metadata

**Review finding:** Backend `CourseController::update()` already accepts audited course metadata, but the SPA has no corresponding API method/hook or edit control in the course builder.

- [x] Add `contentApi.updateCourse()` and `useUpdateCourse()` with course-list/level query invalidation.
- [x] Add an **Edit course details** action to the course builder header.
- [~] Allow editing title, description, and level band. Language remains immutable here because changing it after content exists is not yet safe.
- [x] Surface backend field errors and protect the action with `content.courses.manage`.
- [ ] Test successful edits, authorization, validation, cache refresh, and audit before/after values.

### BF-03.2 Description in course preview

**Review finding:** `CourseSummary.description` is available, but `CoursePreviewPage` renders only the title and units.

- [x] Render the saved course description near the preview header/start content.
- [x] Define an empty-description fallback that does not leave unexplained whitespace.
- [ ] Add a UI test proving edited text appears in preview after query invalidation.

### BF-03.3 Table of contents

**Review finding:** Confirmed UI gap. The existing level/lesson hierarchy can supply an MVP table of contents without a new free-form content model.

- [ ] Add a generated TOC to course preview: ordered levels, ordered lessons, activity/quiz counts, and estimated duration where available.
- [ ] Make entries navigable to the relevant unit/lesson preview where permissions allow.
- [?] Decide whether “add a table of contents” means the generated hierarchy above or a separately authored rich-text TOC. Prefer generated content to prevent drift.
- [ ] Test empty course, multi-level course, ordering, draft inclusion in author preview, and published-only learner display.

## BF-05 — Pricing and account-type corrections

### BF-05.1 Public pricing copy

**Review finding:** Confirmed exact stale strings in `PricingPage.tsx`; offline downloads are also still enabled in seeded plan entitlements, so this is not only a copy change.

- [ ] Change Free copy to the approved wording after resolving BF-13.1; do not publish “Free lessons” if it implies a paywall that the BRD forbids.
- [x] Change Individual inheritance copy to “All Free plan benefits” (grammar-normalized from the feedback wording).
- [x] Change Family inheritance copy to “All Individual plan benefits.”
- [x] Remove “Offline lesson downloads” from public cards until the feature exists.
- [x] Set `offline_download=false` for Individual and Family seed/config data and migrate existing plan rows safely.
- [x] Add a regression test that prevents unsupported offline copy from reappearing in plan presentation.

### BF-05.2 Pricing page and billing page parity

**Review finding:** Public pricing uses hard-coded benefit arrays while billing builds benefits from plan records, allowing drift.

- [~] Establish one shared benefit-presentation source; names, prices, and cadence continue to come from the plans API.
- [x] Make `/pricing` and `/billing` consume the same benefit presentation mapping and plans API response.
- [ ] Add parity tests for Free, Individual, and Family across monthly/annual views.
- [ ] Confirm admin-edited prices propagate consistently without changing locked marketing copy unexpectedly.

### BF-05.3 Individual signup option

**Review finding:** The API already supports `account_type=learner`, which creates an adult learner profile, but the public selector exposes only Family, Educator/School, and Institution. Family currently combines adult self-learning with household creation.

- [x] Add an **Individual** selector and a backward-compatible `individual` API alias for the existing learner provisioning path.
- [x] Keep Family as the parent/household path and make the descriptions unambiguous.
- [x] Apply the same mapping to email and Google signup contexts and post-signup navigation.
- [x] Require the configured digital age for Individual signup and preserve the guardian flow for minors.
- [~] Add backend alias and frontend selector coverage; explicit Google/redirect assertions remain.

## BF-06 — English landing-page quiz wording

**Review finding:** The widget always wraps a short meaning in “Which phrase means … in English?”, so the requested natural English questions cannot be represented cleanly by the current data shape.

- [x] Extend `QuizRound` with an optional full-question field while preserving the existing translated-language prompt format.
- [x] Use these exact English questions:
  - “Which phrase is a greeting in the morning?”
  - “Which word is politely added to a request?”
  - “Which phrase is a polite answer to ‘How are you?’”
- [x] Keep answer choices, notes, quotation marks, and accessibility labels correct.
- [x] Add regression coverage for all three exact English questions and verify other languages retain the translation prompt.

## BF-07 — Badges, streak definition, and learning levels

**Review finding:** Production badge logic currently awards only First Steps, Week Warrior, and Sharp Shooter. “Family Hero” and the six named levels appear only as showcase/design text, not implemented rules.

### BF-07.1 Streak definition and display

- [?] Clarify the feedback sentence “Days of uninterrupted learning except if you use a streak.” The likely intended exception is a streak shield/freeze; document its exact effect and consumption rule.
- [ ] Retain the existing consecutive-day calculation and streak-shield protection once the wording is confirmed.
- [x] Standardize current learner-facing text as `1 Day Streak` / `{n} Day Streaks`.
- [ ] Test same-day activity, next-day activity, missed day, shield use, timezone boundary, and singular/plural copy.

### BF-07.2 Family Hero

- [?] Define eligibility: all learners globally, within one family, or within a league; define timezone, ties, and whether “score” means daily XP or quiz score.
- [ ] Add the badge definition and deterministic daily award job only after the definition is approved.
- [ ] Make the job idempotent and test ties, reruns, no activity, and day boundaries.

### BF-07.3 Learning levels

- [?] Define numeric thresholds and whether levels derive from lifetime XP, course mastery, or league performance.
- [ ] Implement the approved mapping: Level 0 Star Starter, Level 1 Bronze, Level 2 Silver, Level 3 Gold, Level 4 Platinum, Level 5 Culture Master.
- [ ] Return level number/name from the API and use it consistently on profile, achievements, and leaderboard surfaces.
- [ ] Add boundary tests for every threshold and migration/backfill tests for existing learners.

## BF-08 — Tone-practice invitation

**Review finding:** Speaking submissions exist, but there is no peer invitation/link workflow and no defined “cannot practice tone” state.

- [?] Define the trigger and audience: microphone unavailable, learner chooses “practice with someone,” failed speech recognition, or an author-marked partner activity.
- [?] Confirm privacy rules for child profiles: inviter display name, guardian approval, recipients, link expiry, and whether a recipient must sign in.
- [ ] Design a signed, expiring invitation URL tied to the specific published video/activity; never expose a child’s email, phone, or internal learner ID.
- [ ] Add email/WhatsApp/share-sheet delivery through existing messaging seams, with guardian control for minors.
- [ ] Use approved, pronoun-safe invitation copy; do not hard-code “with her.”
- [ ] Track sent/opened/accepted states and audit abuse-sensitive events.
- [ ] Test expired/tampered links, unpublished content, minor privacy, existing/new recipients, and rate limits.

## BF-09 — Tone feature “Coming soon” state

**Review finding:** Tone/speaking claims currently appear across landing, authoring, lesson player, achievements/progress, reviews, and demo/showcase content. A single feature flag does not yet suppress or label them consistently.

- [ ] Inventory every tone/speaking entry point and distinguish the unfinished **tone scoring/practice** feature from already-working speaking recording/review.
- [?] Approve which capability is actually unavailable; avoid labelling all speaking workflows unavailable if only automated tone scoring is pending.
- [~] Add a centralized `tone_practice` feature flag and consistent `Coming soon` badges on current tone-scoring/game surfaces.
- [x] Prevent creation of unfinished tone games while keeping speaking recording and learning non-blocking.
- [ ] Update public marketing claims so they do not advertise unavailable functionality.
- [ ] Add route/component tests that assert the label everywhere covered by the flag.

## BF-10 — Quiz and lesson experience

### BF-10.1 No XP on lesson repetition

**Review finding:** Implemented. `LessonCompletionController` checks prior completion and awards zero lesson XP on replay.

- [x] Preserve idempotent lesson-completion XP.
- [ ] Add/retain a regression test for repeated completion, concurrent duplicate requests, and a replay containing already-earned question XP.
- [ ] Verify production ledger entries have unique logical references and no duplicate award from retries.

### BF-10.2 Clear streak text

- [x] Replace the completion card’s bare flame/count with explicit text such as `1 Day Streak`.
- [x] Apply consistent wording on achievements and learner summaries.
- [~] Test singular and plural formatting; full component coverage for zero remains.

### BF-10.3 Separate each uploaded quiz and show its result

**Review finding:** Separate quiz components exist in storage, but the player flattens every component’s questions into one slide stream and only renders a lesson-level completion screen.

- [x] Preserve quiz component boundaries in player state instead of treating all quiz slides as one continuous quiz.
- [x] After each quiz component, show score (`correct/total` and percentage), XP earned for that quiz attempt, pass/fail outcome, with hearts retained in the player header where applicable.
- [x] Require an explicit **Continue to next activity** action after the summary.
- [ ] Ensure resume logic starts at the correct question or component summary after refresh.
- [x] Keep author preview and live learner playback behavior aligned through the shared slide deck.
- [ ] Add tests for two quizzes in one lesson, non-quiz components between quizzes, refresh/resume, no-question quiz, and retry.

### BF-10.4 One XP per correct question

**Review finding:** Implemented for newly authored/imported questions: questions default to 1 point and XP is awarded per correct question once per learner/question. The August migration changes quiz component XP defaults but does not itself rewrite legacy `questions.points` values.

- [x] Preserve per-question award and anti-farming check.
- [ ] Audit production `questions.points` for legacy non-1 values before claiming all quizzes comply.
- [ ] If product requires exactly 1 XP universally, add a reviewed data migration for question rows and prevent authors/imports from overriding points; otherwise clarify that 1 is a default, not a fixed rule.
- [ ] Add a 10-question acceptance test: ten first-time correct answers award 10 XP; replay awards 0 additional XP.

### BF-10.5 Rewatch video after a failed quiz

- [x] Add a failed-quiz result action to return to the nearest preceding lesson video and retry the same quiz.
- [?] Define which video is “relevant” when a lesson has multiple videos or a quiz precedes all videos. Prefer the nearest preceding required video, with a lesson-start fallback.
- [x] Preserve server-side completed progress and anti-farming behavior when reviewing video/lesson content.
- [ ] Test pass, fail, multiple videos, no video, and attempt-cap behavior.

### BF-10.6 Free-plan heart deduction cadence

**Review finding:** Current backend deducts one heart for every incorrect answer on a heart-enabled quiz, regardless of plan. The feedback says both “a failed quiz should deduct hearts” and “every 4 questions = 1 heart,” which is not precise enough to implement safely.

- [?] Confirm whether one heart is lost per four **incorrect answers**, per four **answered questions**, or per failed quiz of up to four questions.
- [ ] Apply heart deductions only to plans without `unlimited_hearts` after the cadence is approved.
- [ ] Make the counter transaction-safe under concurrent answer requests.
- [ ] Return enough API state for an accurate, immediate UI update.
- [ ] Test Free vs paid, correct/incorrect cadence boundaries, retry, duplicate request, and zero-floor behavior.

### BF-10.7 Paid plans have unlimited hearts

**Review finding:** Plans advertise the entitlement, but `AnswerController` does not consult it and still deducts stored hearts. This is a confirmed functional bug.

- [x] Resolve entitlements server-side for the learner’s owning user/family/organization before heart mutation.
- [x] For `unlimited_hearts=true`, skip deduction and return `hearts_remaining=null` plus `unlimited_hearts=true`.
- [x] Hide numeric-heart depletion in the player for unlimited users.
- [ ] Test Individual, Family, school/org entitlement inheritance, cancelled/grace subscriptions, and Free fallback.

### BF-10.8 Hearts exhausted / 12-hour lock

**Review finding:** Blocked by product rule. Current code deliberately floors hearts at zero but never blocks learning.

- [?] **Do not implement the requested lock without a formal BRD change.** It conflicts with “Free = full learning + ads” and “learning is never dead-ended.”
- [ ] Recommended compliant alternative: at zero hearts, keep lessons/quizzes playable in practice mode, pause new XP/competitive score for 12 hours, and offer an optional rewarded-ad refill or paid unlimited hearts.
- [ ] If the alternative is approved, show supportive copy that clearly says learning remains available; do not use “See you in 12 hours” if the learner can continue.
- [ ] Add clock-injected tests for cooldown start/expiry, practice access, rewarded refill, and paid bypass.

### BF-10.9 Shuffle “Build the Sentence” options

**Review finding:** Confirmed bug. `WordBankInput` currently presents `slide.options` in authored/correct order.

- [x] Shuffle a stable copy of word-bank options once when each question attempt mounts.
- [x] Use Fisher–Yates; never mutate the authored answer-key order.
- [x] Avoid returning the correct order when possible; retry/replay remounts and reshuffles.
- [x] Provide a deterministic RNG seam for tests.
- [ ] Test grading order, duplicate word labels with distinct IDs, rerender stability, retry reshuffle, and accessibility order.

## BF-11 — Parent/child profile adverts

**Review finding:** All eligible consumer pages request the same generic `inline` placement; the advert model supports position, not route/audience context.

- [x] Add a dedicated `profile_data_topup` advert placement.
- [x] Use it on parent/family and child-profile routes only.
- [x] Seed a data-top-up creative linked to billing.
- [ ] Retain COPPA/NDPA eligibility filtering and paid/staff ad suppression.
- [ ] Add fallback behavior when no profile advert is active; do not fall back to a course advert if that violates the approved placement policy.
- [ ] Test parent, child, under-age, paid, staff, inactive creative, and schedule boundaries.

## BF-12 — Child profiles and leaderboard

### BF-12.1 Mixed human and animal avatars

**Review finding:** Implemented in the reviewed code. `AVATAR_PRESETS` contains animal art plus cultural human portraits.

- [x] Keep both categories in the picker and stable preset IDs for existing profiles.
- [ ] Production regression: verify all referenced files deploy with correct case-sensitive names, load without 404s, and have useful accessible labels.
- [ ] Confirm the picker visibly mixes categories rather than burying one category after a long scroll; add grouping/filtering only if usability testing shows it is needed.

### BF-12.2 Cumulative leaderboard and “Level” label

**Review finding:** The leaderboard already sums XP-ledger entries for the current week, but repeated correct answers intentionally do not re-award XP. Therefore the example `40 + 35 + 37 = 112` will only occur if those attempts legitimately create new score/XP entries. The UI still says “Tier.”

- [ ] Change the user-facing label from “Tier” to “Level” once BF-07.3 level semantics are approved.
- [?] Decide whether leaderboard points are XP, quiz-attempt score, or a separate competitive score. Do not remove anti-farming controls merely to make repeated attempts cumulative.
- [ ] Recommended: keep XP idempotent and add a separate, abuse-resistant attempt-score ledger only if every replay must count.
- [ ] If attempt scores count, define caps, best-vs-all attempts, time window, abandoned attempts, negative adjustments, and tie-breaking.
- [ ] Test cumulative entries, replay abuse, weekly rollover, concurrent attempts, and rank ties.

### BF-12.3 Remove dummy data

**Review finding:** Operational request, not a code deletion. Seeders intentionally generate local/demo data and are environment-gated; production rows must be identified before removal.

- [OPS] Take and verify a restorable production database backup.
- [OPS] Produce a dry-run inventory keyed by IDs/emails/ownership/source—not names alone—including Angela and all dependent learner, progress, XP, wallet, referral, subscription, and media records.
- [OPS] Obtain sign-off on the exact inventory; “Angela” is not a safe unique deletion criterion.
- [OPS] Delete in a transaction or approved archival/anonymization workflow, respecting financial/audit retention requirements.
- [OPS] Reconcile leaderboard, family counts, seats, referrals, invoices, and analytics after removal.
- [ ] Add a production safeguard test/config assertion ensuring `DevSeeder`/`DemoSeeder` cannot run in production.

## BF-13 — Free-tier lesson lock

### BF-13.1 Lesson 0 free; Lesson 1+ paid

**Review finding:** Blocked by the locked BRD. It directly contradicts “never gate/lock learning behind hearts or paywall” and “Free = full learning + ads.” The requested pricing copy in BF-05 also depends on this unresolved conflict.

- [?] **Do not build the paywall as written** unless the BRD, entitlement model, public terms, pricing copy, and master implementation TODO are formally revised together.
- [ ] Preserve visibility of every published language for Free users.
- [ ] Recommended compliant monetization: keep all core lessons available; reserve conveniences such as ad-free use, unlimited hearts/competitive attempts, family dashboards, offline access when built, and enhanced analytics for paid plans.
- [ ] If leadership overrides the BRD, write a separate migration/design covering lesson entitlement metadata, catalogue lock state, deep-link enforcement, API authorization, existing enrolments, schools/telco, refunds, analytics, and accessibility before implementation.

## BF-14 — Flashcard bulk upload template

**Review finding:** Confirmed gap. Quiz import supports CSV/XLSX/DOCX, but the flashcard builder is manual and has no importer/template.

- [~] Add a downloadable UTF-8 CSV template with friendly `Front (Word)` and `Back (Meaning)` headers; XLSX remains.
- [ ] Define optional columns up front if supported: mnemonic, audio asset ID, and image asset ID.
- [ ] Add a parse/preview endpoint using a flashcard-specific parser; do not overload quiz semantics.
- [ ] Show valid/invalid/duplicate row counts and row-level errors before save.
- [ ] Import in a transaction and choose/document append vs replace behavior; default to append with explicit duplicate handling.
- [~] Preserve Unicode/diacritics with BOM CSV parsing/export; formula-injection hardening and a server preview remain.
- [ ] Add tests for CSV/XLSX, BOM, blank rows, duplicate words, missing columns, accented text, large-file limits, authorization, and rollback.

**Acceptance:** An authorized content owner can download a template, upload a completed file, preview errors, import valid cards, and immediately see the correctly ordered front/back values in author preview.

## BF-15 — Telco integration

**Review finding:** Airtime billing currently lets a caller submit any monthly personal plan ID. It creates a normal user subscription and has no Level-1-only language entitlement. The UI exposes airtime payment from each eligible paid-plan card, including Family.

- [?] Define whether “Telco account” is a signup channel, a billing method, or a distinct entitlement tier. Prefer billing method + a dedicated `telco_individual` plan to avoid account-type branching.
- [x] Restrict telco enrolment server-side to the approved monthly Individual plan; validate before consuming the single-use OTP.
- [x] Hide airtime payment for Family and non-monthly plans in billing.
- [?] Reconcile “only Level 1 of each language” with the same no-learning-paywall conflict in BF-13.1. A channel-specific content restriction also risks inconsistent entitlements.
- [ ] If approved, model the restriction explicitly in plan entitlements and enforce it in catalogue, play API, deep links, and offline/cache behavior—not only in UI.
- [ ] Add tests for tampered plan IDs, all operators, existing subscription replacement, failed/grace billing, entitlement downgrade, and language/level access.
- [OPS] Commercial contracts and live operator credentials remain external launch dependencies.

## BF-16 — Email marketing: FluentCRM and Amazon SES

**Review finding:** Email work has started. The app has contact lists/import, suppression-aware campaigns, branded templates, delivery logs, queueing, and a super-admin SMTP configuration/test screen. Amazon SES can be used through SMTP today. There is no FluentCRM synchronization/integration in the reviewed code.

### BF-16.1 Amazon SES delivery

- [x] SMTP host/port/scheme/username/password/from settings can be saved securely and tested from the admin portal.
- [ ] Configure verified SES identity/domain, DKIM/SPF/DMARC, production SMTP credentials, correct region endpoint, and an approved From address.
- [ ] Move SES out of sandbox or confirm recipient restrictions before launch.
- [ ] Configure and monitor the queue worker; verify password reset, invitation, receipt, and campaign samples.
- [ ] Connect SES bounce/complaint/delivery events to suppression and delivery status; SMTP acceptance alone is not final delivery.
- [OPS] Run inbox/spam placement tests and document credential rotation and incident procedures.

### BF-16.2 FluentCRM

- [?] Decide the system of record. The app already contains a campaign/contact-list system; duplicating ownership in FluentCRM will create unsubscribe and consent drift.
- [ ] Recommended integration boundary: MAHADUM remains the source of truth for identity/consent; sync eligible contacts, tags, locale, plan, and lifecycle events to FluentCRM; ingest unsubscribe/bounce status back.
- [ ] Confirm FluentCRM deployment URL, API/auth method, required lists/tags, field mapping, lawful basis/guardian consent, and retention rules.
- [ ] Build idempotent queued sync with retry/backoff, rate limits, audit-safe logs, and a reconciliation command/dashboard.
- [ ] Never sync child learner profiles or sensitive learning data unless a separately approved legal/privacy requirement exists.
- [ ] Add contract tests against a stub, replay/idempotency tests, unsubscribe precedence tests, deletion/anonymization tests, and an end-to-end staging test.

---

## Cross-cutting release gates

- [ ] Every completed item has backend feature tests where server behavior changes.
- [ ] Every changed SPA flow has Vitest coverage and an axe accessibility check where applicable.
- [ ] `vendor/bin/pint --test` passes.
- [ ] `vendor/bin/phpstan analyse --level=5 --memory-limit=512M` passes.
- [ ] `php artisan test` passes.
- [ ] `npm test` passes in `web/`.
- [ ] `npm run build` passes in `web/`.
- [ ] Database migrations have a reviewed rollback/data-preservation strategy.
- [ ] Pricing, entitlements, learner APIs, and UI are tested together for Free, Individual, Family, School, and Telco variants.
- [ ] Production smoke test covers referral links, registration, course preview, lesson replay, two-quiz flow, hearts by plan, leaderboard totals, adverts, and email delivery.
- [ ] Update `Mahadum360_Implementation_TODO.md`, relevant architecture/content/RBAC docs, and OpenAPI for every approved product/model change.

## Product decisions required before coding

1. Confirm that the BRD’s full-learning Free tier remains authoritative (BF-10.8 and BF-13.1).
2. Define the one-heart-per-four-questions rule precisely (BF-10.6).
3. Define learning-level thresholds and what leaderboard “score” represents (BF-07.3 and BF-12.2).
4. Clarify the streak/shield sentence and Family Hero scope/tie rules (BF-07.1–BF-07.2).
5. Define tone-feature scope and child-safe invitation/privacy rules (BF-08–BF-09).
6. Define telco plan/channel entitlements without creating an inconsistent learning paywall (BF-15).
7. Choose MAHADUM or FluentCRM as the marketing-consent system of record (BF-16.2).
